<?php

declare(strict_types=1);

namespace K2gl\ArrayReader;

/**
 * Internal sentinel returned by the cast helpers when a value cannot be produced,
 * so lenient accessors can fall back to their default without using exceptions for
 * control flow.
 *
 * @internal
 */
enum Miss
{
    case Value;
}
