<?php
/**
 * Domain Value Objects
 *
 * @package    Bitcore
 * @since      2026.03.01
 * @version    1.3.0
 */

declare(strict_types=1);

namespace BITCORE\Domain;

if (!defined('ABSPATH')) {
    exit;

class ValueObjects {

}

/**
 * Value Object Interface
 */
interface ValueObjectInterface {
    public function equals(self $other): bool;
    public function __toString(): string;
}

/**
 * Abstract Value Object
 */
abstract class AbstractValueObject implements ValueObjectInterface {
    protected mixed $value;

    abstract protected function __construct($value);

    public static function from(mixed $value): static {
        return new static($value);
    }

    public function getValue(): mixed {
        return $this->value;
    }

    public function __toString(): string {
        return (string) $this->value;
    }

    public function equals(ValueObjectInterface $other): bool {
        if (!$other instanceof self) {
            return false;
        }
        return $this->value === $other->value;
    }
}

/**
 * Task ID Value Object
 */
final class TaskId extends AbstractValueObject {
    protected function __construct($value) {
        if (!is_string($value) || empty($value)) {
            throw new \InvalidArgumentException('TaskId must be a non-empty string');
        }
        $this->value = $value;
    }

    public static function generate(): self {
        return new self(uniqid('task_', true));
    }
}

/**
 * Agent ID Value Object
 */
final class AgentId extends AbstractValueObject {
    protected function __construct($value) {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException('AgentId must be a positive integer');
        }
        $this->value = $value;
    }
}

/**
 * Session ID Value Object
 */
final class SessionId extends AbstractValueObject {
    protected function __construct($value) {
        if (!is_string($value) || empty($value)) {
            throw new \InvalidArgumentException('SessionId must be a non-empty string');
        }
        $this->value = $value;
    }

    public static function generate(): self {
        return new self(bin2hex(random_bytes(16)));
    }
}

/**
 * Token Count Value Object
 */
final class TokenCount extends AbstractValueObject {
    protected function __construct($value) {
        if (!is_string($value) || empty($value)) {
            throw new \InvalidArgumentException('TokenCount must be a non-empty string');
        }
        $this->value = $value;
    }



}
}
