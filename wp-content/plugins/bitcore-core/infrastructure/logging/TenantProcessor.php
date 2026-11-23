<?php

declare(strict_types=1);

/**
 * BITCORE Logging Infrastructure - Tenant Processor
 *
 * @package BITCORE\Infrastructure\Logging
 * @since 1.0.0
 * @version     1.0.0
 */

namespace BITCORE\Infrastructure\Logging;

use BITCORE\Infrastructure\Auth\TenantContext;

/**
 * Tenant Context Processor
 *
 * Adds tenant ID to log records when tenant context is available.
 * Enables multi-tenant log filtering and tenant-specific debugging.
 *
 * @since 1.0.0
 */
class TenantProcessor implements ProcessorInterface
{
    /** @var TenantContext The tenant context provider */
    private TenantContext $tenantContext;

    /**
     * Constructor.
     *
     * @param TenantContext $tenantContext The tenant context instance.
     * @since 1.0.0
     */
    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    /**
     * Process a log record by adding tenant information.
     *
     * @param array<string, mixed> $record The log record to process.
     * @return array<string, mixed> The modified log record with tenant_id.
     * @since 1.0.0
     */
    public function __invoke(array $record): array
    {
        if ($this->tenantContext->hasTenant()) {
            $tenant = $this->tenantContext->getCurrentTenant();
            $record['tenant_id'] = $tenant->getId();
            $record['extra']['tenant'] = [
                'id'   => $tenant->getId(),
                'name' => $tenant->getName(),
            ];
        }

        return $record;
    }
}
