<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Tests;

use K2gl\ArrayReader\AbstractArrayReader;
use K2gl\ArrayReader\ArrayReader;
use K2gl\ArrayReader\Exception\MissingKeyException;
use K2gl\ArrayReader\Exception\TypeMismatchException;
use K2gl\ArrayReader\LooseArrayReader;
use K2gl\ArrayReader\StrictArrayReader;
use K2gl\ArrayReader\Tests\Fixtures\Priority;
use K2gl\ArrayReader\Tests\Fixtures\Suit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(AbstractArrayReader::class)]
#[CoversClass(ArrayReader::class)]
#[CoversClass(StrictArrayReader::class)]
#[CoversClass(LooseArrayReader::class)]
#[CoversClass(TypeMismatchException::class)]
final class CollectionCastersTest extends TestCase
{
    public function testEnumsReadsStringAndIntBackedEnums(): void
    {
        $reader = ArrayReader::of([
            'suits'      => ['hearts', 'spades'],
            'priorities' => [1, 3],
        ]);

        fact($reader->enums('suits', Suit::class))->is([Suit::Hearts, Suit::Spades]);
        fact($reader->enums('priorities', Priority::class))->is([Priority::Low, Priority::High]);
    }

    public function testEnumsAppliesTheCastModePerElement(): void
    {
        // Safe mode reads the int-backed enum's scalar through the cast pipeline: '1' -> 1.
        fact(ArrayReader::of(['p' => ['1', 2]])->enums('p', Priority::class))->is([Priority::Low, Priority::Medium]);
    }

    public function testEnumsThrowsOnUnknownCase(): void
    {
        $this->expectException(TypeMismatchException::class);

        ArrayReader::of(['suits' => ['hearts', 'wizard']])->enums('suits', Suit::class);
    }

    public function testEnumsThrowsOnMissingKey(): void
    {
        $this->expectException(MissingKeyException::class);

        ArrayReader::of([])->enums('suits', Suit::class);
    }

    public function testEnumsOrFallsBackToDefault(): void
    {
        fact(ArrayReader::of([])->enumsOr('suits', Suit::class, [Suit::Clubs]))->is([Suit::Clubs]);
        fact(ArrayReader::of(['suits' => ['nope']])->enumsOr('suits', Suit::class))->null();
        fact(ArrayReader::of(['suits' => ['hearts']])->enumsOr('suits', Suit::class))->is([Suit::Hearts]);
    }

    public function testDateTimesReadsAndParsesEachElement(): void
    {
        $dates = ArrayReader::of(['dates' => ['2026-01-01', '2026-06-30']])->dateTimes('dates');

        fact(array_map(static fn ($d): string => $d->format('Y-m-d'), $dates))->is(['2026-01-01', '2026-06-30']);
    }

    public function testDateTimesHonoursAnExplicitFormat(): void
    {
        $this->expectException(TypeMismatchException::class);

        // '2026-01-01' does not match the d/m/Y format.
        ArrayReader::of(['dates' => ['01/01/2026', '2026-01-01']])->dateTimes('dates', 'd/m/Y');
    }

    public function testDateTimesOrFallsBackToDefault(): void
    {
        fact(ArrayReader::of([])->dateTimesOr('dates'))->null();
        fact(ArrayReader::of(['dates' => ['not-a-date']])->dateTimesOr('dates', []))->is([]);
    }

    public function testListOfMapsEachElementThroughTheCaster(): void
    {
        $reader = ArrayReader::of(['points' => [['x' => 1], ['x' => 2], ['x' => 3]]]);

        $xs = $reader->listOf('points', static fn (mixed $p): int => ArrayReader::of((array) $p)->int('x'));

        fact($xs)->is([1, 2, 3]);
    }

    public function testListOfThrowsOnMissingKeyOrNonList(): void
    {
        $this->expectException(TypeMismatchException::class);

        ArrayReader::of(['items' => ['a' => 1]])->listOf('items', static fn (mixed $v): mixed => $v);
    }

    public function testListOfPropagatesCasterExceptions(): void
    {
        $this->expectException(RuntimeException::class);

        ArrayReader::of(['items' => [1, 2]])->listOf('items', static function (mixed $v): int {
            throw new RuntimeException('boom');
        });
    }

    public function testListOfOrFallsBackWithoutRunningTheCaster(): void
    {
        $ran = false;
        $caster = static function (mixed $v) use (&$ran): mixed {
            $ran = true;

            return $v;
        };

        fact(ArrayReader::of([])->listOfOr('items', $caster, ['fallback']))->is(['fallback']);
        fact($ran)->false();

        fact(ArrayReader::of(['items' => [1, 2]])->listOfOr('items', static fn (mixed $v): int => (int) $v * 10))->is([10, 20]);
    }
}
