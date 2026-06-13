<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Exception;

use OutOfBoundsException;

final class MissingKeyException extends OutOfBoundsException implements ArrayReaderException
{
    public static function forKey(string|int $key): self
    {
        return new self(sprintf('Missing required key "%s".', $key));
    }

    /**
     * @param non-empty-list<string|int> $keys
     */
    public static function forKeys(array $keys): self
    {
        $quoted = array_map(static fn (string|int $key): string => sprintf('"%s"', $key), $keys);

        return new self(sprintf('Missing required keys: %s.', implode(', ', $quoted)));
    }
}
