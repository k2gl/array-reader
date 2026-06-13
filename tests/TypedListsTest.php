<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Tests;

use K2gl\ArrayReader\AbstractArrayReader;
use K2gl\ArrayReader\ArrayReader;
use K2gl\ArrayReader\Exception\MissingKeyException;
use K2gl\ArrayReader\Exception\TypeMismatchException;
use K2gl\ArrayReader\LooseArrayReader;
use K2gl\ArrayReader\StrictArrayReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(AbstractArrayReader::class)]
#[CoversClass(ArrayReader::class)]
#[CoversClass(StrictArrayReader::class)]
#[CoversClass(LooseArrayReader::class)]
#[CoversClass(TypeMismatchException::class)]
final class TypedListsTest extends TestCase
{
    public function testCastsEachElementInSafeMode(): void
    {
        $reader = ArrayReader::of([
            'ids'     => ['1', 2, '3'],
            'tags'    => ['x', 42, true],
            'ratios'  => [1, '1.5', 2.0],
            'flags'   => [true, '1', 0],
        ]);

        fact($reader->ints('ids'))->is([1, 2, 3]);
        fact($reader->strings('tags'))->is(['x', '42', '1']);
        fact($reader->floats('ratios'))->is([1.0, 1.5, 2.0]);
        fact($reader->bools('flags'))->is([true, true, false]);
    }

    public function testEmptyListIsEmpty(): void
    {
        fact(ArrayReader::of(['ids' => []])->ints('ids'))->is([]);
    }

    public function testThrowsWhenAnElementCannotBeProduced(): void
    {
        $this->expectException(TypeMismatchException::class);

        ArrayReader::of(['ids' => [1, 'not-a-number', 3]])->ints('ids');
    }

    public function testThrowsOnMissingKey(): void
    {
        $this->expectException(MissingKeyException::class);

        ArrayReader::of([])->ints('ids');
    }

    public function testThrowsWhenValueIsNotAList(): void
    {
        $this->expectException(TypeMismatchException::class);

        ArrayReader::of(['ids' => ['a' => 1]])->ints('ids');
    }

    public function testStrictModeRejectsCastableElements(): void
    {
        $this->expectException(TypeMismatchException::class);

        // '1' is a numeric string, accepted by Safe but not by Strict.
        StrictArrayReader::of(['ids' => ['1', '2']])->ints('ids');
    }

    public function testLooseModeCastsAnyScalarElement(): void
    {
        fact(LooseArrayReader::of(['ids' => ['1', 'x', 2]])->ints('ids'))->is([1, 0, 2]);
    }

    public function testOrReturnsDefaultOnMissingKey(): void
    {
        fact(ArrayReader::of([])->intsOr('ids', [0]))->is([0]);
    }

    public function testOrReturnsNullByDefault(): void
    {
        fact(ArrayReader::of([])->intsOr('ids'))->is(null);
    }

    public function testOrReturnsDefaultWhenNotAList(): void
    {
        fact(ArrayReader::of(['ids' => 'nope'])->intsOr('ids', []))->is([]);
    }

    public function testOrReturnsDefaultWhenAnElementCannotBeProduced(): void
    {
        fact(ArrayReader::of(['ids' => [1, 'x', 3]])->intsOr('ids', [-1]))->is([-1]);
    }

    public function testOrReturnsCastListWhenValid(): void
    {
        fact(ArrayReader::of(['ids' => ['1', '2']])->intsOr('ids'))->is([1, 2]);
    }
}
