<?php
/**
 * BITCORE Abstract Connector Tests
 * Tests for connector base class functionality
 *
 * @package BITCORE\Tests\Connectors
 * @since 1.3.0
 * @version    1.0.0
 * @author     coraline
 */

declare(strict_types=1);

namespace BITCORE\Tests\Connectors;

use PHPUnit\Framework\TestCase;

/**
 * Class AbstractConnectorTest
 * Tests for BITCORE_Abstract_Connector base class
 */
class AbstractConnectorTest extends TestCase {

    /**
     * Test concrete connector can extend abstract base
     */
    public function test_concrete_connector_extends_base(): void {
        $connector = $this->create_test_connector();
        
        $this->assertInstanceOf(\BITCORE_Abstract_Connector::class, $connector);
    }

    /**
     * Test connector name method
     */
    public function test_get_name(): void {
        $connector = $this->create_test_connector();
        
        $this->assertEquals('test_connector', $connector->get_name());
    }

    /**
     * Test is_configured returns true when API URL and key set
     */
    public function test_is_configured_returns_true_with_credentials(): void {
        $connector = $this->create_test_connector(
            'https://api.example.com',
            'test-api-key'
        );
        
        $this->assertTrue($connector->is_configured());
    }

    /**
     * Test is_configured returns false without API key
     */
    public function test_is_configured_returns_false_without_key(): void {
        $connector = $this->create_test_connector(
            'https://api.example.com',
            null
        );
        
        $this->assertFalse($connector->is_configured());
    }

    /**
     * Test get_last_error returns empty string initially
     */
    public function test_get_last_error_empty_initially(): void {
        $connector = $this->create_test_connector();
        
        $this->assertEquals('', $connector->get_last_error());
    }

    /**
     * Test get_request_log returns empty array initially
     */
    public function test_get_request_log_empty_initially(): void {
        $connector = $this->create_test_connector();
        
        $this->assertEquals([], $connector->get_request_log());
    }

    /**
     * Test clear_request_log empties log
     */
    public function test_clear_request_log(): void {
        $connector = $this->create_test_connector();
        
        // Simulate adding to log via reflection
        $reflection = new \ReflectionClass($connector);
        $property = $reflection->getProperty('request_log');
        $property->setAccessible(true);
        $property->setValue($connector, [['test' => 'entry']]);
        
        $this->assertCount(1, $connector->get_request_log());
        
        $connector->clear_request_log();
        
        $this->assertEquals([], $connector->get_request_log());
    }

    /**
     * Test default headers include Content-Type and Accept
     */
    public function test_default_headers(): void {
        $connector = $this->create_test_connector(
            'https://api.example.com',
            'test-key'
        );
        
        $reflection = new \ReflectionClass($connector);
        $method = $reflection->getMethod('get_default_headers');
        $method->setAccessible(true);
        
        $headers = $method->invoke($connector);
        
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals('application/json', $headers['Accept']);
        $this->assertEquals('Bearer test-key', $headers['Authorization']);
    }

    /**
     * Test timeout default value
     */
    public function test_timeout_default(): void {
        $connector = $this->create_test_connector();
        
        $reflection = new \ReflectionClass($connector);
        $property = $reflection->getProperty('timeout');
        $property->setAccessible(true);
        
        $this->assertEquals(30, $property->getValue($connector));
    }

    /**
     * Create test connector instance
     */
    private function create_test_connector(
        ?string $api_url = 'https://api.test.com',
        ?string $api_key = 'test-key'
    ): \BITCORE_Abstract_Connector {
        return new class($api_url, $api_key) extends \BITCORE_Abstract_Connector {
            private ?string $test_api_key;
            private string $test_api_url;
            
            public function __construct(?string $api_url, ?string $api_key) {
                $this->test_api_url = $api_url ?? '';
                $this->test_api_key = $api_key;
                // Skip parent constructor, set properties directly
                $this->api_url = $this->test_api_url;
                $this->api_key = $this->test_api_key;
            }
            
            protected function get_api_key(): ?string {
                return $this->test_api_key;
            }
            
            protected function get_api_url(): string {
                return $this->test_api_url;
            }
            
            public function get_name(): string {
                return 'test_connector';
            }
        };
    }
}
