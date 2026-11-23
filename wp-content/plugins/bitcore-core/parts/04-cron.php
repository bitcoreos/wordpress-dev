<?php
/**
 * Cron schedules, scheduled events and retry hooks
 *
 * Extracted verbatim from bitcore-core.php; require_once order is the original
 * execution order. Bootstrap remains the plugin entry point.
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     coraline
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// === FROM HYPERION: Custom Cron Schedules ===
function bitcore_cron_schedules(array $schedules): array {
    $schedules['bitcore_queue_1min'] = [
        'interval' => 60,
        'display'  => __('BITCORE Queue: Every Minute'),
    ];
    $schedules['bitcore_queue_5min'] = [
        'interval' => 300,
        'display'  => __('BITCORE Queue: Every 5 Minutes'),
    ];
    $schedules['bitcore_webhooks_retry'] = [
        'interval' => 900,
        'display'  => __('BITCORE Webhooks: Retry Failed (15 min)'),
    ];
    $schedules['bitcore_health_check'] = [
        'interval' => 3600,
        'display'  => __('BITCORE System: Health Check (Hourly)'),
    ];
    return $schedules;
}
add_filter('cron_schedules', 'bitcore_cron_schedules', 10, 1);

// === FROM HYPERION: Schedule Cron Events ===
function bitcore_schedule_cron(): void {
    if (!wp_next_scheduled('bitcore_queue_worker')) {
        wp_schedule_event(time(), 'bitcore_queue_1min', 'bitcore_queue_worker');
    }
    if (!wp_next_scheduled('bitcore_webhooks_retry')) {
        wp_schedule_event(time(), 'bitcore_webhooks_retry', 'bitcore_webhooks_retry');
    }
    if (!wp_next_scheduled('bitcore_health_check')) {
        wp_schedule_event(time(), 'bitcore_health_check', 'bitcore_health_check');
    }
    if (!wp_next_scheduled('bitcore_cleanup_sessions')) {
        wp_schedule_event(time(), 'hourly', 'bitcore_cleanup_sessions');
    }
    if (!wp_next_scheduled('bitcore_cleanup_transcriptions')) {
        wp_schedule_event(time(), 'daily', 'bitcore_cleanup_transcriptions');
    }
}
add_action('wp', 'bitcore_schedule_cron');

// === FROM HYPERION: Queue Worker Cron ===
// DISABLED: queue.worker depends on job.repository and logger — both missing.
// function bitcore_queue_worker_cron(): void {
//     if (isset($GLOBALS['bitcore']) && $GLOBALS['bitcore']->container) {
//         $worker = $GLOBALS['bitcore']->container->get('queue.worker');
//         if ($worker) {
//             $worker->process(10);
//         }
//     }
// }
// add_action('bitcore_queue_worker', 'bitcore_queue_worker_cron');

// === FROM HYPERION: Webhooks Retry Cron ===
function bitcore_webhooks_retry_cron(): void {
    if (isset($GLOBALS['bitcore']) && $GLOBALS['bitcore']->container) {
        try {
            $manager = $GLOBALS['bitcore']->container->get('webhooks.manager');
            if ($manager) {
                $manager->retryFailed(5);
            }
        } catch (Exception $e) {
            // Service not registered; silently skip
        }
    }
}
add_action('bitcore_webhooks_retry', 'bitcore_webhooks_retry_cron');

// === FROM HYPERION: Health Check Cron ===
// DISABLED: logger service references missing infrastructure/Logging/Logger.php
// function bitcore_health_check_cron(): void {
//     if (isset($GLOBALS['bitcore']) && $GLOBALS['bitcore']->container) {
//         $logger = $GLOBALS['bitcore']->container->get('logger');
//         if ($logger) {
//             $health = [
//                 'timestamp' => time(),
//                 'memory'    => memory_get_usage(true),
//                 'peak'      => memory_get_peak_usage(true),
//                 'queue'     => $GLOBALS['wpdb']->get_var("SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}bitcore_jobs WHERE status = 'pending'"),
//             ];
//             $logger->info('Health check completed', $health);
//         }
//     }
// }
// add_action('bitcore_health_check', 'bitcore_health_check_cron');

// === FROM CORALINE: Token Rotation Schedule ===
function bitcore_schedule_token_check(): void {
    if (!wp_next_scheduled('bitcore_token_rotation_check')) {
        wp_schedule_event(time(), 'daily', 'bitcore_token_rotation_check');
    }
}
add_action('wp', 'bitcore_schedule_token_check');

add_action('bitcore_token_rotation_check', function() {
    if (class_exists('BITCORE_Services_Token_Manager')) {
        BITCORE_Services_Token_Manager::ensure_valid_token();
    }
});

// === FROM HYPERION: Clear Cron on Deactivation ===
function bitcore_clear_cron(): void {
    wp_clear_scheduled_hook('bitcore_queue_worker');
    wp_clear_scheduled_hook('bitcore_webhooks_retry');
    wp_clear_scheduled_hook('bitcore_health_check');
    wp_clear_scheduled_hook('bitcore_token_rotation_check');
    wp_clear_scheduled_hook('bitcore_cleanup_sessions');
    wp_clear_scheduled_hook('bitcore_cleanup_transcriptions');
}
add_action('bitcore_clear_cron', 'bitcore_clear_cron');
