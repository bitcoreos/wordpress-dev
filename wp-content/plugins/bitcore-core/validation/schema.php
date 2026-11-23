<?php
/**
 * Schema Validation (Zod-like)
 *
 * Type-safe validation chains
 * Pattern adapted from MIT-licensed DashClaw validation
 *
 * @package BITCORE\Validation
 * @since 1.0.0
 * @version     1.0.0
 */

declare(strict_types=1);

namespace BITCORE\Validation;

/**
 * Validation Result
 *
 * @since 1.0.0
 */
class ValidationResult {
    /**
     * Is valid.
     *
     * @var bool
     */
    public bool $isValid;

    /**
     * Errors.
     *
     * @var array<string,string>
     */
    public array $errors;

    /**
     * Validated data.
     *
     * @var mixed
     */
    public $data;

    /**
     * Constructor.
     *
     * @param bool $isValid Is valid.
     * @param array<string,string> $errors Errors.
     * @param mixed $data Validated data.
     */
    public function __construct(bool $isValid, array $errors = [], $data = null) {
        $this->isValid = $isValid;
        $this->errors = $errors;
        $this->data = $data;
    }

    /**
     * Get first error.
     *
     * @return string|null First error or null.
     */
    public function getFirstError(): ?string {
        return $this->errors[array_key_first($this->errors)] ?? null;
    }
}

/**
 * Base Schema
 *
 * @since 1.0.0
 */
abstract class BaseSchema {
    /**
     * Validation rules.
     *
     * @var array<int,callable>
     */
    protected array $rules = [];

    /**
     * Is optional.
     *
     * @var bool
     */
    protected bool $isOptional = false;

    /**
     * Default value.
     *
     * @var mixed
     */
    protected $defaultValue = null;

    /**
     * Make optional.
     *
     * @return self
     */
    public function optional(): self {
        $this->isOptional = true;
        return $this;
    }

    /**
     * Set default.
     *
     * @param mixed $value Default value.
     * @return self
     */
    public function default($value): self {
        $this->defaultValue = $value;
        return $this;
    }

    /**
     * Parse value.
     *
     * @param mixed $value Value to parse.
     * @return ValidationResult Validation result.
     */
    abstract public function parse($value): ValidationResult;

    /**
     * Safe parse (return result).
     *
     * @param mixed $value Value to parse.
     * @return ValidationResult Validation result.
     */
    public function safeParse($value): ValidationResult {
        return $this->parse($value);
    }
}

/**
 * String Schema
 *
 * @since 1.0.0
 */
class StringSchema extends BaseSchema {
    /**
     * Min length.
     *
     * @var int|null
     */
    private ?int $minLength = null;

    /**
     * Max length.
     *
     * @var int|null
     */
    private ?int $maxLength = null;

    /**
     * Email validation.
     *
     * @var bool
     */
    private bool $isEmail = false;

    /**
     * URL validation.
     *
     * @var bool
     */
    private bool $isUrl = false;

    /**
     * Regex pattern.
     *
     * @var string|null
     */
    private ?string $pattern = null;

    /**
     * Set min length.
     *
     * @param int $min Min length.
     * @return self
     */
    public function min(int $min): self {
        $this->minLength = $min;
        return $this;
    }

    /**
     * Set max length.
     *
     * @param int $max Max length.
     * @return self
     */
    public function max(int $max): self {
        $this->maxLength = $max;
        return $this;
    }

    /**
     * Validate as email.
     *
     * @return self
     */
    public function email(): self {
        $this->isEmail = true;
        return $this;
    }

    /**
     * Validate as URL.
     *
     * @return self
     */
    public function url(): self {
        $this->isUrl = true;
        return $this;
    }

    /**
     * Set regex pattern.
     *
     * @param string $pattern Regex pattern.
     * @return self
     */
    public function regex(string $pattern): self {
        $this->pattern = $pattern;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function parse($value): ValidationResult {
        if ($value === null || $value === '') {
            if ($this->isOptional) {
                return new ValidationResult(true, [], $this->defaultValue);
            }
            return new ValidationResult(false, ['value' => __('String is required.', 'bitcore-core')]);
        }

        if (!is_string($value)) {
            return new ValidationResult(false, ['value' => __('Value must be a string.', 'bitcore-core')]);
        }

        if ($this->minLength !== null && strlen($value) < $this->minLength) {
            return new ValidationResult(false, ['value' => sprintf(__('String must be at least %d characters.', 'bitcore-core'), $this->minLength)]);
        }

        if ($this->maxLength !== null && strlen($value) > $this->maxLength) {
            return new ValidationResult(false, ['value' => sprintf(__('String must be at most %d characters.', 'bitcore-core'), $this->maxLength)]);
        }

        if ($this->isEmail && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return new ValidationResult(false, ['value' => __('Invalid email format.', 'bitcore-core')]);
        }

        if ($this->isUrl && !filter_var($value, FILTER_VALIDATE_URL)) {
            return new ValidationResult(false, ['value' => __('Invalid URL format.', 'bitcore-core')]);
        }

        if ($this->pattern !== null && !preg_match($this->pattern, $value)) {
            return new ValidationResult(false, ['value' => __('String does not match required pattern.', 'bitcore-core')]);
        }

        return new ValidationResult(true, [], $value);
    }
}

/**
 * Array Schema
 *
 * @since 1.0.0
 */
class ArraySchema extends BaseSchema {
    /**
     * Min items.
     *
     * @var int|null
     */
    private ?int $minItems = null;

