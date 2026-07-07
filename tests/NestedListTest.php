<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Tests;

use K2gl\ArrayReader\AbstractArrayReader;
use K2gl\ArrayReader\ArrayReader;
use K2gl\ArrayReader\Exception\MissingKeyException;
use K2gl\ArrayReader\Exception\TypeMismatchException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(AbstractArrayReader::class)]
#[CoversClass(ArrayReader::class)]
#[CoversClass(TypeMismatchException::class)]
final class NestedListTest extends TestCase
{
    public function testWrapsEachElementInAReader(): void
    {
        $reader = ArrayReader::of([
            'items' => [
                ['id' => 1, 'name' => 'a'],
                ['id' => 2, 'name' => 'b'],
            ],
        ]);

        $items = $reader->nestedList('items');

        fact(count($items))->is(2);
        fact($items[0] instanceof ArrayReader)->true();
        fact($items[0]->int('id'))->is(1);
        fact($items[1]->string('name'))->is('b');
    }

    public function testEmptyListIsEmpty(): void
    {
        fact(ArrayReader::of(['items' => []])->nestedList('items'))->is([]);
    }

    public function testThrowsOnMissingKey(): void
    {
        // act + assert
        fact(static fn () => ArrayReader::of([])->nestedList('items'))->throws(MissingKeyException::class);
    }

    public function testThrowsWhenValueIsNotAList(): void
    {
        // act + assert
        fact(static fn () => ArrayReader::of(['items' => ['id' => 1]])->nestedList('items'))
            ->throws(TypeMismatchException::class);
    }

    public function testThrowsWhenAnElementIsNotAnArray(): void
    {
        // act + assert
        fact(static fn () => ArrayReader::of(['items' => [['id' => 1], 'oops']])->nestedList('items'))
            ->throws(TypeMismatchException::class);
    }

    public function testOrReturnsNullOnMissingKey(): void
    {
        fact(ArrayReader::of([])->nestedListOr('items'))->is(null);
    }

    public function testOrReturnsNullWhenNotAList(): void
    {
        fact(ArrayReader::of(['items' => ['id' => 1]])->nestedListOr('items'))->is(null);
    }

    public function testOrReturnsNullWhenAnElementIsNotAnArray(): void
    {
        fact(ArrayReader::of(['items' => [['id' => 1], 5]])->nestedListOr('items'))->is(null);
    }

    public function testOrReturnsReadersWhenValid(): void
    {
        $items = ArrayReader::of(['items' => [['id' => 1]]])->nestedListOr('items');

        fact($items !== null)->true();
        fact($items[0]->int('id'))->is(1);
    }
}
