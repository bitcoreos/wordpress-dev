<?php
/**
use BITCORE\Infrastructure\CLI\BitcoreCommand;
use BITCORE\Infrastructure\Cache\CacheManager;
use BITCORE\Infrastructure\Logging\Logger;
use BITCORE\Infrastructure\MultiTenancy\Tenant;
use BITCORE\Infrastructure\MultiTenancy\TenantRepository;
use BITCORE\Infrastructure\Queue\QueueWorker;
use BITCORE\Infrastructure\Queue\WorkerConfig;
 * System and diagnostic WP-CLI commands for Bitcore
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


require_once __DIR__ . '/trait-system-helpers.php';
require_once __DIR__ . '/trait-system-health.php';
require_once __DIR__ . '/trait-system-diagnostics.php';

class Bitcore_System_Commands {
    use Bitcore_System_Helpers_Trait;
    use Bitcore_System_Health_Trait;
    use Bitcore_System_Diagnostics_Trait;


    private $logger;


    public function __construct() {
        $this->logger = new BITCORE_Infrastructure_Logging_Logger();
    }

}
