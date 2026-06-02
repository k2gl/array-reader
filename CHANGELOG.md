# Changelog

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
