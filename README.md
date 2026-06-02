# k2gl/array-reader

[![CI](https://img.shields.io/github/actions/workflow/status/k2gl/array-reader/ci.yml?branch=main&label=CI&logo=github)](https://github.com/k2gl/array-reader/actions/workflows/ci.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/k2gl/array-reader?logo=packagist&logoColor=white)](https://packagist.org/packages/k2gl/array-reader)
[![Total Downloads](https://img.shields.io/packagist/dt/k2gl/array-reader?logo=packagist&logoColor=white)](https://packagist.org/packages/k2gl/array-reader)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-level%209-2a5ea7?logo=php&logoColor=white)](https://phpstan.org)
[![License](https://img.shields.io/packagist/l/k2gl/array-reader?color=yellowgreen)](https://packagist.org/packages/k2gl/array-reader)

Read typed values out of an untyped `array` — query strings, form input, CSV rows, decoded JSON,
config, environment — **without** the `isset(...) && is_string(...) ? ... : null` dance, and
**without** dropping to `mixed` in the eyes of PHPStan / Psalm.

```php
use K2gl\ArrayReader\ArrayReader;

$request = ArrayReader::of($_GET);

$page    = $request->int('page');             // "5"   -> 5   (int)
$active  = $request->bool('active');          // "on"  -> true (bool)
$perPage = $request->intOr('per_page', 20);   // 20 if it is absent or not a valid number
```

Without it you write the same guard for every field and still end up with `mixed`:

```php
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : null;
```

**Why install it:** one tiny, **zero-dependency** class family that turns messy input arrays into
typed values, lets you choose how forgiving the conversion is, fails loudly on missing data, and
keeps static analysis green.

## Install

```bash
composer require k2gl/array-reader
```

Requires PHP 8.1+. No runtime dependencies.

## Pick a reader

There are three readers. They expose the **same methods** and differ only in how they handle a
value whose type doesn't match what you asked for. Here is one input read by each:

```php
$data = ['count' => '42', 'price' => '9.99', 'enabled' => 'yes'];
```

### `ArrayReader` — safe casting (use this by default)

Converts the obvious string/number/bool representations and **rejects anything ambiguous or
lossy**. Ideal for data that arrives as strings: `$_GET`, `$_POST`, CSV rows, environment variables.

```php
use K2gl\ArrayReader\ArrayReader;

$request = ArrayReader::of($data);

$request->int('count');        // 42        — "42" is a whole number
$request->float('price');      // 9.99      — numeric string
$request->bool('enabled');     // true      — "yes"
$request->int('price');        // throws TypeMismatchException — "9.99" is not an integer
$request->intOr('price', 0);   // 0         — the lenient variant returns the default, never throws
```

### `StrictArrayReader` — exact type only

Accepts a value only if it is **already** the requested type (the lone convenience: an `int` may be
read as `float`). Ideal for data you already trust to be well-typed, e.g. a decoded JSON document.

```php
use K2gl\ArrayReader\StrictArrayReader;

$document = StrictArrayReader::of(['count' => 42, 'name' => 'Ada']);

$document->int('count');       // 42
$document->string('name');     // 'Ada'

StrictArrayReader::of(['count' => '42'])->int('count'); // throws — "42" is a string, not an int
```

### `LooseArrayReader` — PHP's native cast

Casts **any scalar** with PHP's own rules and never rejects a scalar — so malformed input passes
through silently. Reach for it only when you explicitly want that behaviour.

```php
use K2gl\ArrayReader\LooseArrayReader;

$loose = LooseArrayReader::of($data);

$loose->int('price');          // 9         — (int) "9.99"
$loose->bool('enabled');       // true
$loose->int('count');          // 42

LooseArrayReader::of(['x' => 'abc'])->int('x'); // 0   — (int) "abc"
$loose->int('missing');        // throws MissingKeyException — a missing key is always an error
```

## Reading values

For every scalar type each reader offers two accessors:

- **strict** `int($key)` — returns the value, or throws: `MissingKeyException` when the key is
  absent, `TypeMismatchException` when the value cannot be produced as the requested type.
- **lenient** `intOr($key, $default = null)` — returns the value, or `$default` when the key is
  absent or the value cannot be produced. Pass a non-null default and the return type is non-null
  too (conditional return types, so PHPStan / Psalm narrow it for you).

```php
$form = ArrayReader::of($_POST);

$email    = $form->string('email');          // string  (throws if missing / not producible)
$nickname = $form->stringOr('nickname');     // ?string (null when absent)
$age      = $form->intOr('age', 0);          // int     (0 when absent / invalid)
$price    = $form->float('price');           // float
$subscribe = $form->boolOr('subscribe', false);
```

## Arrays, lists and nesting

`array()`, `list()` and `nested()` **never cast** — in every reader they only validate shape:

```php
$config = ArrayReader::of($decoded);

$config->array('options');               // array<array-key, mixed>
$config->list('tags');                   // list<mixed> — sequential, 0-based keys
$config->nested('database')->string('host');   // a reader of the same kind over the nested array

$config->arrayOr('options', []);         // lenient variants return the default instead of throwing
$config->listOr('tags', []);
$config->nestedOr('database');           // ?reader
```

## Enums

`enum()` / `enumOr()` read a **backed enum**: the enum's backing scalar is produced through the
same cast pipeline (so the reader's cast mode applies), then resolved with `BackedEnum::tryFrom()`.
A value that is absent, cannot be produced as the backing type, or is not one of the enum's cases
throws (strict) or returns the default (`enumOr`):

```php
enum Suit: string { case Hearts = 'hearts'; case Spades = 'spades'; }

$card = ArrayReader::of($row);

$card->enum('suit', Suit::class);                 // Suit  (throws if missing / not a valid case)
$card->enumOr('suit', Suit::class);               // ?Suit (null when absent / invalid)
$card->enumOr('suit', Suit::class, Suit::Hearts); // Suit  (Suit::Hearts when absent / invalid)
```

The cast mode applies to the backing scalar: with `ArrayReader` (safe) a numeric string `"2"`
resolves an `int`-backed enum, `StrictArrayReader` requires the exact backing type, and
`LooseArrayReader` coerces any scalar. Only backed enums are supported.

## Helpers and JSON

```php
$config = ArrayReader::of($decoded);
$config->has('debug');                   // bool — is the key present? (true even if its value is null)
$config->toArray();                      // the underlying array<array-key, mixed>

$request = ArrayReader::fromJson($body); // decode a JSON object/array straight into a reader
```

## Error handling

Every exception implements `K2gl\ArrayReader\Exception\ArrayReaderException`, so you can catch the
whole family at once:

```php
use K2gl\ArrayReader\ArrayReader;
use K2gl\ArrayReader\Exception\ArrayReaderException;

try {
    $email = ArrayReader::fromJson($body)->string('email');
} catch (ArrayReaderException $e) {
    // MissingKeyException | TypeMismatchException | InvalidJsonException
}
```

## Safe casting reference (`ArrayReader`)

What `ArrayReader` accepts beyond the exact type — anything else throws (strict) or returns the
default (`*Or`):

| Accessor | Also accepts |
| --- | --- |
| `string` | `int`/`float` → string, `bool` → `"1"`/`"0"`, `Stringable` |
| `int` | integer numeric string (`"5"`, `"-3"`), `bool` → `1`/`0` — rejects floats, `"5.5"`, `"abc"` |
| `float` | `int`, numeric string (`"1.5"`, `"2"`) |
| `bool` | `"1"`/`"true"`/`"on"`/`"yes"` → true, `"0"`/`"false"`/`"off"`/`"no"`/`""` → false, `int` `0`/`1` |

## Zero dependencies

`array-reader` has **no runtime dependencies** — its only `require` is `php` itself. Installing it
pulls nothing else into your dependency tree: no transitive packages to audit, no version conflicts
to resolve, and it is safe to drop into any application or library, including ones that must stay
dependency-light.

## License

MIT © Nickolay Harin. See [LICENSE](LICENSE).
