<?php
declare(strict_types=1);
/**
 * Test Class for Sanitization Functions
 *
 * PHPUnit test cases for WordPress sanitization and security functions.
 *
 * Based on patterns from bitcore/wordpress-security
 *
 * @package BITCORE
 * @since 1.0.0
 * @author BITCORE Team
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class BITCORE_Tests_Sanitization
 *
 * Tests sanitization functions for security hardening.
 *
 * @since 1.0.0
 */
class BITCORE_Tests_Sanitization extends WP_UnitTestCase {

    /**
     * REST Tasks controller instance
     *
     * @var BITCORE_Rest_Tasks
     */
    private $controller;

    /**
     * Setup before tests
     *
     * @since 1.0.0
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->controller = new BITCORE_Rest_Tasks();
    }

    /**
     * Test string sanitization with sanitize_text_field
     *
     * @since 1.0.0
     * @return void
     */
    public function test_sanitize_text_field(): void {
        $input    = '  Hello <script>alert("xss")</script> World  ';
        $expected = 'Hello alert("xss") World';
        $result   = sanitize_text_field( $input );
        $this->assertEquals( $expected, $result );
    }

    /**
     * Test title sanitization
     *
     * @since 1.0.0
     * @return void
     */
    public function test_sanitize_title(): void {
        $input    = 'Hello World! @#$%';
        $expected = 'hello-world';
        $result   = sanitize_title( $input );
        $this->assertEquals( $expected, $result );
    }

    /**
     * Test XSS prevention with script tags
     *
     * @since 1.0.0
     * @return void
     */
    public function test_xss_prevention_script_tags(): void {
        $input = '<script>alert("xss")</script>';
        $result = sanitize_text_field( $input );
        $this->assertStringNotContainsString( '<script>', $result );
        $this->assertStringNotContainsString( '</script>', $result );
    }

    /**
     * Test XSS prevention with event handlers
     *
     * @since 1.0.0
     * @return void
     */
    public function test_xss_prevention_event_handlers(): void {
        $input = '<img src=x onerror=alert(1)>';
        $result = wp_kses_post( $input );
        $this->assertStringNotContainsString( 'onerror', $result );
    }

    /**
     * Test javascript: URL prevention
     *
     * @since 1.0.0
     * @return void
     */
    public function test_xss_prevention_javascript_url(): void {
        $input = 'javascript:alert(1)';
        $result = esc_url( $input );
        $this->assertStringNotContainsString( 'javascript:', $result );
    }

    /**
     * Test recursive sanitization with nested arrays
     *
     * @since 1.0.0
     * @return void
     */
    public function test_recursive_sanitization_nested_arrays(): void {
        $input = array(
            'level1' => array(
                'level2' => array(
                    'data' => '  test<script>alert(1)</script>  '
                )
            )
        );
        $result = BITCORE_Rest_Tasks::sanitize_recursive( $input );
        $this->assertEquals(
            'testalert(1)',
            $result['level1']['level2']['data']
        );
    }

    /**
     * Test recursive sanitization with mixed types
     *
     * @since 1.0.0
     * @return void
     */
    public function test_recursive_sanitization_mixed_types(): void {
        $input = array(
            'string'  => 'test<script>',
            'integer' => 42,
            'float'   => 3.14,
            'boolean' => true,
            'null'    => null,
        );
        $result = BITCORE_Rest_Tasks::sanitize_recursive( $input );
        $this->assertEquals( 'test', $result['string'] );
        $this->assertEquals( 42, $result['integer'] );
        $this->assertEquals( 3.14, $result['float'] );
        $this->assertTrue( $result['boolean'] );
        $this->assertNull( $result['null'] );
    }

    /**
     * Test malformed input handling
     *
     * @since 1.0.0
     * @return void
     */
    public function test_malformed_input_handling(): void {
        $input = "\x01\x02test";
        $result = sanitize_text_field( $input );
        $this->assertStringNotContainsString( "", $result );
    }

    /**
     * Test SQL injection prevention
     *
     * @since 1.0.0
     * @return void
     */
    public function test_sql_injection_prevention(): void {
        $input = "'; DROP TABLE users; --";
        $result = sanitize_text_field( $input );
        $this->assertStringNotContainsString( ';', $result );
        $this->assertStringNotContainsString( '--', $result );
    }

    /**
     * Test null input handling
     *
     * @since 1.0.0
     * @return void
     */
    public function test_null_input(): void {
        $input = null;
        $result = BITCORE_Rest_Tasks::sanitize_recursive( $input );
        $this->assertNull( $result );
    }

    /**
     * Test empty array handling
     *
     * @since 1.0.0
     * @return void
     */
    public function test_empty_array(): void {
        $input = array();
        $result = BITCORE_Rest_Tasks::sanitize_recursive( $input );
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    /**
     * Test unicode character handling
     *
     * @since 1.0.0
     * @return void
     */
    public function test_unicode_characters(): void {
        $input = 'Hello 世界 ñ é';
        $result = sanitize_text_field( $input );
        $this->assertEquals( $input, $result );
    }

    /**
     * Test special character handling
     *
     * @since 1.0.0
     * @return void
     */
    public function test_special_characters(): void {
        $input = 'Test & "quotes" 'apostrophe' <br>';
        $result = sanitize_text_field( $input );
        $this->assertStringNotContainsString( '<br>', $result );
    }

    /**
     * Test wp_kses_post with allowed HTML
     *
     * @since 1.0.0
     * @return void
     */
    public function test_wp_kses_post_allowed_html(): void {
        $input = '<p>Paragraph</p><strong>Bold</strong><script>alert(1)</script>';
        $result = wp_kses_post( $input );
        $this->assertStringContainsString( '<p>', $result );
        $this->assertStringContainsString( '<strong>', $result );
        $this->assertStringNotContainsString( '<script>', $result );
    }

    /**
     * Test sanitize_key function
     *
     * @since 1.0.0
     * @return void
     */
    public function test_sanitize_key(): void {
        $input = 'Test_Key-123!';
        $expected = 'test_key-123';
        $result = sanitize_key( $input );
        $this->assertEquals( $expected, $result );
    }

    /**
     * Test esc_attr function
     *
     * @since 1.0.0
     * @return void
     */
    public function test_esc_attr(): void {
        $input = 'test" onclick="alert(1)';
        $result = esc_attr( $input );
        $this->assertStringNotContainsString( '"', $result );
        $this->assertStringNotContainsString( 'onclick', $result );
    }

    /**
     * Test esc_html function
     *
     * @since 1.0.0
     * @return void
     */
    public function test_esc_html(): void {
        $input = '<strong>Bold</strong>';
        $result = esc_html( $input );
        $this->assertStringNotContainsString( '<strong>', $result );
    }
}
