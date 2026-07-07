<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Tests;

use K2gl\ArrayReader\AbstractArrayReader;
use K2gl\ArrayReader\ArrayReader;
use K2gl\ArrayReader\Exception\InvalidJsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(AbstractArrayReader::class)]
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
        // act + assert
        fact(static fn () => ArrayReader::fromJson('{not valid'))->throws(InvalidJsonException::class);
    }

    public function testThrowsWhenJsonIsNotAnArray(): void
    {
        // act + assert
        fact(static fn () => ArrayReader::fromJson('"just a string"'))
            ->throws(InvalidJsonException::class, 'Expected JSON to decode to an array, got "string".');
    }
}
