<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Tests;

use K2gl\ArrayReader\AbstractArrayReader;
use K2gl\ArrayReader\Exception\TypeMismatchException;
use K2gl\ArrayReader\LooseArrayReader;
use K2gl\ArrayReader\Tests\Fixtures\Priority;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(AbstractArrayReader::class)]
#[CoversClass(LooseArrayReader::class)]
#[CoversClass(TypeMismatchException::class)]
final class LooseArrayReaderTest extends TestCase
{
    #[DataProvider('looseIntProvider')]
    public function testIntUsesPhpCast(mixed $input, int $expected): void
    {
        fact(LooseArrayReader::of(['v' => $input])->int('v'))->is($expected);
    }

    public static function looseIntProvider(): array
    {
        return [
            ['5', 5],
            ['abc', 0],
            ['', 0],
            [1.9, 1],
            [true, 1],
            [false, 0],
        ];
    }

    #[DataProvider('looseStringProvider')]
    public function testStringUsesPhpCast(mixed $input, string $expected): void
    {
        fact(LooseArrayReader::of(['v' => $input])->string('v'))->is($expected);
    }

    public static function looseStringProvider(): array
    {
        return [
            [42, '42'],
            [1.5, '1.5'],
            [true, '1'],
            [false, ''],
        ];
    }

    #[DataProvider('looseBoolProvider')]
    public function testBoolUsesPhpCast(mixed $input, bool $expected): void
    {
        fact(LooseArrayReader::of(['v' => $input])->bool('v'))->is($expected);
    }

    public static function looseBoolProvider(): array
    {
        return [
            ['abc', true],
            ['false', true],
            ['', false],
            ['0', false],
            [0, false],
            [2, true],
        ];
    }

    public function testFloatUsesPhpCast(): void
    {
        fact(LooseArrayReader::of(['v' => 'abc'])->float('v'))->is(0.0);
        fact(LooseArrayReader::of(['v' => '1.5'])->float('v'))->is(1.5);
    }

    #[DataProvider('nonScalarProvider')]
    public function testNonScalarValueIsRejected(mixed $input): void
    {
        $this->expectException(TypeMismatchException::class);

        LooseArrayReader::of(['v' => $input])->int('v');
    }

    public static function nonScalarProvider(): array
    {
        return [
            'array' => [[1, 2]],
            'null' => [null],
        ];
    }

    public function testLenientFallsBackOnNonScalar(): void
    {
        fact(LooseArrayReader::of(['v' => null])->intOr('v', 5))->is(5);
        fact(LooseArrayReader::of(['v' => [1]])->intOr('v', 9))->is(9);
    }

    public function testEnumCoercesAnyScalarToBacking(): void
    {
        // Loose casting coerces the scalar to the enum's int backing before tryFrom.
        fact(LooseArrayReader::of(['priority' => '2'])->enum('priority', Priority::class))->is(Priority::Medium);
        fact(LooseArrayReader::of(['priority' => 3.0])->enum('priority', Priority::class))->is(Priority::High);
    }

    public function testEnumOrFallsBackWhenCoercedValueIsNotACase(): void
    {
        // 'abc' coerces to int 0, which is not a Priority case.
        fact(LooseArrayReader::of(['priority' => 'abc'])->enumOr('priority', Priority::class, Priority::Low))
            ->is(Priority::Low);
    }
}
