# Changelog

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
