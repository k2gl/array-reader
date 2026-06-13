<?php

declare(strict_types=1);

namespace K2gl\ArrayReader;

use K2gl\ArrayReader\Exception\InvalidJsonException;
use K2gl\ArrayReader\Exception\MissingKeyException;
use K2gl\ArrayReader\Exception\TypeMismatchException;
use JsonException;
use Stringable;
use BackedEnum;
use ReflectionEnum;
use ReflectionNamedType;
use Closure;
use DateTimeImmutable;
use Exception;

/**
 * Immutable, type-safe reader for a "mixed" array — decoded JSON, CSV rows,
 * configuration arrays, request payloads.
 *
 * Concrete readers differ only in how they convert a value whose type does not
 * match the requested one (see {@see castMode()}): {@see StrictArrayReader} (none),
 * {@see ArrayReader} (safe), {@see LooseArrayReader} (loose).
 *
 * Each scalar type offers two access styles:
 *  - strict:  `string($key)` returns the value, or throws on a missing key or a
 *             value that cannot be produced;
 *  - lenient: `stringOr($key, $default = null)` returns the value, or `$default`
 *             when the key is absent or the value cannot be produced.
 *
 * Backed enums use the same two styles via `enum()` / `enumOr()`: the enum's
 * backing scalar is read through the cast pipeline, then resolved with
 * `BackedEnum::tryFrom()`.
 *
 * `array()`, `list()` and `nested()` never cast — they validate array shape in
 * every mode.
 */
abstract class AbstractArrayReader
{
    /**
     * @param array<array-key, mixed> $data
     */
    final public function __construct(protected readonly array $data) {}

    abstract protected function castMode(): CastMode;

    /**
     * @param array<array-key, mixed> $data
     */
    public static function of(array $data): static
    {
        return new static($data);
    }

