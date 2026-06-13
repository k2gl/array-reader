<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Tests;

use K2gl\ArrayReader\AbstractArrayReader;
use K2gl\ArrayReader\ArrayReader;
use K2gl\ArrayReader\Exception\MissingKeyException;
use K2gl\ArrayReader\Exception\TypeMismatchException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(AbstractArrayReader::class)]
#[CoversClass(ArrayReader::class)]
#[CoversClass(TypeMismatchException::class)]
final class DateTimeTest extends TestCase
{
    public function testParsesIso8601WithoutFormat(): void
    {
        $reader = ArrayReader::of(['at' => '2024-01-15T10:30:00+00:00']);

        fact($reader->dateTime('at')->format('Y-m-d H:i:s'))->is('2024-01-15 10:30:00');
    }

    public function testParsesWithExplicitFormat(): void
    {
        $reader = ArrayReader::of(['day' => '15/01/2024']);

        fact($reader->dateTime('day', 'd/m/Y')->format('Y-m-d'))->is('2024-01-15');
    }

    public function testParsesUnixTimestampWithFormat(): void
    {
        // Safe mode casts the int to a string first, then 'U' parses it.
        $reader = ArrayReader::of(['ts' => 1_700_000_000]);

        fact($reader->dateTime('ts', 'U')->getTimestamp())->is(1_700_000_000);
    }

    public function testThrowsOnMissingKey(): void
    {
        $this->expectException(MissingKeyException::class);

        ArrayReader::of([])->dateTime('at');
    }

    public function testThrowsOnUnparsableStringWithoutFormat(): void
    {
        $this->expectException(TypeMismatchException::class);

        ArrayReader::of(['at' => 'not a date'])->dateTime('at');
    }

    public function testThrowsOnEmptyString(): void
    {
        $this->expectException(TypeMismatchException::class);

        ArrayReader::of(['at' => ''])->dateTime('at');
    }

    public function testThrowsWhenInputDoesNotMatchFormat(): void
    {
        $this->expectException(TypeMismatchException::class);

        ArrayReader::of(['day' => '2024-01-15 trailing'])->dateTime('day', 'Y-m-d');
    }

    public function testOrReturnsDefaultOnMissingKey(): void
    {
        $default = new DateTimeImmutable('2000-01-01');

        fact(ArrayReader::of([])->dateTimeOr('at', $default))->is($default);
    }

    public function testOrReturnsNullByDefault(): void
    {
        fact(ArrayReader::of(['at' => 'nonsense'])->dateTimeOr('at'))->is(null);
    }

    public function testOrParsesWithFormat(): void
    {
        $reader = ArrayReader::of(['day' => '2024-12-31']);

        fact($reader->dateTimeOr('day', null, 'Y-m-d')?->format('Y-m-d'))->is('2024-12-31');
    }
}
