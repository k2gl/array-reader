<?php

declare(strict_types=1);

namespace K2gl\ArrayReader;

/**
 * Reads values with **loose casting**: any scalar is converted with PHP's native
 * cast (`(int)`, `(float)`, `(bool)`, `(string)`), so a scalar is never rejected —
 * `'abc'` becomes `0`, any non-empty string becomes `true`, and so on. Non-scalars
 * (array, object, null) still throw in strict accessors / yield the default.
 *
 * Use this only when you explicitly want PHP's lenient conversion and accept that
 * it hides malformed input. Most callers want {@see ArrayReader} (safe) instead.
 */
final class LooseArrayReader extends AbstractArrayReader
{
    protected function castMode(): CastMode
    {
        return CastMode::Loose;
    }
}
