<?php
/**
 * Knowledge REST API Controller - trait
 *
 * @package BITCORE\Rest
 * @since       1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Rest;

if (!defined('ABSPATH')) exit;

trait Knowledge_Api_Helpers {

    /* ---------- helpers ---------- */

    private function normalize_limit(mixed $limit): int {
        return max(min((int) ($limit ?: 20), 100), 1);
    }

    private function get_bithub_connector(): mixed {
        if (isset($GLOBALS['bitcore']->connector_bithub)) {
            return $GLOBALS['bitcore']->connector_bithub;
        }
        if (class_exists('BITCORE_Services_Connector_BIThub')) {
            return new \BITCORE_Services_Connector_BIThub($GLOBALS['bitcore'] ?? null);
        }
        return null;
    }

    private function get_bitwiki_connector(): mixed {
        if (isset($GLOBALS['bitcore']->connector_bitwiki)) {
            return $GLOBALS['bitcore']->connector_bitwiki;
        }
        if (class_exists('BITCORE_Services_Connector_BITwiki')) {
            return new \BITCORE_Services_Connector_BITwiki($GLOBALS['bitcore'] ?? null);
        }
        return null;
    }

    private function normalize_knowledge_card(array $item, string $source, string $default_type): array {
        $id = $item['id'] ?? $item['topic_id'] ?? $item['page_id'] ?? $item['slug'] ?? $item['title'] ?? uniqid($source . '-', false);
        $title = $item['title'] ?? $item['name'] ?? 'Untitled';
        $description = $item['description'] ?? $item['excerpt'] ?? $item['summary'] ?? $item['content'] ?? '';
        $tags = $item['tags'] ?? $item['keywords'] ?? [];
        if (is_string($tags)) {
            $tags = array_values(array_filter(array_map('trim', explode(',', $tags))));
        }
        if (!is_array($tags)) {
            $tags = [];
        }

        return [
            'id'          => (string) $id,
            'title'       => sanitize_text_field($title),
            'description' => wp_kses_post($description),
            'source'      => $source,
            'type'        => sanitize_text_field($item['type'] ?? $default_type),
            'tags'        => array_values(array_map('sanitize_text_field', $tags)),
            'updated_at'  => sanitize_text_field($item['updated_at'] ?? $item['created_at'] ?? $item['last_posted_at'] ?? ''),
            'url'         => isset($item['url']) ? sanitize_text_field($item['url']) : '',
        ];
    }

    private function get_item_by_id(int $id): ?array {
        $table = $this->wpdb->prefix . 'bitcore_knowledge';
        $row   = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    private function can_read_item(array $item): bool {
        $user_id  = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        if ($is_admin || (int) $item['user_id'] === $user_id) {
            return true;
        }
        if (in_array($item['status'], ['shared', 'published'], true)) {
            return is_user_logged_in();
        }
        return false;
    }

    private function can_modify_item(array $item): bool {
        $is_admin = current_user_can('manage_options');
        if ($is_admin) {
            return true;
        }
        if ((int) $item['user_id'] !== get_current_user_id()) {
            return false;
        }
        return in_array($item['status'], ['private', 'draft', 'review'], true);
    }

    private function validate_transition(string $from, string $to, array $item): \WP_Error|true {
        $is_admin = current_user_can('manage_options');

        if (!isset($this->transitions[$from]) || !in_array($to, $this->transitions[$from], true)) {
            return new \WP_Error('invalid_transition', "Cannot transition from {$from} to {$to}");
        }

        if ($is_admin) {
            return true;
        }

        if ((int) $item['user_id'] !== get_current_user_id()) {
            return new \WP_Error('not_owner', 'You do not own this item');
        }

        if ($from === 'review' && in_array($to, ['shared', 'published'], true)) {
            return new \WP_Error('admin_required', 'Only admin can transition to shared or published');
        }
        if ($from === 'shared' && $to === 'published') {
            return new \WP_Error('admin_required', 'Only admin can publish');
        }

        return true;
    }

    private function sanitize_tags($tags): string {
        if (is_array($tags)) {
            $tags = array_map('sanitize_text_field', $tags);
            return implode(',', $tags);
        }
        return sanitize_text_field($tags ?? '');
    }
}
