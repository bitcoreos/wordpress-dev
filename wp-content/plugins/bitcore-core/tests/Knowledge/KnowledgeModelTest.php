<?php
/**
 * Knowledge Pipeline Tests
 *
 * @package BITCORE\Tests\Knowledge
 * @since      2026.11.23
 * @version    1.0.0
 * @author     coraline
 */

namespace BITCORE\Tests\Knowledge;

use BITCORE\Rest\Knowledge_Api;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class KnowledgeModelTest
 */
class KnowledgeModelTest extends \WP_UnitTestCase {
    private Knowledge_Api $api;
    private int $user_id;
    private int $admin_id;

    public function setUp(): void {
        parent::setUp();
        $this->api = new Knowledge_Api();
        $this->user_id = self::factory()->user->create(['role' => 'subscriber']);
        $this->admin_id = self::factory()->user->create(['role' => 'administrator']);
    }

    public function test_create_with_default_private_status(): void {
        wp_set_current_user($this->user_id);
        $request = new WP_REST_Request('POST', '/bitcore/v1/knowledge');
        $request->set_param('title', 'Test Title');
        $request->set_param('content', 'Test content');

        $response = $this->api->create_item($request);
        $data = $response->get_data();

        $this->assertEquals(201, $response->get_status());
        $this->assertEquals('private', $data['status']);
        $this->assertTrue($data['success']);
    }

    public function test_user_scoping(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_knowledge';
        $wpdb->insert($table, [
            'user_id' => $this->user_id,
            'title'   => 'User A item',
            'content' => 'Content A',
            'status'  => 'private',
        ]);
        $item_a = $wpdb->insert_id;

        $wpdb->insert($table, [
            'user_id' => $this->admin_id,
            'title'   => 'User B item',
            'content' => 'Content B',
            'status'  => 'private',
        ]);

        wp_set_current_user($this->user_id);
        $request = new WP_REST_Request('GET', '/bitcore/v1/knowledge');
        $response = $this->api->get_items($request);
        $data = $response->get_data();

        $this->assertCount(1, $data['items']);
        $this->assertEquals('User A item', $data['items'][0]['title']);
    }

    public function test_valid_status_transition(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_knowledge';
        $wpdb->insert($table, [
            'user_id' => $this->user_id,
            'title'   => 'Transition test',
            'content' => 'Content',
            'status'  => 'private',
        ]);
        $id = $wpdb->insert_id;

        wp_set_current_user($this->user_id);
        $request = new WP_REST_Request('POST', '/bitcore/v1/knowledge/' . $id . '/transition');
        $request->set_param('id', $id);
        $request->set_param('status', 'draft');

        $response = $this->api->transition_item($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertEquals('draft', $data['status']);
    }

    public function test_invalid_status_transition_returns_400(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_knowledge';
        $wpdb->insert($table, [
            'user_id' => $this->user_id,
            'title'   => 'Invalid transition',
            'content' => 'Content',
            'status'  => 'private',
        ]);
        $id = $wpdb->insert_id;

        wp_set_current_user($this->user_id);
        $request = new WP_REST_Request('POST', '/bitcore/v1/knowledge/' . $id . '/transition');
        $request->set_param('id', $id);
        $request->set_param('status', 'published');

        $response = $this->api->transition_item($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_publish_403_for_non_admin_private(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_knowledge';
        $wpdb->insert($table, [
            'user_id' => $this->user_id,
            'title'   => 'Private item',
            'content' => 'Content',
            'status'  => 'private',
        ]);
        $id = $wpdb->insert_id;

        wp_set_current_user($this->user_id);
        $request = new WP_REST_Request('POST', '/bitcore/v1/knowledge/' . $id . '/publish');
        $request->set_param('id', $id);

        $response = $this->api->publish_item($request);

        $this->assertEquals(403, $response->get_status());
    }

    public function test_admin_override(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'bitcore_knowledge';
        $wpdb->insert($table, [
            'user_id' => $this->user_id,
            'title'   => 'Admin access',
            'content' => 'Content',
            'status'  => 'private',
        ]);
        $id = $wpdb->insert_id;

        wp_set_current_user($this->admin_id);
        $request = new WP_REST_Request('GET', '/bitcore/v1/knowledge/' . $id);
        $request->set_param('id', $id);

        $response = $this->api->get_item($request);
        $data = $response->get_data();

        $this->assertEquals('Admin access', $data['title']);
    }
}