    /**
     * Decode a JSON object/array into a reader.
     *
     * @throws InvalidJsonException when the string is not valid JSON, or does not decode to an array
     */
    public static function fromJson(string $json): static
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidJsonException::decodeFailed($e);
        }

        if (! is_array($decoded)) {
            throw InvalidJsonException::notArray($decoded);
        }

        return new static($decoded);
    }

    public function has(string|int $key): bool
    {
        return ! $this->locate($key) instanceof Miss;
    }

    /**
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function string(string|int $key): string
    {
        $value = $this->requireKey($key);
        $cast = $this->asString($value);

        if ($cast instanceof Miss) {
            throw TypeMismatchException::expected('string', $key, $value);
        }

        return $cast;
    }

    /**
     * @return ($default is null ? string|null : string)
     */
    public function stringOr(string|int $key, ?string $default = null): ?string
    {
        $cast = $this->asString($this->value($key));

        return $cast instanceof Miss ? $default : $cast;
    }

    /**
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function int(string|int $key): int
    {
        $value = $this->requireKey($key);
        $cast = $this->asInt($value);

        if ($cast instanceof Miss) {
            throw TypeMismatchException::expected('int', $key, $value);
        }

        return $cast;
    }

    /**
     * @return ($default is null ? int|null : int)
     */
    public function intOr(string|int $key, ?int $default = null): ?int
    {
        $cast = $this->asInt($this->value($key));

        return $cast instanceof Miss ? $default : $cast;
    }

    /**
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function float(string|int $key): float
    {
        $value = $this->requireKey($key);
        $cast = $this->asFloat($value);

        if ($cast instanceof Miss) {
            throw TypeMismatchException::expected('float', $key, $value);
        }

        return $cast;
    }

    /**
     * @return ($default is null ? float|null : float)
     */
    public function floatOr(string|int $key, ?float $default = null): ?float
    {
        $cast = $this->asFloat($this->value($key));

        return $cast instanceof Miss ? $default : $cast;
    }

    /**
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function bool(string|int $key): bool
    {
        $value = $this->requireKey($key);
        $cast = $this->asBool($value);

        if ($cast instanceof Miss) {
            throw TypeMismatchException::expected('bool', $key, $value);
        }

        return $cast;
    }

    /**
     * @return ($default is null ? bool|null : bool)
     */
    public function boolOr(string|int $key, ?bool $default = null): ?bool
    {
        $cast = $this->asBool($this->value($key));

        return $cast instanceof Miss ? $default : $cast;
    }

    /**
     * Read a list of ints: the value at `$key` must be a list, and every element
     * is produced through the cast pipeline (so the reader's mode applies).
     *
     * @return list<int>
     *
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function ints(string|int $key): array
    {
        return $this->castList($key, 'int', fn (mixed $value): int|Miss => $this->asInt($value));
    }

    /**
     * Returns `$default` when the key is absent, the value is not a list, or any
     * element cannot be produced.
     *
     * @param list<int>|null $default
     *
     * @return ($default is null ? list<int>|null : list<int>)
     */
    public function intsOr(string|int $key, ?array $default = null): ?array
    {
        return $this->castListOr($key, fn (mixed $value): int|Miss => $this->asInt($value)) ?? $default;
    }

    /**
     * @return list<string>
     *
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function strings(string|int $key): array
    {
        return $this->castList($key, 'string', fn (mixed $value): string|Miss => $this->asString($value));
    }

    /**
     * @param list<string>|null $default
     *
     * @return ($default is null ? list<string>|null : list<string>)
     */
    public function stringsOr(string|int $key, ?array $default = null): ?array
    {
        return $this->castListOr($key, fn (mixed $value): string|Miss => $this->asString($value)) ?? $default;
    }

    /**
     * @return list<float>
     *
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function floats(string|int $key): array
    {
        return $this->castList($key, 'float', fn (mixed $value): float|Miss => $this->asFloat($value));
    }

    /**
     * @param list<float>|null $default
     *
     * @return ($default is null ? list<float>|null : list<float>)
     */
    public function floatsOr(string|int $key, ?array $default = null): ?array
    {
        return $this->castListOr($key, fn (mixed $value): float|Miss => $this->asFloat($value)) ?? $default;
    }

    /**
     * @return list<bool>
     *
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function bools(string|int $key): array
    {
        return $this->castList($key, 'bool', fn (mixed $value): bool|Miss => $this->asBool($value));
    }

    /**
     * @param list<bool>|null $default
     *
     * @return ($default is null ? list<bool>|null : list<bool>)
     */
    public function boolsOr(string|int $key, ?array $default = null): ?array
    {
        return $this->castListOr($key, fn (mixed $value): bool|Miss => $this->asBool($value)) ?? $default;
    }

    /**
     * Read a backed enum: the enum's backing scalar is produced through the cast
     * pipeline (so the reader's mode applies), then resolved with `tryFrom()`.
     *
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     *
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function enum(string|int $key, string $enum): BackedEnum
    {
        $value = $this->requireKey($key);
        $cast = $this->asEnum($value, $enum);

        if ($cast instanceof Miss) {
            throw TypeMismatchException::expected($enum, $key, $value);
        }

        return $cast;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     * @param T|null          $default
     *
     * @return ($default is null ? T|null : T)
     */
    public function enumOr(string|int $key, string $enum, ?BackedEnum $default = null): ?BackedEnum
    {
        $cast = $this->asEnum($this->value($key), $enum);

        return $cast instanceof Miss ? $default : $cast;
    }

    /**
     * Read a date/time string. The value is read as a string through the cast
     * pipeline, then parsed. Without `$format` any `DateTimeImmutable`-parsable
     * string is accepted (ISO-8601, relative, `@timestamp`); with `$format` the
     * string must match it exactly (no surplus, no parse warnings).
     *
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function dateTime(string|int $key, ?string $format = null): DateTimeImmutable
    {
        $value = $this->requireKey($key);
        $cast = $this->asDateTime($value, $format);

        if ($cast instanceof Miss) {
            throw TypeMismatchException::expected('DateTimeImmutable', $key, $value);
        }

        return $cast;
    }

    /**
     * @return ($default is null ? DateTimeImmutable|null : DateTimeImmutable)
     */
    public function dateTimeOr(string|int $key, ?DateTimeImmutable $default = null, ?string $format = null): ?DateTimeImmutable
    {
        $cast = $this->asDateTime($this->value($key), $format);

        return $cast instanceof Miss ? $default : $cast;
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

        if (! is_array($value)) {
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
        $value = $this->value($key);

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

        if (! array_is_list($value)) {
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
        $value = $this->value($key);

        return is_array($value) && array_is_list($value) ? $value : $default;
    }

    /**
     * Read a nested array as its own reader of the same kind.
     *
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function nested(string|int $key): static
    {
        return new static($this->array($key));
    }

    public function nestedOr(string|int $key): ?static
    {
        $value = $this->value($key);

        return is_array($value) ? new static($value) : null;
    }

    /**
     * Read a list of nested arrays, each wrapped in a reader of the same kind —
     * the common "array of objects" payload (`{"items": [{...}, {...}]}`).
     *
     * @return list<static>
     *
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    public function nestedList(string|int $key): array
    {
        $result = [];

        foreach ($this->list($key) as $index => $element) {
            if (! is_array($element)) {
                throw TypeMismatchException::expected('array', $key . '[' . $index . ']', $element);
            }

            $result[] = new static($element);
        }

        return $result;
    }

    /**
     * Returns `null` when the key is absent, the value is not a list, or any
     * element is not an array.
     *
     * @return list<static>|null
     */
    public function nestedListOr(string|int $key): ?array
    {
        $value = $this->value($key);

        if (! is_array($value) || ! array_is_list($value)) {
            return null;
        }

        $result = [];

        foreach ($value as $element) {
            if (! is_array($element)) {
                return null;
            }

            $result[] = new static($element);
        }

        return $result;
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
        $value = $this->locate($key);

        if ($value instanceof Miss) {
            throw MissingKeyException::forKey($key);
        }

        return $value;
    }

    /**
     * Resolve a key to its value. A literal key always wins (so keys that contain
     * dots keep working); only when it is absent is a string key split on "." and
     * walked as a path into nested arrays. Returns {@see Miss} when nothing matches.
     */
    private function locate(string|int $key): mixed
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        if (! is_string($key) || ! str_contains($key, '.')) {
            return Miss::Value;
        }

        $current = $this->data;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return Miss::Value;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Resolved value for lenient accessors: the value at the key/path, or null
     * when it is absent (matching how a present null is treated).
     */
    private function value(string|int $key): mixed
    {
        $value = $this->locate($key);

        return $value instanceof Miss ? null : $value;
    }

    /**
     * @template T
     *
     * @param Closure(mixed): (T|Miss) $caster
     *
     * @return list<T>
     *
     * @throws MissingKeyException
     * @throws TypeMismatchException
     */
    private function castList(string|int $key, string $type, Closure $caster): array
    {
        $result = [];

        foreach ($this->list($key) as $index => $element) {
            $value = $caster($element);

            if ($value instanceof Miss) {
                throw TypeMismatchException::expected($type, $key . '[' . $index . ']', $element);
            }

            $result[] = $value;
        }

        return $result;
    }

    /**
     * @template T
     *
     * @param Closure(mixed): (T|Miss) $caster
     *
     * @return list<T>|null
     */
    private function castListOr(string|int $key, Closure $caster): ?array
    {
        $value = $this->value($key);

        if (! is_array($value) || ! array_is_list($value)) {
            return null;
        }

        $result = [];

        foreach ($value as $element) {
            $cast = $caster($element);

            if ($cast instanceof Miss) {
                return null;
            }

            $result[] = $cast;
        }

        return $result;
    }

    private function asString(mixed $value): string|Miss
    {
        if (is_string($value)) {
            return $value;
        }

        return match ($this->castMode()) {
            CastMode::None => Miss::Value,
            CastMode::Safe => $this->safeString($value),
            CastMode::Loose => $this->looseString($value),
        };
    }

    private function safeString(mixed $value): string|Miss
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return Miss::Value;
    }

    private function looseString(mixed $value): string|Miss
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return Miss::Value;
    }

    private function asInt(mixed $value): int|Miss
    {
        if (is_int($value)) {
            return $value;
        }

        return match ($this->castMode()) {
            CastMode::None => Miss::Value,
            CastMode::Safe => $this->safeInt($value),
            CastMode::Loose => is_scalar($value) ? (int) $value : Miss::Value,
        };
    }

    private function safeInt(mixed $value): int|Miss
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_string($value)) {
            $int = filter_var($value, FILTER_VALIDATE_INT);

            return $int === false ? Miss::Value : $int;
        }

        return Miss::Value;
    }

    private function asFloat(mixed $value): float|Miss
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        return match ($this->castMode()) {
            CastMode::None => Miss::Value,
            CastMode::Safe => $this->safeFloat($value),
            CastMode::Loose => is_scalar($value) ? (float) $value : Miss::Value,
        };
    }

    private function safeFloat(mixed $value): float|Miss
    {
        if (is_string($value)) {
            $float = filter_var($value, FILTER_VALIDATE_FLOAT);

            if ($float !== false) {
                return $float;
            }
        }

        return Miss::Value;
    }

    private function asBool(mixed $value): bool|Miss
    {
        if (is_bool($value)) {
            return $value;
        }

        return match ($this->castMode()) {
            CastMode::None => Miss::Value,
            CastMode::Safe => $this->safeBool($value),
            CastMode::Loose => is_scalar($value) ? (bool) $value : Miss::Value,
        };
    }

    private function safeBool(mixed $value): bool|Miss
    {
        if (is_int($value)) {
            return match ($value) {
                0 => false,
                1 => true,
                default => Miss::Value,
            };
        }

        if (is_string($value)) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $bool === null ? Miss::Value : $bool;
        }

        return Miss::Value;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|Miss
     */
    private function asEnum(mixed $value, string $enum): BackedEnum|Miss
    {
        $backingType = (new ReflectionEnum($enum))->getBackingType();
        $isIntBacked = $backingType instanceof ReflectionNamedType && $backingType->getName() === 'int';

        $scalar = $isIntBacked ? $this->asInt($value) : $this->asString($value);

        if ($scalar instanceof Miss) {
            return Miss::Value;
        }

        return $enum::tryFrom($scalar) ?? Miss::Value;
    }

    private function asDateTime(mixed $value, ?string $format): DateTimeImmutable|Miss
    {
        $string = $this->asString($value);

        if ($string instanceof Miss || $string === '') {
            return Miss::Value;
        }

        if ($format !== null) {
            $dateTime = DateTimeImmutable::createFromFormat($format, $string);
            $errors = DateTimeImmutable::getLastErrors();

            if ($dateTime === false || ($errors !== false && $errors['warning_count'] + $errors['error_count'] > 0)) {
                return Miss::Value;
            }

            return $dateTime;
        }

        try {
            return new DateTimeImmutable($string);
        } catch (Exception) {
            return Miss::Value;
        }
    }
}
