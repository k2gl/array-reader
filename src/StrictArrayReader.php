<?php

declare(strict_types=1);

namespace K2gl\ArrayReader;

/**
 * Reads values with **no casting**: a value must already be of the requested type
 * (the only allowance is lossless int -> float widening). Anything else throws in
 * a strict accessor, or yields the default in a `*Or` accessor.
 *
 * Use this when ill-typed data is a bug you want surfaced — e.g. validating an
 * already-decoded, well-typed payload.
 */
final class StrictArrayReader extends AbstractArrayReader
{
    protected function castMode(): CastMode
    {
        return CastMode::None;
    }
}
