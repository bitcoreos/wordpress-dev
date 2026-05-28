<?php
/**
 * Additive WP-Discourse permission snapshot capture for ASHI authorization.
 *
 * This file must not modify WP-Discourse SSO behavior. It only mirrors safe,
 * already-synced Discourse identity and permission metadata into BITCORE usermeta.
 *
 * @package BITCORE\Infrastructure\Auth
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class BITCORE_Discourse_Permission_Capture {
    private const META_DISCOURSE_ID = 'bitcore_discourse_user_id';
    private const META_DISCOURSE_USERNAME = 'bitcore_discourse_username';
    private const META_DISCOURSE_GROUPS = 'bitcore_discourse_groups';
    private const META_DISCOURSE_TRUST_LEVEL = 'bitcore_discourse_trust_level';
    private const META_SNAPSHOT = 'bitcore_discourse_permissions_snapshot';
    private const SOURCE_SSO_ID = 'discourse_sso_user_id';
    private const SOURCE_USERNAME = 'discourse_username';
    private const SNAPSHOT_TTL = 86400;

    public static function register_hooks(): void {
        add_action('wp_login', [self::class, 'capture_on_wp_login'], 10, 2);
        add_action('wpdc_sso_provider_before_sso_redirect', [self::class, 'capture_from_provider_redirect'], 10, 2);
        add_action('wpdc_sso_provider_before_create_user', [self::class, 'capture_from_provider_create_user'], 10, 2);
        add_filter('wpdc_sso_client_updated_user', [self::class, 'capture_from_client_update'], 10, 2);
    }

    public static function capture_on_wp_login(string $user_login, mixed $user): void {
        $user_id = self::extract_user_id($user);
        if ($user_id > 0) {
            self::capture_user($user_id);
        }
    }

    public static function capture_from_provider_redirect(int $user_id, mixed $user = null): void {
        if ($user_id > 0) {
            self::capture_user($user_id);
        }
    }

    public static function capture_from_provider_create_user(string $user_login, mixed $user): void {
        $user_id = self::extract_user_id($user);
        if ($user_id > 0) {
            self::capture_user($user_id);
        }
    }

    public static function capture_from_client_update(mixed $result, mixed $user = null): mixed {
        $user_id = self::extract_user_id($user);
        if ($user_id === 0 && is_numeric($result)) {
            $user_id = (int) $result;
        }
        if ($user_id > 0) {
            self::capture_user($user_id);
        }
        return $result;
    }

    public static function capture_user(int $user_id, array $payload = []): bool {
        if ($user_id <= 0) {
            return false;
        }

        $discourse_id = self::first_non_empty([
            $payload['external_id'] ?? null,
            $payload['discourse_sso_user_id'] ?? null,
            get_user_meta($user_id, self::SOURCE_SSO_ID, true),
        ]);
        if ($discourse_id === '') {
            return false;
        }

        $username = self::first_non_empty([
            $payload['username'] ?? null,
            $payload['name'] ?? null,
            get_user_meta($user_id, self::SOURCE_USERNAME, true),
        ]);

        [$groups, $groups_valid] = self::normalize_groups($payload['groups'] ?? null);
        $trust_level = $groups_valid ? self::normalize_trust_level($payload['trust_level'] ?? null, $groups) : null;

        $snapshot = [
            'user_id' => $discourse_id,
            'username' => $username,
            'groups' => $groups,
            'trust_level' => $trust_level,
            'captured_at' => self::now(),
            'source' => 'wp-discourse',
        ];

        update_user_meta($user_id, self::META_DISCOURSE_ID, $discourse_id);
        if ($username !== '') {
            update_user_meta($user_id, self::META_DISCOURSE_USERNAME, $username);
        }
        update_user_meta($user_id, self::META_DISCOURSE_GROUPS, $groups);
        update_user_meta($user_id, self::META_DISCOURSE_TRUST_LEVEL, $trust_level);
        update_user_meta($user_id, self::META_SNAPSHOT, $snapshot);

        return true;
    }

    public static function get_fresh_permissions_snapshot(int $user_id): array {
        if ($user_id <= 0) {
            return [];
        }
        $snapshot = get_user_meta($user_id, self::META_SNAPSHOT, true);
        if (!is_array($snapshot)) {
            return [];
        }
        $captured_at = isset($snapshot['captured_at']) && is_numeric($snapshot['captured_at']) ? (int) $snapshot['captured_at'] : 0;
        if ($captured_at <= 0 || (self::now() - $captured_at) > self::SNAPSHOT_TTL) {
            return [];
        }
        return $snapshot;
    }

    public static function user_has_discourse_group(int $user_id, string $group): bool {
        $group = sanitize_key($group);
        if ($group === '') {
            return false;
        }
        $snapshot = self::get_fresh_permissions_snapshot($user_id);
        $groups = $snapshot['groups'] ?? [];
        return is_array($groups) && in_array($group, $groups, true);
    }

    public static function user_can_administer_ashi(int $user_id): bool {
        if ($user_id > 0 && user_can($user_id, 'manage_options')) {
            return true;
        }
        return self::user_has_discourse_group($user_id, 'admins') || self::user_has_discourse_group($user_id, 'staff');
    }

    private static function extract_user_id(mixed $user): int {
        if (is_object($user) && isset($user->ID) && is_numeric($user->ID)) {
            return (int) $user->ID;
        }
        if (is_array($user) && isset($user['ID']) && is_numeric($user['ID'])) {
            return (int) $user['ID'];
        }
        return 0;
    }

    private static function first_non_empty(array $values): string {
        foreach ($values as $value) {
            if ($value === null || $value === false || $value === '') {
                continue;
            }
            $clean = sanitize_text_field((string) $value);
            if ($clean !== '') {
                return $clean;
            }
        }
        return '';
    }

    private static function normalize_groups(mixed $groups): array {
        if ($groups === null || $groups === '') {
            return [[], true];
        }
        if (is_string($groups)) {
            $groups = array_map('trim', explode(',', $groups));
        }
        if (!is_array($groups)) {
            return [[], false];
        }
        foreach ($groups as $group) {
            if (!is_scalar($group)) {
                return [[], false];
            }
        }
        $normalized = [];
        foreach ($groups as $group) {
            $clean = sanitize_key(sanitize_text_field((string) $group));
            if ($clean !== '') {
                $normalized[] = $clean;
            }
        }
        return [array_values(array_unique($normalized)), true];
    }

    private static function normalize_trust_level(mixed $trust_level, array $groups): ?int {
        if ($trust_level !== null && $trust_level !== '') {
            if (!is_numeric($trust_level)) {
                return null;
            }
            $level = (int) $trust_level;
            return ($level >= 0 && $level <= 4) ? $level : null;
        }
        foreach ($groups as $group) {
            if (preg_match('/^trust_level_([0-4])$/', (string) $group, $matches) === 1) {
                return (int) $matches[1];
            }
        }
        return null;
    }

    private static function now(): int {
        return function_exists('current_time') ? (int) current_time('timestamp', true) : time();
    }
}

BITCORE_Discourse_Permission_Capture::register_hooks();
