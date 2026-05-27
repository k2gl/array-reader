<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Tests;

use K2gl\ArrayReader\ArrayReader;
use K2gl\ArrayReader\Exception\InvalidJsonException;

use function K2gl\PHPUnitFluentAssertions\fact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayReader::class)]
#[CoversClass(InvalidJsonException::class)]
final class FromJsonTest extends TestCase
{
    public function testDecodesJsonObject(): void
    {
        // act
        $reader = ArrayReader::fromJson('{"name":"Ada","age":36}');

        // assert
        fact($reader->string('name'))->is('Ada');
        fact($reader->int('age'))->is(36);
    }

    public function testDecodesTopLevelJsonArray(): void
    {
        // act
        $reader = ArrayReader::fromJson('[1, 2, 3]');

        // assert
        fact($reader->toArray())->is([1, 2, 3]);
    }

    public function testThrowsOnMalformedJson(): void
    {
        $this->expectException(InvalidJsonException::class);

        ArrayReader::fromJson('{not valid');
    }

    public function testThrowsWhenJsonIsNotAnArray(): void
    {
        $this->expectException(InvalidJsonException::class);
        $this->expectExceptionMessage('Expected JSON to decode to an array, got "string".');

        ArrayReader::fromJson('"just a string"');
    }
}
