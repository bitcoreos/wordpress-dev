<?php
/**
 * Artifact Schema - JSON Schema definitions
 *
 * @package BITCORE_Core\Artifacts
 * @since 1.4.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class BITCORE_Artifact_Schema {

    private ?array $schema = null;

    public function get_schema(): array {
        if ($this->schema !== null) {
            return $this->schema;
        }

        $this->schema = [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'bitcore-artifact',
            'type' => 'object',
            'properties' => [
                'id' => [
                    'description' => __('Unique identifier for the artifact.', 'bitcore'),
                    'type' => 'integer',
                    'readonly' => true,
                ],
                'name' => [
                    'description' => __('Artifact name/identifier.', 'bitcore'),
                    'type' => 'string',
                    'required' => true,
                    'pattern' => '^[a-z0-9_-]+$',
                ],
                'type' => [
                    'description' => __('Artifact type classification.', 'bitcore'),
                    'type' => 'string',
                    'enum' => ['circumpunct', 'decision-framework', 'cognitive-bias', 'logic-pattern', 'heuristic', 'ontology', 'generic'],
                    'default' => 'generic',
                ],
                'content' => [
                    'description' => __('Artifact content (JSON or text).', 'bitcore'),
                    'type' => ['object', 'string'],
                    'required' => true,
                ],
                'schema_def' => [
                    'description' => __('JSON Schema for content validation.', 'bitcore'),
                    'type' => 'object',
                ],
                'metadata' => [
                    'description' => __('Additional metadata.', 'bitcore'),
                    'type' => 'object',
                    'properties' => [
                        'description' => ['type' => 'string'],
                        'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'version' => ['type' => 'string'],
                        'author' => ['type' => 'string'],
                    ],
                ],
                'tenant_id' => [
                    'description' => __('Tenant ID for multi-tenant isolation.', 'bitcore'),
                    'type' => 'integer',
                ],
                'created_at' => [
                    'description' => __('Creation timestamp.', 'bitcore'),
                    'type' => 'string',
                    'format' => 'date-time',
                    'readonly' => true,
                ],
                'updated_at' => [
                    'description' => __('Last update timestamp.', 'bitcore'),
                    'type' => 'string',
                    'format' => 'date-time',
                    'readonly' => true,
                ],
            ],
        ];

        return $this->schema;
    }

    public function validate(array $data): array {
        $schema = $this->get_schema();
        $errors = [];

        // Required fields
        if (empty($data['name'])) {
            $errors[] = __('name is required', 'bitcore');
        } elseif (!preg_match('/^[a-z0-9_-]+$/', $data['name'])) {
            $errors[] = __('name must contain only lowercase letters, numbers, underscores, and hyphens', 'bitcore');
        }

        if (empty($data['content'])) {
            $errors[] = __('content is required', 'bitcore');
        }

        // Type validation
        $valid_types = ['circumpunct', 'decision-framework', 'cognitive-bias', 'logic-pattern', 'heuristic', 'ontology', 'generic'];
        if (!empty($data['type']) && !in_array($data['type'], $valid_types, true)) {
            $errors[] = sprintf(__('type must be one of: %s', 'bitcore'), implode(', ', $valid_types));
        }

        return $errors;
    }

    public function get_endpoint_args(string $method): array {
        $schema = $this->get_schema();
        $props = $schema['properties'];

        $args = [];

        if ($method === 'POST' || $method === 'PUT') {
            $args['name'] = [
                'required' => $method === 'POST',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ];
            $args['type'] = [
                'required' => false,
                'type' => 'string',
                'default' => 'generic',
                'sanitize_callback' => 'sanitize_text_field',
            ];
            $args['content'] = [
                'required' => $method === 'POST',
                'type' => 'object',
            ];
            $args['schema_def'] = [
                'required' => false,
                'type' => 'object',
            ];
            $args['metadata'] = [
                'required' => false,
                'type' => 'object',
            ];
        }

        return $args;
    }
}
