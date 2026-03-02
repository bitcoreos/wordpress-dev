<?php

declare(strict_types=1);

/**
 * BITCORE Command Center - Workflow Runner
 *
 * @package BITCORE\CommandCenter
 * @since 1.0.0
 */

namespace BITCORE\CommandCenter;

use WP_Error;

/**
 * Workflow Runner Class
 *
 * Executes n8n workflows with tracking and status management.
 *
 * @since 1.0.0
 */
class Workflow_Runner
{
    /** @var string n8n base URL */
    private string $n8n_url;

    /** @var string n8n API key */
    private string $n8n_api_key;

    /** @var string Cache key prefix */
    private const CACHE_PREFIX = 'bitcore_workflow_';

    /** @var int Cache TTL in seconds */
    private const CACHE_TTL = 60;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->n8n_url = get_option('bitcore_n8n_url', 'https://o27fv62pv9derdal22sf0pn13k.ingress.boogle.cloud');
        $this->n8n_api_key = get_option('bitcore_n8n_api_key', '');
    }

    /**
     * Get all available workflows.
     *
     * @param bool $active_only Whether to return only active workflows.
     * @return array<int, array<string, mixed>> List of workflows.
     * @since 1.0.0
     */
    public function get_workflows(bool $active_only = false): array
    {
        $cache_key = self::CACHE_PREFIX . 'list_' . ($active_only ? 'active' : 'all');
        $cached = wp_cache_get($cache_key, 'bitcore');

        if (false !== $cached) {
            return $cached;
        }

        $response = $this->request('GET', '/workflows');

        if (is_wp_error($response)) {
            return [];
        }

        $workflows = $response['data'] ?? [];

        if ($active_only) {
            $workflows = array_filter($workflows, fn($w) => ($w['active'] ?? false) === true);
        }

        wp_cache_set($cache_key, $workflows, 'bitcore', self::CACHE_TTL);

        return $workflows;
    }

    /**
     * Get single workflow by ID.
     *
     * @param int $workflow_id The workflow ID.
     * @return array<string, mixed>|null Workflow data or null if not found.
     * @since 1.0.0
     */
    public function get_workflow(int $workflow_id): ?array
    {
        $response = $this->request('GET', "/workflows/{$workflow_id}");

        if (is_wp_error($response)) {
            return null;
        }

        return $response['data'] ?? null;
    }

    /**
     * Execute a workflow.
     *
     * @param int $workflow_id The workflow ID.
     * @param array<string, mixed> $payload Optional payload data.
     * @return array<string, mixed>|WP_Error Execution result.
     * @since 1.0.0
     */
    public function execute(int $workflow_id, array $payload = []): array|WP_Error
    {
        $workflow = $this->get_workflow($workflow_id);

        if (!$workflow) {
            return new WP_Error(
                'workflow_not_found',
                'Workflow not found.',
                ['status' => 404]
            );
        }

        // For webhook-triggered workflows, use webhook endpoint
        if (!empty($workflow['nodes'])) {
            $webhook_node = $this->find_webhook_node($workflow['nodes']);

            if ($webhook_node) {
                return $this->execute_webhook($webhook_node, $payload);
            }
        }

        // Direct execution for manual-trigger workflows
        $response = $this->request('POST', "/workflows/{$workflow_id}/execute", $payload);

        if (is_wp_error($response)) {
            return $response;
        }

        $this->log_execution($workflow_id, $payload, $response);

        return [
            'success' => true,
            'workflow_id' => $workflow_id,
            'execution_id' => $response['executionId'] ?? null,
            'data' => $response['data'] ?? [],
        ];
    }

    /**
     * Execute workflow via webhook.
     *
     * @param array<string, mixed> $webhook_node The webhook node data.
     * @param array<string, mixed> $payload The payload to send.
     * @return array<string, mixed>|WP_Error Execution result.
     * @since 1.0.0
     */
    private function execute_webhook(array $webhook_node, array $payload): array|WP_Error
    {
        $path = $webhook_node['parameters']['path'] ?? '';
        $method = strtoupper($webhook_node['parameters']['httpMethod'] ?? 'POST');

        if (empty($path)) {
            return new WP_Error(
                'invalid_webhook',
                'Webhook path not configured.',
                ['status' => 400]
            );
        }

        $webhook_url = $this->n8n_url . "/webhook/{$path}";

        $args = [
            'method' => $method,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ($method === 'POST' || $method === 'PUT') {
            $args['body'] = wp_json_encode($payload);
        }

        $response = wp_remote_request($webhook_url, $args);

        if (is_wp_error($response)) {
            return new WP_Error(
                'webhook_failed',
                $response->get_error_message(),
                ['status' => 500]
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'success' => $code >= 200 && $code < 300,
            'status_code' => $code,
            'data' => $body,
        ];
    }

    /**
     * Find webhook node in workflow nodes.
     *
     * @param array<int, array<string, mixed>> $nodes Workflow nodes.
     * @return array<string, mixed>|null Webhook node or null.
     * @since 1.0.0
     */
    private function find_webhook_node(array $nodes): ?array
    {
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'n8n-nodes-base.webhook') {
                return $node;
            }
        }

        return null;
    }

    /**
     * Get execution status.
     *
     * @param string $execution_id The execution ID.
     * @return array<string, mixed>|WP_Error Execution status.
     * @since 1.0.0
     */
    public function get_execution_status(string $execution_id): array|WP_Error
    {
        $response = $this->request('GET', "/executions/{$execution_id}");

        if (is_wp_error($response)) {
            return $response;
        }

        return [
            'execution_id' => $execution_id,
            'status' => $response['data']['status'] ?? 'unknown',
            'started_at' => $response['data']['startedAt'] ?? null,
            'finished_at' => $response['data']['finishedAt'] ?? null,
            'data' => $response['data']['data'] ?? [],
        ];
    }

    /**
     * Get recent executions.
     *
     * @param int $limit Maximum number of results.
     * @return array<int, array<string, mixed>> List of executions.
     * @since 1.0.0
     */
    public function get_recent_executions(int $limit = 20): array
    {
        $response = $this->request('GET', '/executions', ['limit' => $limit]);

        if (is_wp_error($response)) {
            return [];
        }

        return $response['data'] ?? [];
    }

    /**
     * Get workflow statistics.
     *
     * @return array<string, mixed> Workflow statistics.
     * @since 1.0.0
     */
    public function get_stats(): array
    {
        $workflows = $this->get_workflows();
        $executions = $this->get_recent_executions(100);

        $active_workflows = count(array_filter($workflows, fn($w) => ($w['active'] ?? false) === true));
        $total_executions = count($executions);
        $successful = count(array_filter($executions, fn($e) => ($e['status'] ?? '') === 'success'));
        $failed = count(array_filter($executions, fn($e) => ($e['status'] ?? '') === 'error'));

        return [
            'total_workflows' => count($workflows),
            'active_workflows' => $active_workflows,
            'total_executions' => $total_executions,
            'successful_executions' => $successful,
            'failed_executions' => $failed,
            'success_rate' => $total_executions > 0 ? round(($successful / $total_executions) * 100, 1) : 0,
        ];
    }

    /**
     * Make API request to n8n.
     *
     * @param string $method HTTP method.
     * @param string $endpoint API endpoint.
     * @param array<string, mixed> $data Request data.
     * @return array<string, mixed>|WP_Error Response or error.
     * @since 1.0.0
     */
    private function request(string $method, string $endpoint, array $data = []): array|WP_Error
    {
        $url = $this->n8n_url . '/api/v1' . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ];

        if (!empty($this->n8n_api_key)) {
            $args['headers']['X-N8N-API-KEY'] = $this->n8n_api_key;
        }

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return new WP_Error(
                'n8n_request_failed',
                $response->get_error_message(),
                ['status' => 500]
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            return new WP_Error(
                'n8n_api_error',
                $body['message'] ?? 'Unknown API error',
                ['status' => $code]
            );
        }

        return [
            'status_code' => $code,
            'data' => $body,
        ];
    }

    /**
     * Log workflow execution.
     *
     * @param int $workflow_id Workflow ID.
     * @param array<string, mixed> $payload Execution payload.
     * @param array<string, mixed> $response Execution response.
     * @since 1.0.0
     */
    private function log_execution(int $workflow_id, array $payload, array $response): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'bitcore_workflow_logs';

        // Check if table exists before logging
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");

        if (!$exists) {
            return;
        }

        $wpdb->insert($table, [
            'workflow_id' => $workflow_id,
            'payload' => wp_json_encode($payload),
            'response' => wp_json_encode($response),
            'status' => isset($response['success']) && $response['success'] ? 'success' : 'error',
            'created_at' => current_time('mysql', true),
        ]);
    }
}
