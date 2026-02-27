<?php
/**
 * BITCORE REST API Test Suite
 */
class BITCORE_Rest_API_Test extends WP_UnitTestCase {
    private $namespace = 'bitcore/v1';
    public function test_abilities_endpoint_exists() {
        $request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/abilities' );
        $response = rest_do_request( $request );
        $this->assertEquals( 200, $response->get_status() );
    }
    public function test_mcp_tools_endpoint() {
        $request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/mcp/tools' );
        $response = rest_do_request( $request );
        $data = $response->get_data();
        $this->assertArrayHasKey( 'tools', $data );
    }
}
