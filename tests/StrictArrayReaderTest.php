<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Tests;

use K2gl\ArrayReader\AbstractArrayReader;
use K2gl\ArrayReader\Exception\MissingKeyException;
use K2gl\ArrayReader\Exception\TypeMismatchException;
use K2gl\ArrayReader\StrictArrayReader;

use function K2gl\PHPUnitFluentAssertions\fact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractArrayReader::class)]
#[CoversClass(StrictArrayReader::class)]
#[CoversClass(MissingKeyException::class)]
#[CoversClass(TypeMismatchException::class)]
final class StrictArrayReaderTest extends TestCase
{
    public function testReadersReturnExactlyTypedValues(): void
    {
        $reader = StrictArrayReader::of([
            'name' => 'Ada',
            'age' => 36,
            'score' => 9.5,
            'active' => true,
            'meta' => ['a' => 1],
            'tags' => ['x', 'y'],
        ]);

        fact($reader->string('name'))->is('Ada');
        fact($reader->int('age'))->is(36);
        fact($reader->float('score'))->is(9.5);
        fact($reader->bool('active'))->true();
        fact($reader->array('meta'))->is(['a' => 1]);
        fact($reader->list('tags'))->is(['x', 'y']);
    }

    public function testFloatAcceptsLosslessIntWidening(): void
    {
        fact(StrictArrayReader::of(['price' => 10])->float('price'))->is(10.0);
    }

    public function testHasDistinguishesPresentFromAbsentKeys(): void
    {
        $reader = StrictArrayReader::of(['present' => null]);

        fact($reader->has('present'))->true();
        fact($reader->has('absent'))->false();
    }

    public function testLenientReadersFallBackToDefault(): void
    {
        $reader = StrictArrayReader::of(['name' => 'Ada']);

        fact($reader->stringOr('name'))->is('Ada');
        fact($reader->stringOr('missing'))->is(null);
        fact($reader->stringOr('missing', 'fallback'))->is('fallback');
        fact($reader->intOr('missing', 0))->is(0);
        fact($reader->boolOr('missing', false))->false();
    }

    public function testLenientReadersFallBackOnTypeMismatch(): void
    {
        fact(StrictArrayReader::of(['age' => 'not-an-int'])->intOr('age', -1))->is(-1);
    }

    public function testNestedReturnsSubReaderOfSameKind(): void
    {
        $reader = StrictArrayReader::of(['address' => ['city' => 'Paris']]);

        fact($reader->nested('address') instanceof StrictArrayReader)->true();
        fact($reader->nested('address')->string('city'))->is('Paris');
        fact($reader->nestedOr('missing'))->is(null);
    }

    public function testStringThrowsOnMissingKey(): void
    {
        $this->expectException(MissingKeyException::class);
        $this->expectExceptionMessage('Missing required key "email".');

        StrictArrayReader::of([])->string('email');
    }

    public function testThrowsOnTypeMismatch(): void
    {
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('Expected "string" at key "age", got "int".');

        StrictArrayReader::of(['age' => 36])->string('age');
    }

    public function testNumericStringIsNotAccepted(): void
    {
        $this->expectException(TypeMismatchException::class);

        StrictArrayReader::of(['age' => '36'])->int('age');
    }

    public function testIntRejectsFloat(): void
    {
        $this->expectException(TypeMismatchException::class);

        StrictArrayReader::of(['n' => 1.5])->int('n');
    }

    public function testListRejectsAssociativeArray(): void
    {
        $this->expectException(TypeMismatchException::class);

        StrictArrayReader::of(['map' => ['a' => 1]])->list('map');
    }
}
