<?php
/**
 * MiddlewareInterface implementation
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     hyperion
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Middleware Interface Contract
 *
 * Defines the contract for HTTP middleware implementations in the BITCORE infrastructure.
 *
 * @package BITCORE\Infrastructure\Security\Contracts
 * @since 1.0.0
 */

namespace BITCORE\Infrastructure\Security\Contracts;

/**
 * Interface for HTTP middleware implementations.
 *
 * Middleware provides a mechanism to filter HTTP requests and responses
 * through a pipeline of reusable components.
 *
 * @since 1.0.0
 */
interface MiddlewareInterface {

    /**
     * Process an incoming request and pass it to the next middleware.
     *
     * @param array    $request The request data to process.
     * @param callable $next    The next middleware in the pipeline.
     * @return array The processed request data.
     * @since 1.0.0
     */
    public function handle(array $request, callable $next): array;
}
