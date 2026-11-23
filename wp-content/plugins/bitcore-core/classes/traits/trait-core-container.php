<?php
/**
 * Core container and service wiring
 *
 * Extracted verbatim from wp-content/plugins/bitcore-core/classes/core.php (BITCORE 500-LOC refactor); logic unchanged.
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 * @author     coraline
 */

declare(strict_types=1);


if (!defined('ABSPATH')) {
    exit;
}


trait BITCORE_Core_Container_Trait {

    /**
     * Initialize service container (Hyperion pattern with Coraline additions)
     */
    private function init_container(): void {
        require_once BITCORE_PATH . 'classes/container.php';
        $this->container = new BITCORE_Container();
        $this->container->register_core_services($this);

        // === Coraline: Intelligence Services ===
        $this->container->register('encrypted_options', function() {
            require_once BITCORE_PATH . 'classes/encrypted-options.php';
            return new BITCORE_Encrypted_Options();
        });
        
        $this->container->register('model_selector', function() {
            require_once BITCORE_PATH . 'classes/model-selector.php';
            return new BITCORE_Model_Selector();
        });

        // === Hyperion: Infrastructure Services ===
        // NOTE: Six infrastructure files are MISSING - commented out until built.
        // Missing: Auth/AuthManager.php, Auth/CapabilityManager.php, MultiTenancy/TenantResolver.php,
        //          Cache/CacheManager.php, Logging/Logger.php, RateLimit/RateLimitManager.php
        // $this->container->register('auth.manager', function() { return new \BITCORE\Infrastructure\Auth\AuthManager(); });
        // $this->container->register('auth.capability', function() { return new \BITCORE\Infrastructure\Auth\CapabilityManager(); });
        // $this->container->register('tenant.resolver', function() { return new \BITCORE\Infrastructure\MultiTenancy\TenantResolver(); });
        // $this->container->register('cache.manager', function() { return new \BITCORE\Infrastructure\Cache\CacheManager(); });
        // QueueWorker requires Logger which is missing — commented out until built.
        // require_once BITCORE_PATH . 'infrastructure/queue/QueueWorker.php';
        // $this->container->register('queue.worker', function($c) {
        //     require_once BITCORE_PATH . 'infrastructure/queue/JobRepository.php';
        //     return new \BITCORE\Infrastructure\Queue\QueueWorker(
        //         $c->get('job.repository'),
        //         $c->get('logger')
        //     );
        // });
        // $this->container->register('logger', function() { return new \BITCORE\Infrastructure\Logging\Logger(); });
        // $this->container->register('ratelimit.manager', function() { return new \BITCORE\Infrastructure\RateLimit\RateLimitManager(); });
        require_once BITCORE_PATH . 'infrastructure/webhooks/WebhookManager.php';
        // DISABLED: WebhookManager requires 4 constructor args (repository, httpClient, logger, signatureVerifier)
        // which are not registered in the container. Re-enable after wiring dependencies.
        // $this->container->register('webhooks.manager', function() { return new \BITCORE\Infrastructure\Webhooks\WebhookManager(); });

        // === Hyperion: Wave 4 Security Services ===
        require_once BITCORE_PATH . 'infrastructure/security/middleware-pipeline.php';
        $this->container->register('security.middleware.pipeline', function() { return new \BITCORE\Infrastructure\Security\MiddlewarePipeline(); });

        require_once BITCORE_PATH . 'infrastructure/security/audit-logger.php';
        $this->container->register('security.audit.logger', function() { return new \BITCORE\Infrastructure\Security\AuditLogger(); });

        require_once BITCORE_PATH . 'infrastructure/security/hardening.php';
        $this->container->register('security.hardening.manager', function() { return new \BITCORE\Infrastructure\Security\HardeningManager(); });

        // === Hyperion: A2A Swarm Services ===
        require_once BITCORE_PATH . 'infrastructure/swarm/a2a-dispatcher.php';
        $this->container->register('swarm.dispatcher', function() { return new \BITCORE\Infrastructure\Swarm\A2ADispatcher(); });

        require_once BITCORE_PATH . 'infrastructure/swarm/swarm-coordinator.php';
        $this->container->register('swarm.coordinator', function() { return new \BITCORE\Infrastructure\Swarm\SwarmCoordinator(); });

        require_once BITCORE_PATH . 'infrastructure/swarm/health-monitor.php';
        $this->container->register('health.monitor', function() { return new \BITCORE\Infrastructure\Swarm\HealthMonitor(); });

        // x402 Payment Verification
        require_once BITCORE_PATH . 'infrastructure/class-x402.php';
        require_once BITCORE_PATH . 'infrastructure/class-x402-facilitator.php';
        $this->container->register('x402.verifier', function() {
            return new \BITCORE\Infrastructure\X402_Verifier();
        });
        $this->container->register('x402.facilitator', function() {
            return new \BITCORE\Infrastructure\X402_Facilitator_Client();
        });

        // Initialize Wave 4 Services
        add_action('bitcore_init', [$this, 'init_wave4_services']);
    }

    /**
     * Initialize Wave 4 security and swarm services
     */
    public function init_wave4_services(): void {
        // Apply hardening
        $hardening = $this->container->get('security.hardening.manager');
        if ($hardening) {
            $hardening->apply();
        }

        // Initialize health monitoring
        $monitor = $this->container->get('health.monitor');
        if ($monitor && class_exists('\BITCORE\Infrastructure\Swarm\DatabaseHealthChecker')) {
            $monitor->registerChecker(new \BITCORE\Infrastructure\Swarm\DatabaseHealthChecker());
            $cacheManager = $this->container->get('cache.manager');
            if ($cacheManager) {
                $monitor->registerChecker(new \BITCORE\Infrastructure\Swarm\CacheHealthChecker($cacheManager));
            }
        }
    }


}
