# k2gl/array-reader

[![CI](https://github.com/k2gl/array-reader/actions/workflows/ci.yml/badge.svg)](https://github.com/k2gl/array-reader/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/k2gl/array-reader/v)](https://packagist.org/packages/k2gl/array-reader)
[![PHP Version Require](https://poser.pugx.org/k2gl/array-reader/require/php)](https://packagist.org/packages/k2gl/array-reader)
[![License](https://poser.pugx.org/k2gl/array-reader/license)](https://packagist.org/packages/k2gl/array-reader)

A tiny, **zero-dependency** typed reader for "mixed" arrays — query strings, form input, CSV rows,
decoded JSON, configuration, environment. Read typed values without the
`isset(...) && is_string(...) ? ... : null` boilerplate, with the casting strictness you choose,
and keep static analysers (PHPStan / Psalm) following your types.

```php
use K2gl\ArrayReader\ArrayReader;

$query = ArrayReader::of($_GET);          // safe casting — the default

$page   = $query->int('page');            // '5'   -> 5
$active  = $query->bool('active');        // 'on'  -> true
$ratio  = $query->floatOr('ratio', 1.0);  // '1.5' -> 1.5, or 1.0 if absent/invalid
```

## Three flavours

Pick a class by how a value that doesn't match the requested type should be handled. The API is
otherwise identical.

| Class | Behaviour | `int('x')` on `'5'` | on `'abc'` |
| --- | --- | --- | --- |
| `ArrayReader` | **safe cast** (default) | `5` | throws / default |
| `StrictArrayReader` | no casting — exact type only | throws | throws |
| `LooseArrayReader` | PHP cast for any scalar | `5` | `0` |

- **`ArrayReader`** converts sensible representations (numeric strings, `bool`↔`int`, `Stringable`,
  `'on'`/`'yes'`/`'1'` → `true`) and rejects anything ambiguous or lossy. Best for string-sourced
  data — HTTP, CSV, environment variables.
- **`StrictArrayReader`** requires the value to already be of the requested type (lossless
  `int`→`float` aside). Use it to validate an already-decoded, well-typed payload.
- **`LooseArrayReader`** applies PHP's native cast to any scalar (`'abc'` → `0`, any non-empty
  string except `'0'` → `true`) and never gives up on a scalar. Use only when you want exactly that.

## Install

```bash
composer require k2gl/array-reader
```

Requires PHP 8.1+. No runtime dependencies.

## Usage

### Strict and lenient access

Every scalar type has two accessors on all three classes:

- **strict** — `int($key)` returns the value, or throws `MissingKeyException` (key absent) or
  `TypeMismatchException` (value can't be produced as the requested type);
- **lenient** — `intOr($key, $default = null)` returns the value, or `$default` when the key is
  absent or the value can't be produced. With a non-null default you get a non-null type back
  (expressed via conditional return types, so PHPStan/Psalm understand it).

```php
$r->string($key);   $r->stringOr($key, 'fallback');
$r->int($key);      $r->intOr($key, 0);
$r->float($key);    $r->floatOr($key, 0.0);
$r->bool($key);     $r->boolOr($key, false);
```

### Arrays, lists and nesting

`array()`, `list()` and `nested()` **never cast** in any flavour — they validate shape:

```php
$r->array($key);    // array<array-key, mixed>
$r->list($key);     // list<mixed> (sequential, 0-based)
$r->nested($key);   // a reader of the same flavour over the nested array
$r->arrayOr($key, []);  $r->listOr($key, []);  $r->nestedOr($key); // lenient variants
```

### Helpers

```php
$r->has($key);     // bool — key present? (true even when the value is null)
$r->toArray();     // array<array-key, mixed> — the underlying data
```

### Error handling

Every exception implements `K2gl\ArrayReader\Exception\ArrayReaderException`, so you can catch them
all at once:

```php
use K2gl\ArrayReader\Exception\ArrayReaderException;

try {
    $email = ArrayReader::fromJson($body)->string('email');
} catch (ArrayReaderException $e) {
    // MissingKeyException | TypeMismatchException | InvalidJsonException
}
```

## Safe casting reference (`ArrayReader`)

| Accessor | Accepts (besides the exact type) |
| --- | --- |
| `string` | `int`/`float` → string, `bool` → `'1'`/`'0'`, `Stringable` |
| `int` | integer numeric string (`'5'`, `'-3'`), `bool` → `1`/`0` (rejects floats, `'5.5'`, `'abc'`) |
| `float` | `int`, numeric string (`'1.5'`, `'2'`) |
| `bool` | `'1'`/`'true'`/`'on'`/`'yes'` → true, `'0'`/`'false'`/`'off'`/`'no'`/`''` → false, `int` `0`/`1` |

Anything not listed is a `TypeMismatchException` (strict accessor) or the default (`*Or` accessor).

## Upgrading from 1.x

In 1.0 the single `ArrayReader` was strict ("no implicit coercion"). In 2.0 `ArrayReader`
**safe-casts** by default; the old exact-type behaviour now lives in **`StrictArrayReader`**. If you
relied on strict reads, replace `ArrayReader` with `StrictArrayReader` — the method names, exceptions
and `array()`/`list()`/`nested()` semantics are unchanged.

## Zero dependencies

`array-reader` has **no runtime dependencies** — its only `require` is `php` itself. Installing it
pulls nothing else into your dependency tree: no transitive packages to audit, no version conflicts
to resolve, and it is safe to drop into any application or library, including ones that must stay
dependency-light.

## License

MIT © Nickolay Harin. See [LICENSE](LICENSE).
