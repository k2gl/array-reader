<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Exception;

use JsonException;
use UnexpectedValueException;

final class InvalidJsonException extends UnexpectedValueException implements ArrayReaderException
{
    public static function decodeFailed(JsonException $previous): self
    {
        return new self(
            sprintf('Failed to decode JSON: %s', $previous->getMessage()),
            (int) $previous->getCode(),
            $previous,
        );
    }

    public static function notArray(mixed $decoded): self
    {
        return new self(sprintf(
            'Expected JSON to decode to an array, got "%s".',
            get_debug_type($decoded),
        ));
    }
}
