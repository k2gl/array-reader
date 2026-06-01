<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Tests;

use K2gl\ArrayReader\AbstractArrayReader;
use K2gl\ArrayReader\ArrayReader;
use K2gl\ArrayReader\Exception\TypeMismatchException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(AbstractArrayReader::class)]
#[CoversClass(ArrayReader::class)]
#[CoversClass(TypeMismatchException::class)]
final class ArrayReaderTest extends TestCase
{
    #[DataProvider('safeStringProvider')]
    public function testStringCasts(mixed $input, string $expected): void
    {
        fact(ArrayReader::of(['v' => $input])->string('v'))->is($expected);
    }

    public static function safeStringProvider(): array
    {
        return [
            ['text', 'text'],
            [42, '42'],
            [1.5, '1.5'],
            [true, '1'],
            [false, '0'],
        ];
    }

    #[DataProvider('safeIntProvider')]
    public function testIntCasts(mixed $input, int $expected): void
    {
        fact(ArrayReader::of(['v' => $input])->int('v'))->is($expected);
    }

    public static function safeIntProvider(): array
    {
        return [
            [5, 5],
            ['5', 5],
            ['-3', -3],
            [true, 1],
            [false, 0],
        ];
    }

    #[DataProvider('safeFloatProvider')]
    public function testFloatCasts(mixed $input, float $expected): void
    {
        fact(ArrayReader::of(['v' => $input])->float('v'))->is($expected);
    }

    public static function safeFloatProvider(): array
    {
        return [
            [1.5, 1.5],
            [2, 2.0],
            ['1.5', 1.5],
            ['2', 2.0],
        ];
    }

    #[DataProvider('safeBoolProvider')]
    public function testBoolCasts(mixed $input, bool $expected): void
    {
        fact(ArrayReader::of(['v' => $input])->bool('v'))->is($expected);
    }

    public static function safeBoolProvider(): array
    {
        return [
            [true, true],
            ['on', true],
            ['yes', true],
            ['1', true],
            ['true', true],
            [1, true],
            [false, false],
            ['off', false],
            ['no', false],
            ['0', false],
            ['', false],
            [0, false],
        ];
    }

    #[DataProvider('uncastableProvider')]
    public function testStrictAccessorThrowsOnUncastableValue(string $type, mixed $input): void
    {
        $this->expectException(TypeMismatchException::class);

        ArrayReader::of(['v' => $input])->{$type}('v');
    }

    public static function uncastableProvider(): array
    {
        return [
            'garbage string to int' => ['int', 'abc'],
            'fractional string to int' => ['int', '5.5'],
            'float to int' => ['int', 1.5],
            'garbage string to float' => ['float', 'abc'],
            'ambiguous string to bool' => ['bool', 'maybe'],
            'out-of-range int to bool' => ['bool', 2],
            'array to string' => ['string', [1, 2]],
        ];
    }

    public function testLenientReadersCastOrFallBack(): void
    {
        $reader = ArrayReader::of(['page' => '5', 'flag' => 'on', 'bad' => 'abc']);

        fact($reader->intOr('page'))->is(5);
        fact($reader->boolOr('flag'))->true();
        fact($reader->intOr('bad', 7))->is(7);
        fact($reader->intOr('missing', 0))->is(0);
        fact($reader->stringOr('page'))->is('5');
    }

    public function testArrayAndListAreNotCast(): void
    {
        $reader = ArrayReader::of(['meta' => ['a' => 1], 'tags' => ['x', 'y']]);

        fact($reader->array('meta'))->is(['a' => 1]);
        fact($reader->list('tags'))->is(['x', 'y']);
    }

    public function testNestedStaysSafeCasting(): void
    {
        $reader = ArrayReader::of(['filters' => ['page' => '5']]);

        fact($reader->nested('filters') instanceof ArrayReader)->true();
        fact($reader->nested('filters')->int('page'))->is(5);
    }

    public function testHeadlineHttpCase(): void
    {
        $query = ArrayReader::of(['page' => '5', 'active' => 'on', 'ratio' => '1.5']);

        fact($query->int('page'))->is(5);
        fact($query->bool('active'))->true();
        fact($query->float('ratio'))->is(1.5);
    }
}
