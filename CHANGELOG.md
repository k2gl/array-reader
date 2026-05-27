# Changelog

## 2.0.0

Casting support, via three sibling readers chosen by how a mismatched value is handled:

- **`ArrayReader`** now **safe-casts** — numeric strings, `bool`↔`int`, `Stringable`, and
  `'on'`/`'yes'`/`'1'` → `true`, while ambiguous or lossy values are rejected. This is the right
  default for HTTP query/form, CSV and environment data.
- **`StrictArrayReader`** (new) keeps the 1.x behaviour: exact type only (lossless `int`→`float`
  aside).
- **`LooseArrayReader`** (new) applies PHP's native scalar casts and never rejects a scalar.

### Backward compatibility

**Breaking:** `ArrayReader` was strict in 1.x and now safe-casts. Replace `ArrayReader` with
`StrictArrayReader` to keep the old behaviour. The strict/lenient method names, the exception
hierarchy, and `array()`/`list()`/`nested()` (which never cast) are unchanged.

## 1.0.0

Initial release: a single strict `ArrayReader` with strict and lenient typed accessors.
