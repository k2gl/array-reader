<?php

declare(strict_types=1);

namespace K2gl\ArrayReader;

use K2gl\ArrayReader\Exception\InvalidJsonException;
use K2gl\ArrayReader\Exception\MissingKeyException;
use K2gl\ArrayReader\Exception\TypeMismatchException;

/**
 * Immutable, type-safe reader for a "mixed" array — decoded JSON, CSV rows,
 * configuration arrays, request payloads.
 *
 * Two access styles are offered for each type:
 *  - strict:  `string($key)` returns the value, or throws on a missing key or a
 *             value of the wrong type;
 *  - lenient: `stringOr($key, $default = null)` returns the value, or `$default`
 *             when the key is absent or holds a value of a different type.
 *
 * No implicit coercion is performed. The single exception is int -> float
 * widening in {@see float()} / {@see floatOr()}, which is lossless and matches
 * PHP's own behaviour for `float` parameters.
 */
final class ArrayReader
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function of(array $data): self
    {
        return new self($data);
    }

    /**
     * Decode a JSON object/array into a reader.
     *
     * @throws InvalidJsonException when the string is not valid JSON, or does not decode to an array
     */
    public static function fromJson(string $json): self
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw InvalidJsonException::decodeFailed($e);
        }

        if (!is_array($decoded)) {
            throw InvalidJsonException::notArray($decoded);
        }

        return new self($decoded);
    }

    public function has(string|int $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function string(string|int $key): string
    {
        $value = $this->requireKey($key);

        if (!is_string($value)) {
            throw TypeMismatchException::expected('string', $key, $value);
        }

        return $value;
    }

    /**
     * @return ($default is null ? string|null : string)
     */
    public function stringOr(string|int $key, ?string $default = null): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function int(string|int $key): int
    {
        $value = $this->requireKey($key);

        if (!is_int($value)) {
            throw TypeMismatchException::expected('int', $key, $value);
        }

        return $value;
    }

    /**
     * @return ($default is null ? int|null : int)
     */
    public function intOr(string|int $key, ?int $default = null): ?int
    {
        $value = $this->data[$key] ?? null;

        return is_int($value) ? $value : $default;
    }

    /**
     * Accepts int and float (lossless widening) and returns a float.
     *
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function float(string|int $key): float
    {
        $value = $this->requireKey($key);

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        throw TypeMismatchException::expected('float', $key, $value);
    }

    /**
     * @return ($default is null ? float|null : float)
     */
    public function floatOr(string|int $key, ?float $default = null): ?float
    {
        $value = $this->data[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function bool(string|int $key): bool
    {
        $value = $this->requireKey($key);

        if (!is_bool($value)) {
            throw TypeMismatchException::expected('bool', $key, $value);
        }

        return $value;
    }

    /**
     * @return ($default is null ? bool|null : bool)
     */
    public function boolOr(string|int $key, ?bool $default = null): ?bool
    {
        $value = $this->data[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function array(string|int $key): array
    {
        $value = $this->requireKey($key);

        if (!is_array($value)) {
            throw TypeMismatchException::expected('array', $key, $value);
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed>|null $default
     *
     * @return ($default is null ? array<array-key, mixed>|null : array<array-key, mixed>)
     */
    public function arrayOr(string|int $key, ?array $default = null): ?array
    {
        $value = $this->data[$key] ?? null;

        return is_array($value) ? $value : $default;
    }

    /**
     * A list is an array with sequential integer keys starting at 0.
     *
     * @return list<mixed>
     *
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function list(string|int $key): array
    {
        $value = $this->array($key);

        if (!array_is_list($value)) {
            throw TypeMismatchException::expected('list', $key, $value);
        }

        return $value;
    }

    /**
     * @param list<mixed>|null $default
     *
     * @return ($default is null ? list<mixed>|null : list<mixed>)
     */
    public function listOr(string|int $key, ?array $default = null): ?array
    {
        $value = $this->data[$key] ?? null;

        return is_array($value) && array_is_list($value) ? $value : $default;
    }

    /**
     * Read a nested array as its own reader.
     *
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function nested(string|int $key): self
    {
        return new self($this->array($key));
    }

    public function nestedOr(string|int $key): ?self
    {
        $value = $this->data[$key] ?? null;

        return is_array($value) ? new self($value) : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @throws MissingKeyException
     */
    private function requireKey(string|int $key): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            throw MissingKeyException::forKey($key);
        }

        return $this->data[$key];
    }
}
