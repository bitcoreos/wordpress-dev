<?php
/**
 * Standalone tests for BITCORE_Container.
 *
 * Run with:
 *   php tests/container/ContainerStandaloneTest.php
 *
 * These tests intentionally avoid WordPress, PHPUnit, network, database, and
 * Composer. They test the real container class directly.
 * @package    Bitcore\Tests
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/classes/container.php';

final class BitcoreContainerStandaloneTest {
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int {
        $this->test_set_get_and_has_registered_service();
        $this->test_register_alias_is_chainable_and_equivalent_to_set();
        $this->test_shared_services_are_singletons_by_default();
        $this->test_non_shared_services_are_factories();
        $this->test_factory_receives_container_instance();
        $this->test_missing_service_throws_precise_runtime_exception();
        $this->test_unregister_removes_definition_and_cached_instance();
        $this->test_unregister_missing_service_returns_false();
        $this->test_get_registered_returns_registered_service_ids();
        $this->test_clear_drops_instances_but_keeps_definitions();
        $this->test_reregistering_shared_service_replaces_cached_instance();
        $this->report();
        return $this->failed === 0 ? 0 : 1;
    }

    private function test_set_get_and_has_registered_service(): void {
        $container = new BITCORE_Container();
        $returned = $container->set('object', fn() => new stdClass());

        $this->assert_same($container, $returned, 'set_returns_container_for_chaining');
        $this->assert_true($container->has('object'), 'has_returns_true_for_registered_service');
        $this->assert_true($container->get('object') instanceof stdClass, 'get_returns_factory_result');
    }

    private function test_register_alias_is_chainable_and_equivalent_to_set(): void {
        $container = new BITCORE_Container();
        $returned = $container->register('answer', fn() => 42);

        $this->assert_same($container, $returned, 'register_returns_container_for_chaining');
        $this->assert_true($container->has('answer'), 'register_adds_service_definition');
        $this->assert_same(42, $container->get('answer'), 'register_get_returns_factory_result');
    }

    private function test_shared_services_are_singletons_by_default(): void {
        $container = new BITCORE_Container();
        $calls = 0;
        $container->set('shared', function () use (&$calls): stdClass {
            $calls++;
            return new stdClass();
        });

        $first = $container->get('shared');
        $second = $container->get('shared');

        $this->assert_same($first, $second, 'shared_service_returns_same_instance');
        $this->assert_same(1, $calls, 'shared_factory_called_once');
    }

    private function test_non_shared_services_are_factories(): void {
        $container = new BITCORE_Container();
        $calls = 0;
        $container->set('factory', function () use (&$calls): stdClass {
            $calls++;
            return new stdClass();
        }, false);

        $first = $container->get('factory');
        $second = $container->get('factory');

        $this->assert_true($first !== $second, 'non_shared_service_returns_new_instance');
        $this->assert_same(2, $calls, 'non_shared_factory_called_per_get');
    }

    private function test_factory_receives_container_instance(): void {
        $container = new BITCORE_Container();
        $container->set('dependency', fn() => 'dep');
        $container->set('consumer', fn(BITCORE_Container $c) => 'uses_' . $c->get('dependency'));

        $this->assert_same('uses_dep', $container->get('consumer'), 'factory_receives_container_instance');
    }

    private function test_missing_service_throws_precise_runtime_exception(): void {
        $container = new BITCORE_Container();

        try {
            $container->get('missing');
            $this->fail('missing_service_throws_runtime_exception');
            return;
        } catch (RuntimeException $exception) {
            $this->assert_same('Service not found: missing', $exception->getMessage(), 'missing_service_exception_message_precise');
        }
    }

    private function test_unregister_removes_definition_and_cached_instance(): void {
        $container = new BITCORE_Container();
        $container->set('temp', fn() => new stdClass());
        $first = $container->get('temp');

        $this->assert_true($first instanceof stdClass, 'unregister_setup_cached_instance_created');
        $this->assert_same(true, $container->unregister('temp'), 'unregister_existing_returns_true');
        $this->assert_same(false, $container->has('temp'), 'unregister_removes_definition');

        try {
            $container->get('temp');
            $this->fail('unregistered_service_cannot_be_resolved');
        } catch (RuntimeException $exception) {
            $this->assert_same('Service not found: temp', $exception->getMessage(), 'unregistered_cached_instance_removed');
        }
    }

    private function test_unregister_missing_service_returns_false(): void {
        $container = new BITCORE_Container();
        $this->assert_same(false, $container->unregister('missing'), 'unregister_missing_returns_false');
    }

    private function test_get_registered_returns_registered_service_ids(): void {
        $container = new BITCORE_Container();
        $container->set('alpha', fn() => 'a');
        $container->set('beta', fn() => 'b');

        $registered = $container->get_registered();
        sort($registered);

        $this->assert_same(['alpha', 'beta'], $registered, 'get_registered_lists_service_ids');
    }

    private function test_clear_drops_instances_but_keeps_definitions(): void {
        $container = new BITCORE_Container();
        $calls = 0;
        $container->set('shared', function () use (&$calls): stdClass {
            $calls++;
            return new stdClass();
        });

        $first = $container->get('shared');
        $container->clear();
        $second = $container->get('shared');

        $this->assert_true($container->has('shared'), 'clear_keeps_service_definition');
        $this->assert_true($first !== $second, 'clear_drops_cached_shared_instance');
        $this->assert_same(2, $calls, 'clear_allows_factory_to_rebuild_instance');
    }

    private function test_reregistering_shared_service_replaces_cached_instance(): void {
        $container = new BITCORE_Container();
        $container->set('service', fn() => 'old');
        $this->assert_same('old', $container->get('service'), 'reregister_setup_old_instance');

        $container->set('service', fn() => 'new');
        $this->assert_same('new', $container->get('service'), 'reregister_replaces_cached_shared_instance');
    }

    private function assert_true(bool $condition, string $name): void {
        $condition ? $this->pass($name) : $this->fail($name);
    }

    private function assert_same(mixed $expected, mixed $actual, string $name): void {
        if ($expected === $actual) {
            $this->pass($name);
            return;
        }
        $this->fail($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }

    private function pass(string $name): void {
        $this->passed++;
        echo "PASS {$name}\n";
    }

    private function fail(string $name): void {
        $this->failed++;
        echo "FAIL {$name}\n";
    }

    private function report(): void {
        echo "\nAssertions passed: {$this->passed}\n";
        echo "Assertions failed: {$this->failed}\n";
    }
}

$test = new BitcoreContainerStandaloneTest();
exit($test->run());
