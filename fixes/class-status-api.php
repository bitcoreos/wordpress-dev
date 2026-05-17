<?php
declare(strict_types=1);

class BITCORE_Status_API {
    private string $namespace = 'bitcore/v1';

    public function register_routes(): void {
        register_rest_route($this->namespace, '/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_status'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($this->namespace, '/queue', [
            'methods' => 'GET',
            'callback' => [$this, 'get_queue'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_status(): array {
        $uptime = 0;
        if (file_exists('/proc/uptime')) {
            $parts = explode(' ', file_get_contents('/proc/uptime'));
            $uptime = (int) floatval($parts[0]);
        }
        return [
            'version' => get_bloginfo('version'),
            'uptime' => $uptime,
            'memory' => round(memory_get_usage(true) / 1048576, 1) . 'MB / ' . ini_get('memory_limit'),
            'php' => PHP_VERSION,
        ];
    }

    public function get_queue(): array {
        return [
            'queues' => [
                ['name' => 'email', 'pending' => 0],
            ],
        ];
    }
}
