# Changelog

## 2.6.0

Add lazy-default scalar accessors `stringOrElse()` / `intOrElse()` / `floatOrElse()` /
`boolOrElse()` — the default comes from a callback invoked only when the value cannot be produced —
and `require(array $keys): static`, which asserts a set of keys is present (dot paths allowed),
failing once with all the missing keys via `MissingKeyException::forKeys()` and returning the reader
for chaining.

## 2.5.0

Add dot-notation support to every key-based accessor (`has()`, scalar getters, `enum()`,
`dateTime()`, `nested()`, list accessors): a key like `'user.profile.age'` walks nested arrays.
Backward compatible — a literal key always wins, and a path is only walked when no literal key
matches, so keys that contain dots keep resolving to themselves.

## 2.4.0

Add `dateTime()` / `dateTimeOr()` to read a date/time string into a `DateTimeImmutable`. The value
is read as a string through the cast pipeline, then parsed: without a format any
`DateTimeImmutable`-parsable string is accepted; with a format the input must match it exactly
(surplus characters or parse warnings are rejected). Strict throws `MissingKeyException` /
`TypeMismatchException`; `dateTimeOr()` returns the default.

## 2.3.0

Add `nestedList()` / `nestedListOr()` to read a list of nested arrays as a `list` of readers of the
same kind — the common "array of objects" payload. The strict accessor throws `MissingKeyException`
/ `TypeMismatchException` when the key is absent, the value is not a list, or an element is not an
array; `nestedListOr()` returns `null` in those cases.

## 2.2.0

Add typed scalar list accessors `ints()` / `strings()` / `floats()` / `bools()` and their lenient
`*Or` variants. Each reads a list and produces every element through the same cast pipeline as the
scalar accessors (so the reader's cast mode applies per element). The strict accessors throw
`TypeMismatchException` when an element cannot be produced; the `*Or` accessors return the default
when the key is absent, the value is not a list, or any element cannot be produced.

## 2.1.0

Add `enum()` / `enumOr()` accessors for backed enums. They read the enum's backing scalar through
the same cast pipeline (so the reader's cast mode applies) and resolve the case via
`BackedEnum::tryFrom()`; a value that is missing, not producible as the backing type, or not one of
the enum's cases throws `TypeMismatchException` (strict) or returns the default (`enumOr`).

## 2.0.0

First public release. A tiny, zero-dependency typed reader for untyped arrays, in three flavours
that share the same API and differ only in how a mismatched value is handled:

- **`ArrayReader`** — safe casting (the default): converts numeric strings, `bool`↔`int`,
  `Stringable`, and `'on'`/`'yes'`/`'1'` → `true`, while rejecting anything ambiguous or lossy.
- **`StrictArrayReader`** — exact type only (lossless `int`→`float` aside).
- **`LooseArrayReader`** — PHP's native scalar casts; never rejects a scalar.

Each scalar type has a strict accessor (throws `MissingKeyException` / `TypeMismatchException`) and
a lenient `*Or` accessor (returns a default). `array()`, `list()` and `nested()` validate shape and
never cast. Every exception implements `ArrayReaderException`. Also reads JSON via `fromJson()`.
