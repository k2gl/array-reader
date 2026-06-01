<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Exception;

use UnexpectedValueException;

final class TypeMismatchException extends UnexpectedValueException implements ArrayReaderException
{
    public static function expected(string $expectedType, string|int $key, mixed $actual): self
    {
        return new self(sprintf(
            'Expected "%s" at key "%s", got "%s".',
            $expectedType,
            $key,
            get_debug_type($actual),
        ));
    }
}
