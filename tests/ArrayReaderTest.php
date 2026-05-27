<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Tests;

use K2gl\ArrayReader\ArrayReader;
use K2gl\ArrayReader\Exception\MissingKeyException;
use K2gl\ArrayReader\Exception\TypeMismatchException;

use function K2gl\PHPUnitFluentAssertions\fact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayReader::class)]
#[CoversClass(MissingKeyException::class)]
#[CoversClass(TypeMismatchException::class)]
final class ArrayReaderTest extends TestCase
{
    public function testStrictReadersReturnTypedValues(): void
    {
        // arrange
        $reader = ArrayReader::of([
            'name' => 'Ada',
            'age' => 36,
            'score' => 9.5,
            'active' => true,
            'meta' => ['a' => 1],
            'tags' => ['x', 'y'],
        ]);

        // assert
        fact($reader->string('name'))->is('Ada');
        fact($reader->int('age'))->is(36);
        fact($reader->float('score'))->is(9.5);
        fact($reader->bool('active'))->true();
        fact($reader->array('meta'))->is(['a' => 1]);
        fact($reader->list('tags'))->is(['x', 'y']);
    }

    public function testFloatAcceptsLosslessIntWidening(): void
    {
        // act
        $price = ArrayReader::of(['price' => 10])->float('price');

        // assert
        fact($price)->is(10.0);
    }

    public function testHasDistinguishesPresentFromAbsentKeys(): void
    {
        // arrange
        $reader = ArrayReader::of(['present' => null]);

        // assert
        fact($reader->has('present'))->true();
        fact($reader->has('absent'))->false();
    }

    public function testLenientReadersFallBackToDefault(): void
    {
        // arrange
        $reader = ArrayReader::of(['name' => 'Ada']);

        // assert
        fact($reader->stringOr('name'))->is('Ada');
        fact($reader->stringOr('missing'))->is(null);
        fact($reader->stringOr('missing', 'fallback'))->is('fallback');
        fact($reader->intOr('missing', 0))->is(0);
        fact($reader->floatOr('missing', 1.5))->is(1.5);
        fact($reader->boolOr('missing', false))->false();
        fact($reader->arrayOr('missing', []))->is([]);
        fact($reader->listOr('missing', []))->is([]);
    }

    public function testLenientReadersFallBackOnTypeMismatch(): void
    {
        // arrange
        $reader = ArrayReader::of(['age' => 'not-an-int']);

        // assert
        fact($reader->intOr('age', -1))->is(-1);
    }

    public function testNestedReturnsSubReader(): void
    {
        // arrange
        $reader = ArrayReader::of(['address' => ['city' => 'Paris']]);

        // assert
        fact($reader->nested('address')->string('city'))->is('Paris');
        fact($reader->nestedOr('missing'))->is(null);
    }

    public function testToArrayReturnsUnderlyingData(): void
    {
        // arrange
        $data = ['a' => 1, 'b' => 2];

        // assert
        fact(ArrayReader::of($data)->toArray())->is($data);
    }

    public function testStringThrowsOnMissingKey(): void
    {
        $this->expectException(MissingKeyException::class);
        $this->expectExceptionMessage('Missing required key "email".');

        ArrayReader::of([])->string('email');
    }

    public function testStrictReaderThrowsOnTypeMismatch(): void
    {
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('Expected "string" at key "age", got "int".');

        ArrayReader::of(['age' => 36])->string('age');
    }

    public function testPresentNullValueIsATypeMismatchForStrictReader(): void
    {
        $this->expectException(TypeMismatchException::class);

        ArrayReader::of(['name' => null])->string('name');
    }

    public function testIntRejectsFloat(): void
    {
        $this->expectException(TypeMismatchException::class);

        ArrayReader::of(['n' => 1.5])->int('n');
    }

    public function testListRejectsAssociativeArray(): void
    {
        $this->expectException(TypeMismatchException::class);

        ArrayReader::of(['map' => ['a' => 1]])->list('map');
    }
}