    /**
     * Max items.
     *
     * @var int|null
     */
    private ?int $maxItems = null;

    /**
     * Item schema.
     *
     * @var BaseSchema|null
     */
    private ?BaseSchema $itemSchema = null;

    /**
     * Set min items.
     *
     * @param int $min Min items.
     * @return self
     */
    public function min(int $min): self {
        $this->minItems = $min;
        return $this;
    }

    /**
     * Set max items.
     *
     * @param int $max Max items.
     * @return self
     */
    public function max(int $max): self {
        $this->maxItems = $max;
        return $this;
    }

    /**
     * Set item schema.
     *
     * @param BaseSchema $schema Item schema.
     * @return self
     */
    public function items(BaseSchema $schema): self {
        $this->itemSchema = $schema;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function parse($value): ValidationResult {
        if (!is_array($value)) {
            return new ValidationResult(false, ['value' => __('Value must be an array.', 'bitcore-core')]);
        }

        if ($this->minItems !== null && count($value) < $this->minItems) {
            return new ValidationResult(false, ['value' => sprintf(__('Array must have at least %d items.', 'bitcore-core'), $this->minItems)]);
        }

        if ($this->maxItems !== null && count($value) > $this->maxItems) {
            return new ValidationResult(false, ['value' => sprintf(__('Array must have at most %d items.', 'bitcore-core'), $this->maxItems)]);
        }

        if ($this->itemSchema !== null) {
            $validatedItems = [];
            foreach ($value as $key => $item) {
                $result = $this->itemSchema->parse($item);
                if (!$result->isValid) {
                    return new ValidationResult(false, ["[$key]" => $result->getFirstError()]);
                }
                $validatedItems[$key] = $result->data;
            }
            return new ValidationResult(true, [], $validatedItems);
        }

        return new ValidationResult(true, [], $value);
    }
}

/**
 * Object Schema
 *
 * @since 1.0.0
 */
class ObjectSchema extends BaseSchema {
    /**
     * Shape definition.
     *
     * @var array<string,BaseSchema>
     */
    private array $shape = [];

    /**
     * Strict mode.
     *
     * @var bool
     */
    private bool $isStrict = false;

    /**
     * Set shape.
     *
     * @param array<string,BaseSchema> $shape Shape definition.
     * @return self
     */
    public function shape(array $shape): self {
        $this->shape = $shape;
        return $this;
    }

    /**
     * Set strict mode.
     *
     * @return self
     */
    public function strict(): self {
        $this->isStrict = true;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function parse($value): ValidationResult {
        if (!is_array($value) && !is_object($value)) {
            return new ValidationResult(false, ['value' => __('Value must be an object.', 'bitcore-core')]);
        }

        $data = (array) $value;
        $result = [];
        $errors = [];

        foreach ($this->shape as $key => $schema) {
            if (!array_key_exists($key, $data)) {
                if (!$schema instanceof BaseSchema || !$schema->isOptional) {
                    $errors[$key] = __('Field is required.', 'bitcore-core');
                } else {
                    $result[$key] = $schema->defaultValue;
                }
                continue;
            }

            $parseResult = $schema->parse($data[$key]);
            if (!$parseResult->isValid) {
                $errors[$key] = $parseResult->getFirstError();
            } else {
                $result[$key] = $parseResult->data;
            }
        }

        if ($this->isStrict) {
            $extraKeys = array_diff_key($data, $this->shape);
            foreach ($extraKeys as $key => $val) {
                $errors[$key] = __('Unexpected field.', 'bitcore-core');
            }
        }

        if (!empty($errors)) {
            return new ValidationResult(false, $errors);
        }

        return new ValidationResult(true, [], $result);
    }
}

/**
 * Schema Factory
 *
 * @since 1.0.0
 */
class Schema {
    /**
     * Create string schema.
     *
     * @return StringSchema
     */
    public static function string(): StringSchema {
        return new StringSchema();
    }

    /**
     * Create array schema.
     *
     * @return ArraySchema
     */
    public static function array(): ArraySchema {
        return new ArraySchema();
    }

    /**
     * Create object schema.
     *
     * @return ObjectSchema
     */
    public static function object(): ObjectSchema {
        return new ObjectSchema();
    }
}
