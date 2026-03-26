<?php
/**
 * ValueObjectInterface implementation
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
 * Domain Value Objects
 *
 * Immutable objects with validation
 * Pattern extracted from mcp-adapter domain layer
 *
 * @package BITCORE\Domain
 * @since 1.0.0
 */

namespace BITCORE\Domain;

/**
 * Value Object Interface
 *
 * @since 1.0.0
 */
interface ValueObjectInterface {
    /**
     * Check if two value objects are equal.
     *
     * @param self $other Other value object.
     * @return bool True if equal.
     */
    public function equals(self $other): bool;

    /**
     * Get string representation.
     *
     * @return string String representation.
     */
    public function __toString(): string;
}

/**
 * Abstract Value Object Base
 *
 * @since 1.0.0
 */
abstract class AbstractValueObject implements ValueObjectInterface {
    /**
     * Validate and construct value.
     *
     * @param mixed $value Raw value.
     * @throws \InvalidArgumentException If invalid.
     */
    abstract protected function __construct($value);

    /**
     * Factory method to create value object.
     *
     * @param mixed $value Raw value.
     * @return static Created value object.
     * @throws \InvalidArgumentException If invalid.
     */
    public static function create($value): self {
        return new static($value);
    }

    /**
     * {@inheritDoc}
     */
    public function equals(ValueObjectInterface $other): bool {
        return $this->__toString() === $other->__toString();
    }
}

/**
 * Task ID Value Object
 *
 * @since 1.0.0
 */
final class TaskId extends AbstractValueObject {
    /**
     * Task ID value.
     *
     * @var string
     */
    private string $value;

    /**
     * Constructor.
     *
     * @param string $value Task ID.
     * @throws \InvalidArgumentException If invalid.
     */
    protected function __construct($value) {
        if (empty($value)) {
            throw new \InvalidArgumentException(__('Task ID cannot be empty.', 'bitcore-core'));
        }
        if (!preg_match('/^[a-z0-9_-]+$/i', $value)) {
            throw new \InvalidArgumentException(__('Task ID can only contain letters, numbers, hyphens, and underscores.', 'bitcore-core'));
        }
        $this->value = strtolower($value);
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string {
        return $this->value;
    }

    /**
     * Get value.
     *
     * @return string Task ID value.
     */
    public function getValue(): string {
        return $this->value;
    }
}

/**
 * User ID Value Object
 *
 * @since 1.0.0
 */
final class UserId extends AbstractValueObject {
    /**
     * User ID value.
     *
     * @var int
     */
    private int $value;

    /**
     * Constructor.
     *
     * @param int $value User ID.
     * @throws \InvalidArgumentException If invalid.
     */
    protected function __construct($value) {
        if ($value < 1) {
            throw new \InvalidArgumentException(__('User ID must be positive.', 'bitcore-core'));
        }
        $this->value = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string {
        return (string) $this->value;
    }

    /**
     * Get value.
     *
     * @return int User ID value.
     */
    public function getValue(): int {
        return $this->value;
    }
}

/**
 * Connector URL Value Object
 *
 * @since 1.0.0
 */
final class ConnectorUrl extends AbstractValueObject {
    /**
     * URL value.
     *
     * @var string
     */
    private string $value;

    /**
     * Constructor.
     *
     * @param string $value URL.
     * @throws \InvalidArgumentException If invalid.
     */
    protected function __construct($value) {
        if (empty($value)) {
            throw new \InvalidArgumentException(__('URL cannot be empty.', 'bitcore-core'));
        }
        
        $filtered = filter_var($value, FILTER_VALIDATE_URL);
        if ($filtered === false) {
            throw new \InvalidArgumentException(__('Invalid URL format.', 'bitcore-core'));
        }
        
        $this->value = $filtered;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string {
        return $this->value;
    }

    /**
     * Get value.
     *
     * @return string URL value.
     */
    public function getValue(): string {
        return $this->value;
    }

    /**
     * Get scheme.
     *
     * @return string URL scheme.
     */
    public function getScheme(): string {
        return parse_url($this->value, PHP_URL_SCHEME) ?: '';
    }

    /**
     * Get host.
     *
     * @return string URL host.
     */
    public function getHost(): string {
        return parse_url($this->value, PHP_URL_HOST) ?: '';
    }
}

/**
 * Email Address Value Object
 *
 * @since 1.0.0
 */
final class EmailAddress extends AbstractValueObject {
    /**
     * Email value.
     *
     * @var string
     */
    private string $value;

    /**
     * Constructor.
     *
     * @param string $value Email address.
     * @throws \InvalidArgumentException If invalid.
     */
    protected function __construct($value) {
        if (empty($value)) {
            throw new \InvalidArgumentException(__('Email cannot be empty.', 'bitcore-core'));
        }
        
        $filtered = filter_var($value, FILTER_VALIDATE_EMAIL);
        if ($filtered === false) {
            throw new \InvalidArgumentException(__('Invalid email format.', 'bitcore-core'));
        }
        
        $this->value = strtolower($filtered);
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string {
        return $this->value;
    }

    /**
     * Get value.
     *
     * @return string Email value.
     */
    public function getValue(): string {
        return $this->value;
    }

    /**
     * Get domain.
     *
     * @return string Email domain.
     */
    public function getDomain(): string {
        return substr($this->value, strpos($this->value, '@') + 1);
    }

    /**
     * Get local part.
     *
     * @return string Local part.
     */
    public function getLocalPart(): string {
        return substr($this->value, 0, strpos($this->value, '@'));
    }
}
