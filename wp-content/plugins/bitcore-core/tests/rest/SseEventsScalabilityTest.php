<?php
/**
 * Sseeventsscalabilitytest.
 *
 * @package    Bitcore\Tests
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

define('ABSPATH', '/tmp/wordpress/');

$GLOBALS['bitcore_sse_test'] = [
    'routes' => [],
    'transients' => [],
    'deleted' => [],
    'can_read' => false,
    'api_key_valid' => false,
];

function register_rest_route(string $namespace, string $route, array $args): void {
    $GLOBALS['bitcore_sse_test']['routes'][] = [$namespace, $route, $args];
}
function current_user_can(string $capability): bool {
    return $GLOBALS['bitcore_sse_test']['can_read'] && $capability === 'read';
}
function getallheaders(): array {
    return $GLOBALS['bitcore_sse_test']['headers'] ?? [];
}
function current_time(string $type): string {
    return '2026-05-24 02:10:00';
}
function get_transient(string $key): mixed {
    return $GLOBALS['bitcore_sse_test']['transients'][$key] ?? false;
}
function set_transient(string $key, mixed $value, int $expiration = 0): bool {
    $GLOBALS['bitcore_sse_test']['transients'][$key] = $value;
    return true;
}
function delete_transient(string $key): bool {
    $GLOBALS['bitcore_sse_test']['deleted'][] = $key;
    unset($GLOBALS['bitcore_sse_test']['transients'][$key]);
    return true;
}
function do_action(string $hook, mixed ...$args): void {}
function __return_true(): bool { return true; }

class BITCORE_Rest_Auth_Filter {
    public static function validate_api_key(string $api_key): bool {
        return $GLOBALS['bitcore_sse_test']['api_key_valid'];
    }
}

require_once dirname(__DIR__, 2) . '/rest/sse-events.php';

final class SseEventsScalabilityTest {
    private int $passed = 0;
    private int $failed = 0;
    private string $source;

    public function __construct() {
        $this->source = file_get_contents(dirname(__DIR__, 2) . '/rest/sse-events.php');
    }

    public function run(): int {
        $this->test_stream_route_is_not_public();
        $this->test_broadcast_persists_to_global_queue_without_live_static_clients();
        $this->test_broadcast_queue_is_capped();
        $this->test_stream_source_has_no_blocking_loop_primitives();
        $this->test_chat_realtime_event_types_are_broadcastable();
        $this->test_broadcast_rejects_invalid_type();

        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
        return $this->failed === 0 ? 0 : 1;
    }

    private function reset_state(): void {
        $GLOBALS['bitcore_sse_test']['routes'] = [];
        $GLOBALS['bitcore_sse_test']['transients'] = [];
        $GLOBALS['bitcore_sse_test']['deleted'] = [];
        $GLOBALS['bitcore_sse_test']['can_read'] = false;
        $GLOBALS['bitcore_sse_test']['api_key_valid'] = false;
    }

    private function test_stream_route_is_not_public(): void {
        $this->reset_state();
        BITCORE_Rest_Sse_Events::register_routes();
        $stream = null;
        foreach ($GLOBALS['bitcore_sse_test']['routes'] as $route) {
            if ($route[1] === '/events/stream') {
                $stream = $route[2];
            }
        }
        $callback = $stream['permission_callback'] ?? null;
        $this->assert_true(is_array($callback) && $callback[1] === 'stream_permission', 'stream_route_uses_stream_permission_not_public');
    }

    private function test_broadcast_persists_to_global_queue_without_live_static_clients(): void {
        $this->reset_state();
        BITCORE_Rest_Sse_Events::broadcast('system', ['message' => 'hello']);
        $queue = $GLOBALS['bitcore_sse_test']['transients']['bitcore_sse_events_queue'] ?? null;
        $this->assert_true(is_array($queue), 'broadcast_writes_global_queue');
        $this->assert_same('system', $queue[0]['type'] ?? null, 'broadcast_global_queue_event_type');
        $this->assert_same('hello', $queue[0]['payload']['message'] ?? null, 'broadcast_global_queue_payload');
    }

    private function test_broadcast_queue_is_capped(): void {
        $this->reset_state();
        for ($i = 0; $i < 150; $i++) {
            BITCORE_Rest_Sse_Events::broadcast('system', ['i' => $i]);
        }
        $queue = $GLOBALS['bitcore_sse_test']['transients']['bitcore_sse_events_queue'] ?? [];
        $this->assert_true(count($queue) <= 100, 'broadcast_queue_capped_at_100');
        $this->assert_same(50, $queue[0]['payload']['i'] ?? null, 'broadcast_queue_drops_oldest_events');
    }

    private function test_stream_source_has_no_blocking_loop_primitives(): void {
        $this->assert_not_contains('while (connection_aborted()', $this->source, 'stream_has_no_connection_aborted_loop');
        $this->assert_not_contains('set_time_limit(0)', $this->source, 'stream_has_no_unlimited_time_limit');
        $this->assert_not_contains('sleep(', $this->source, 'stream_has_no_sleep_call');
        $this->assert_not_contains('usleep(', $this->source, 'stream_has_no_usleep_call');
    }

    private function test_chat_realtime_event_types_are_broadcastable(): void {
        foreach (['THINKING', 'messageComplete', 'message_sent'] as $type) {
            $this->reset_state();
            $request = new class($type) {
                public function __construct(private string $type) {}
                public function get_param(string $key): mixed {
                    if ($key === 'type') {
                        return $this->type;
                    }
                    return ['message' => 'hello', 'channel_id' => 'channel-123'];
                }
            };
            $result = BITCORE_Rest_Sse_Events::handle_broadcast($request);
            $this->assert_true(!($result instanceof WP_Error), 'broadcast_accepts_chat_realtime_type_' . $type);
            $queue = $GLOBALS['bitcore_sse_test']['transients']['bitcore_sse_events_queue'] ?? [];
            $this->assert_same($type, $queue[0]['type'] ?? null, 'broadcast_queues_chat_realtime_type_' . $type);
        }
    }

    private function test_broadcast_rejects_invalid_type(): void {
        $request = new class {
            public function get_param(string $key): mixed {
                return $key === 'type' ? 'invalid' : [];
            }
        };
        $result = BITCORE_Rest_Sse_Events::handle_broadcast($request);
        $this->assert_true($result instanceof WP_Error, 'broadcast_invalid_type_returns_wp_error');
    }

    private function assert_same(mixed $expected, mixed $actual, string $name): void {
        $this->assert_true($expected === $actual, $name);
    }

    private function assert_not_contains(string $needle, string $haystack, string $name): void {
        $this->assert_true(strpos($haystack, $needle) === false, $name);
    }

    private function assert_true(bool $condition, string $name): void {
        if ($condition) {
            $this->passed++;
            echo "PASS {$name}\n";
            return;
        }
        $this->failed++;
        echo "FAIL {$name}\n";
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public string $code;
        public string $message;
        public array $data;
        public function __construct(string $code = '', string $message = '', array $data = []) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }
    }
}

$test = new SseEventsScalabilityTest();
exit($test->run());
