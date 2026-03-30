<?php
/**
 * Tenant Value Objects
 *
 * Data structures for multi-tenancy tenant management.
 *
 * @package BITCORE\Infrastructure\MultiTenancy
 * @since 1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Infrastructure\MultiTenancy;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Tenant Data Transfer Object
 */
final class Tenant {
    public readonly int $id;
    public readonly string $name;
    public readonly string $domain;
    public readonly ?string $subdomain;
    public readonly array $config;
    public readonly bool $isActive;
    public readonly ?DateTimeImmutable $createdAt;

    public function __construct(
        int $id,
        string $name,
        string $domain,
        ?string $subdomain = null,
        array $config = [],
        bool $isActive = true,
        ?DateTimeImmutable $createdAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->domain = $domain;
        $this->subdomain = $subdomain;
        $this->config = $config;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt;
    }

    public function getPrefix(): string { return "tenant_{$this->id}_"; }
    public function getCacheKey(string $key): string { return $this->getPrefix() . $key; }
    public function getOptionKey(string $key): string { return $this->getPrefix() . $key; }
    public function getTablePrefix(): string { return $this->getPrefix(); }
    
    public static function fromArray(array $data): self {
        return new self(
            $data['id'],
            $data['name'],
            $data['domain'],
            $data['subdomain'] ?? null,
            $data['config'] ?? [],
            ($data['is_active'] ?? $data['isActive'] ?? 1) === 1 || ($data['is_active'] ?? $data['isActive'] ?? true) === true,
            isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null
        );
    }
    
    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'subdomain' => $this->subdomain,
            'config' => $this->config,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
        ];
    }
}
