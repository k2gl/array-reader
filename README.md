# k2gl/array-reader

[![CI](https://github.com/k2gl/array-reader/actions/workflows/ci.yml/badge.svg)](https://github.com/k2gl/array-reader/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/k2gl/array-reader/v)](https://packagist.org/packages/k2gl/array-reader)
[![PHP Version Require](https://poser.pugx.org/k2gl/array-reader/require/php)](https://packagist.org/packages/k2gl/array-reader)
[![License](https://poser.pugx.org/k2gl/array-reader/license)](https://packagist.org/packages/k2gl/array-reader)

A tiny, **zero-dependency** typed reader for "mixed" arrays — decoded JSON, CSV rows,
configuration, request payloads. It replaces the `isset(...) && is_string(...) ? ... : null`
boilerplate that every project reinvents, and gives static analysers (PHPStan / Psalm) precise
types instead of `mixed`.

```php
use K2gl\ArrayReader\ArrayReader;

$data = ArrayReader::fromJson($json);          // or ArrayReader::of($array)

$name = $data->string('name');                 // string   (throws if missing / wrong type)
$age  = $data->intOr('age', 0);                // int      (default 0)
$mail = $data->stringOr('email');              // ?string  (null if absent / wrong type)
$tags = $data->list('tags');                   // list<mixed>
$city = $data->nested('address')->string('city');
```

## Why

Data that crosses a boundary — `json_decode($s, true)`, a CSV row, `$_GET`, a YAML/PHP config —
arrives as `array<array-key, mixed>`. Reading a single typed field safely means writing the same
guard over and over:

```php
$name = isset($data['name']) && is_string($data['name']) ? $data['name'] : null;   // again, and again…
```

`ArrayReader` turns that into one expressive call and keeps your code clean under PHPStan level 9+.

## Install

```bash
composer require k2gl/array-reader
```

Requires PHP 8.1+. No runtime dependencies.

## Usage

### Strict access — validate a boundary

Strict readers return the typed value or throw. Use them where missing/ill-typed data is a bug
you want surfaced immediately (e.g. validating an inbound API payload).

```php
$r = ArrayReader::of($payload);

$r->string($key);   // string
$r->int($key);      // int
$r->float($key);    // float  (also accepts int — lossless widening)
$r->bool($key);     // bool
$r->array($key);    // array<array-key, mixed>
$r->list($key);     // list<mixed>   (sequential 0-based array)
$r->nested($key);   // ArrayReader   (wraps a nested array)
```

A missing key throws `MissingKeyException`; a value of the wrong type throws
`TypeMismatchException`. A key that is present but `null` is treated as a type mismatch (it
exists, but it is not a `string`/`int`/…).

### Lenient access — tolerate absent data

Lenient readers return the value, or a default when the key is absent **or** holds a value of a
different type. Use them for imports, optional config, and best-effort parsing.

```php
$r->stringOr($key);              // ?string  (null default)
$r->stringOr($key, 'fallback');  // string
$r->intOr($key, 0);              // int
$r->floatOr($key, 1.0);          // float
$r->boolOr($key, false);         // bool
$r->arrayOr($key, []);           // array<array-key, mixed>
$r->listOr($key, []);            // list<mixed>
$r->nestedOr($key);              // ?ArrayReader
```

The return type narrows automatically: with a non-null default you get a non-null type back
(expressed via conditional return types, so PHPStan/Psalm understand it).

### Helpers

```php
$r->has($key);     // bool — key present? (true even when the value is null)
$r->toArray();     // array<array-key, mixed> — the underlying data
```

### Error handling

Every exception implements `K2gl\ArrayReader\Exception\ArrayReaderException`, so you can catch
them all at once:

```php
use K2gl\ArrayReader\Exception\ArrayReaderException;

try {
    $email = ArrayReader::fromJson($body)->string('email');
} catch (ArrayReaderException $e) {
    // MissingKeyException | TypeMismatchException | InvalidJsonException
}
```

## Design notes

- **No implicit coercion.** A numeric string is *not* an `int`. The only exception is `int → float`
  widening in `float()`/`floatOr()`, which is lossless and matches PHP's own `float` parameter
  behaviour. This keeps reads predictable; if you need coercion, do it explicitly at the call site.
- **Immutable.** A reader never mutates the array it wraps.
- **Precise types.** Return types are as specific as possible (`list<mixed>`, conditional
  nullability) so static analysis stays green without `assert()` noise.

## Comparison

| Package | Purpose | Difference |
| --- | --- | --- |
| `*-typed-array` (steevanb, jumpifbelow, …) | Homogeneous **collections** (an array of one type) | `array-reader` reads heterogeneous data and extracts individual typed fields. |
| `cuyz/valinor`, schema decoders | Map arrays into **objects** against a schema | `array-reader` is a few-kilobyte reader for ad-hoc field access — no mapping, no schema, no dependencies. |
| `symfony/property-access`, dot-access libs | **Path** access into nested structures | `array-reader` focuses on *typed* access with strict/lenient semantics. |

## License

MIT © Nickolay Harin. See [LICENSE](LICENSE).
