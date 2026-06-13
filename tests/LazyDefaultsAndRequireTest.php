<?php

declare(strict_types=1);

namespace K2gl\ArrayReader\Tests;

use K2gl\ArrayReader\AbstractArrayReader;
use K2gl\ArrayReader\ArrayReader;
use K2gl\ArrayReader\Exception\MissingKeyException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function K2gl\PHPUnitFluentAssertions\fact;

#[CoversClass(AbstractArrayReader::class)]
#[CoversClass(ArrayReader::class)]
#[CoversClass(MissingKeyException::class)]
final class LazyDefaultsAndRequireTest extends TestCase
{
    public function testOrElseReturnsValueWhenPresent(): void
    {
        $reader = ArrayReader::of(['page' => '5']);

        fact($reader->intOrElse('page', fn (): int => 99))->is(5);
    }

    public function testOrElseDoesNotInvokeCallbackWhenValuePresent(): void
    {
        $reader = ArrayReader::of(['page' => 5]);
        $called = false;

        $reader->intOrElse('page', function () use (&$called): int {
            $called = true;

            return 0;
        });

        fact($called)->false();
    }

    public function testOrElseInvokesCallbackWhenAbsent(): void
    {
        $reader = ArrayReader::of([]);

        fact($reader->intOrElse('page', fn (): int => 42))->is(42);
        fact($reader->stringOrElse('name', fn (): string => 'fallback'))->is('fallback');
        fact($reader->floatOrElse('ratio', fn (): float => 1.5))->is(1.5);
        fact($reader->boolOrElse('flag', fn (): bool => true))->true();
    }

    public function testOrElseInvokesCallbackWhenValueNotProducible(): void
    {
        $reader = ArrayReader::of(['page' => 'not-a-number']);

        fact($reader->intOrElse('page', fn (): int => 7))->is(7);
    }

    public function testOrElseFollowsDotPath(): void
    {
        $reader = ArrayReader::of(['paging' => ['page' => 3]]);

        fact($reader->intOrElse('paging.page', fn (): int => 0))->is(3);
    }

    public function testRequireReturnsSelfWhenAllPresent(): void
    {
        $reader = ArrayReader::of(['a' => 1, 'b' => 2]);

        fact($reader->require(['a', 'b']) === $reader)->true();
    }

    public function testRequireFollowsDotPaths(): void
    {
        $reader = ArrayReader::of(['user' => ['id' => 1, 'name' => 'x']]);

        fact($reader->require(['user.id', 'user.name']) === $reader)->true();
    }

    public function testRequireThrowsListingAllMissingKeys(): void
    {
        $reader = ArrayReader::of(['a' => 1]);

        try {
            $reader->require(['a', 'b', 'c']);
            $this->fail('Expected MissingKeyException');
        } catch (MissingKeyException $e) {
            fact(str_contains($e->getMessage(), '"b"'))->true();
            fact(str_contains($e->getMessage(), '"c"'))->true();
            fact(str_contains($e->getMessage(), '"a"'))->false();
        }
    }
}
