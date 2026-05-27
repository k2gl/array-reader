<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Exception;

final class MissingKeyException extends \OutOfBoundsException implements ArrayReaderException
{
    public static function forKey(string|int $key): self
    {
        return new self(sprintf('Missing required key "%s".', $key));
    }
}
