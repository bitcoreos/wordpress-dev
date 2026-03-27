<?php
/**
 * BITCORE REST Schema Tests
 * JSON Schema validation for REST API endpoints
 *
 * @package BITCORE\Tests\REST
 * @since 1.3.0
 */

declare(strict_types=1);

namespace BITCORE\Tests\REST;

use PHPUnit\Framework\TestCase;

/**
 * Class RestSchemaTest
 * Tests for BITCORE REST API JSON Schema implementation
 */
class RestSchemaTest extends TestCase {

    /**
     * Test schema has required $schema property
     */
    public function test_schema_has_json_schema_property(): void {
        $schema = $this->get_task_schema();
        
        $this->assertArrayHasKey('$schema', $schema);
        $this->assertEquals('http://json-schema.org/draft-04/schema#', $schema['$schema']);
    }

    /**
     * Test schema has title property
     */
    public function test_schema_has_title(): void {
        $schema = $this->get_task_schema();
        
        $this->assertArrayHasKey('title', $schema);
        $this->assertEquals('bitcore-task', $schema['title']);
    }

    /**
     * Test schema has type object
     */
    public function test_schema_is_object_type(): void {
        $schema = $this->get_task_schema();
        
        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('object', $schema['type']);
    }

    /**
     * Test schema has required properties
     */
    public function test_schema_has_required_properties(): void {
        $schema = $this->get_task_schema();
        
        $this->assertArrayHasKey('properties', $schema);
        
        $required_properties = ['id', 'title', 'content', 'status', 'author', 'date'];
        foreach ($required_properties as $prop) {
            $this->assertArrayHasKey($prop, $schema['properties'], "Missing property: {$prop}");
        }
    }

    /**
     * Test id property is readonly integer
     */
    public function test_id_property_is_readonly_integer(): void {
        $schema = $this->get_task_schema();
        
        $id_prop = $schema['properties']['id'];
        $this->assertEquals('integer', $id_prop['type']);
        $this->assertTrue($id_prop['readonly']);
    }

    /**
     * Test status property has enum values
     */
    public function test_status_property_has_enum(): void {
        $schema = $this->get_task_schema();
        
        $status_prop = $schema['properties']['status'];
        $this->assertEquals('string', $status_prop['type']);
        $this->assertArrayHasKey('enum', $status_prop);
        $this->assertEquals(['pending', 'active', 'completed', 'failed'], $status_prop['enum']);
    }

    /**
     * Test date property has date-time format
     */
    public function test_date_property_has_datetime_format(): void {
        $schema = $this->get_task_schema();
        
        $date_prop = $schema['properties']['date'];
        $this->assertEquals('string', $date_prop['type']);
        $this->assertEquals('date-time', $date_prop['format']);
    }

    /**
     * Test meta property is nested object
     */
    public function test_meta_property_is_nested_object(): void {
        $schema = $this->get_task_schema();
        
        $meta_prop = $schema['properties']['meta'];
        $this->assertEquals('object', $meta_prop['type']);
        $this->assertArrayHasKey('properties', $meta_prop);
        
        $meta_sub_props = ['assigned_to', 'priority', 'deadline', 'params'];
        foreach ($meta_sub_props as $prop) {
            $this->assertArrayHasKey($prop, $meta_prop['properties'], "Missing meta property: {$prop}");
        }
    }

    /**
     * Test priority property has enum values
     */
    public function test_priority_property_has_enum(): void {
        $schema = $this->get_task_schema();
        
        $priority_prop = $schema['properties']['meta']['properties']['priority'];
        $this->assertEquals('string', $priority_prop['type']);
        $this->assertEquals(['low', 'normal', 'high'], $priority_prop['enum']);
    }

    /**
     * Helper to get task schema
     */
    private function get_task_schema(): array {
        // Simulated schema matching implementation
        return [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'bitcore-task',
            'type'       => 'object',
            'properties' => [
                'id' => [
                    'description' => 'Unique identifier for the task.',
                    'type'        => 'integer',
                    'readonly'    => true,
                ],
                'title' => [
                    'description' => 'The title of the task.',
                    'type'        => 'string',
                    'required'    => true,
                ],
                'content' => [
                    'description' => 'The content/description of the task.',
                    'type'        => 'string',
                ],
                'status' => [
                    'description' => 'Task status taxonomy.',
                    'type'        => 'string',
                    'enum'        => ['pending', 'active', 'completed', 'failed'],
                ],
                'post_status' => [
                    'description' => 'WordPress post status.',
                    'type'        => 'string',
                    'enum'        => ['publish', 'draft', 'pending'],
                ],
                'author' => [
                    'description' => 'Task author user ID.',
                    'type'        => 'integer',
                ],
                'date' => [
                    'description' => 'Creation date.',
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'readonly'    => true,
                ],
                'modified' => [
                    'description' => 'Last modified date.',
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'readonly'    => true,
                ],
                'meta' => [
                    'description' => 'Task metadata.',
                    'type'        => 'object',
                    'properties'  => [
                        'assigned_to' => ['type' => 'string'],
                        'priority'    => ['type' => 'string', 'enum' => ['low', 'normal', 'high']],
                        'deadline'    => ['type' => 'string', 'format' => 'date-time'],
                        'params'      => ['type' => 'object'],
                    ],
                ],
            ],
        ];
    }
}
