<?php
/**
 * BITCORE Container Tests
 * PSR-11 inspired dependency injection container tests
 *
 * @package BITCORE\Tests
 * @since 1.3.0
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

namespace BITCORE\Tests\Container;

use PHPUnit\Framework\TestCase;

/**
 * Class ContainerTest
 * Tests for BITCORE_Container service container
 */
class ContainerTest extends TestCase {

    /**
     * Test service registration and retrieval
     */
    public function test_set_and_get_service(): void {
        $container = new \BITCORE_Container();
        
        $container->set('test_service', fn() => new \stdClass());
        
        $this->assertTrue($container->has('test_service'));
        $this->assertInstanceOf(\stdClass::class, $container->get('test_service'));
    }

    /**
     * Test singleton pattern (shared services)
     */
    public function test_singleton_pattern(): void {
        $container = new \BITCORE_Container();
        
        $container->set('singleton', fn() => new \stdClass(), true);
        
        $instance1 = $container->get('singleton');
        $instance2 = $container->get('singleton');
        
        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test factory pattern (non-shared services)
     */
    public function test_factory_pattern(): void {
        $container = new \BITCORE_Container();
        
        $container->set('factory', fn() => new \stdClass(), false);
        
        $instance1 = $container->get('factory');
        $instance2 = $container->get('factory');
        
        $this->assertNotSame($instance1, $instance2);
    }

    /**
     * Test exception on missing service
     */
    public function test_exception_on_missing_service(): void {
        $container = new \BITCORE_Container();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Service not found: non_existent');
        
        $container->get('non_existent');
    }

    /**
     * Test has() returns false for non-existent service
     */
    public function test_has_returns_false_for_non_existent(): void {
        $container = new \BITCORE_Container();
        
        $this->assertFalse($container->has('non_existent'));
    }

    /**
     * Test factory receives container as argument
     */
    public function test_factory_receives_container(): void {
        $container = new \BITCORE_Container();
        
        $container->set('dependency', fn() => 'dep_value');
        $container->set('dependent', fn($c) => 'uses_' . $c->get('dependency'));
        
        $this->assertEquals('uses_dep_value', $container->get('dependent'));
    }
}
