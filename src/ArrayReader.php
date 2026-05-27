<?php

declare(strict_types=1);

namespace K2gl\ArrayReader;

/**
 * Reads values with **safe casting**: sensible representations are converted
 * (numeric strings, bool/int, Stringable, `'on'`/`'yes'`/... for booleans) while
 * anything ambiguous or lossy is rejected (throw in strict accessors, default in
 * the `*Or` accessors).
 *
 * This is the right default for string-sourced data — query strings, form input,
 * CSV rows, environment variables.
 *
 * For exact-type-only reads use {@see StrictArrayReader}; for unconditional PHP
 * casts use {@see LooseArrayReader}.
 */
final class ArrayReader extends AbstractArrayReader
{
    protected function castMode(): CastMode
    {
        return CastMode::Safe;
    }
}
