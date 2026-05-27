<?php

declare(strict_types=1);

namespace K2gl\ArrayReader;

/**
 * Selects how a reader converts a value whose type does not match the requested one.
 */
enum CastMode
{
    /** No conversion: only the exact type is accepted (int -> float widening aside). */
    case None;

    /** Convert sensible representations (numeric strings, bool/int, Stringable); reject the rest. */
    case Safe;

    /** Apply PHP's native cast to any scalar; never give up on a scalar. */
    case Loose;
}
