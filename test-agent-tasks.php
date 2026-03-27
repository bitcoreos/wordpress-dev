<?php
/**
 * BITCORE Tasks Test Suite
 * Run with: wp --allow-root eval-file wp-content/plugins/bitcore-core/test-agent-tasks.php
 */

if ( ! defined( 'WP_CLI' ) ) {
    echo "This test must be run via WP-CLI\n";
    exit( 1 );
}

class BITCORE_Tasks_Test {
    private $passed = 0;
    private $failed = 0;
    private $tests = [];

    public function run() {
        WP_CLI::line( "=== BITCORE Tasks Test Suite ===\n" );
        
        $this->test_cpt_exists();
        $this->test_taxonomy_exists();
        $this->test_meta_fields();
        $this->test_rest_endpoint_registered();
        $this->test_create_task();
        $this->test_rest_list();
        
        $this->report();
    }

    private function test_cpt_exists() {
        $post_types = get_post_types();
        $exists = in_array( 'agent_task', $post_types );
        $this->assert( $exists, 'Agent Task CPT exists', 'agent_task not found in post types' );
    }

    private function test_taxonomy_exists() {
        $taxonomies = get_taxonomies();
        $exists = in_array( 'task_status', $taxonomies );
        $this->assert( $exists, 'Task Status taxonomy exists', 'task_status not found' );
    }

    private function test_meta_fields() {
        $meta_keys = get_registered_meta_keys( 'post', 'agent_task' );
        $has_assigned = isset( $meta_keys['_bitcore_assigned_to'] );
        $this->assert( $has_assigned, 'Meta field assigned_to registered', 'assigned_to meta not found' );
    }

    private function test_rest_endpoint_registered() {
        $routes = rest_get_server()->get_routes();
        $has_endpoint = isset( $routes['/bitcore/v1/tasks'] );
        $this->assert( $has_endpoint, 'REST endpoint /bitcore/v1/tasks registered', 'REST endpoint missing' );
    }

    private function test_create_task() {
        $post_id = wp_insert_post( [
            'post_title'   => 'Test Task',
            'post_type'    => 'agent_task',
            'post_status'  => 'publish',
            'post_author'  => 1,
        ] );
        
        if ( ! is_wp_error( $post_id ) ) {
            update_post_meta( $post_id, '_bitcore_assigned_to', 'hyperion' );
            update_post_meta( $post_id, '_bitcore_priority', 'high' );
            
            $task = get_post( $post_id );
            $this->assert( $task && $task->post_type === 'agent_task', 
                'Can create agent_task', 'Failed to create task' );
            
            // Cleanup
            wp_delete_post( $post_id, true );
        } else {
            $this->assert( false, 'Can create agent_task', $post_id->get_error_message() );
        }
    }

    private function test_rest_list() {
        $request = new WP_REST_Request( 'GET', '/bitcore/v1/tasks' );
        $response = rest_get_server()->dispatch( $request );
        $this->assert( $response->get_status() === 200, 
            'REST list endpoint responds', 'REST list failed: ' . $response->get_status() );
    }

    private function assert( $condition, $test_name, $error = '' ) {
        if ( $condition ) {
            $this->passed++;
            $this->tests[] = [ 'name' => $test_name, 'status' => 'PASS' ];
            WP_CLI::success( $test_name );
        } else {
            $this->failed++;
            $this->tests[] = [ 'name' => $test_name, 'status' => 'FAIL', 'error' => $error ];
            WP_CLI::error( $test_name . ': ' . $error, false );
        }
    }

    private function report() {
        WP_CLI::line( "\n=== Test Report ===" );
        WP_CLI::line( "Passed: " . $this->passed );
        WP_CLI::line( "Failed: " . $this->failed );
        WP_CLI::line( "Total:  " . ( $this->passed + $this->failed ) );
        
        if ( $this->failed === 0 ) {
            WP_CLI::success( "All tests passed!" );
        } else {
            WP_CLI::warning( "Some tests failed" );
        }
    }
}

// Run tests
$test = new BITCORE_Tasks_Test();
$test->run();
