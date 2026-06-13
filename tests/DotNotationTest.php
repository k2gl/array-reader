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
final class DotNotationTest extends TestCase
{
    public function testResolvesNestedPath(): void
    {
        $reader = ArrayReader::of(['user' => ['profile' => ['age' => 30]]]);

        fact($reader->int('user.profile.age'))->is(30);
    }

    public function testLiteralKeyWithDotWinsOverPath(): void
    {
        // BC: a key that literally contains a dot keeps resolving to itself,
        // even when a same-named path exists.
        $reader = ArrayReader::of([
            'a.b' => 1,
            'a'   => ['b' => 2],
        ]);

        fact($reader->int('a.b'))->is(1);
    }

    public function testLiteralKeyWithDotResolvesWhenNoPathExists(): void
    {
        fact(ArrayReader::of(['a.b' => 7])->int('a.b'))->is(7);
    }

    public function testHasFollowsThePath(): void
    {
        $reader = ArrayReader::of(['user' => ['profile' => ['age' => 30]]]);

        fact($reader->has('user.profile.age'))->true();
        fact($reader->has('user.profile.missing'))->false();
        fact($reader->has('user.missing.age'))->false();
    }

    public function testNestedResolvesPath(): void
    {
        $reader = ArrayReader::of(['user' => ['profile' => ['age' => 30]]]);

        fact($reader->nested('user.profile')->int('age'))->is(30);
    }

    public function testLenientAccessorUsesPath(): void
    {
        $reader = ArrayReader::of(['user' => ['profile' => ['age' => 30]]]);

        fact($reader->intOr('user.profile.age', 0))->is(30);
        fact($reader->intOr('user.profile.missing', 7))->is(7);
    }

    public function testStrictThrowsOnMissingPath(): void
    {
        $this->expectException(MissingKeyException::class);

        ArrayReader::of(['user' => ['profile' => []]])->int('user.profile.age');
    }

    public function testPathThroughNonArrayIsMissing(): void
    {
        $reader = ArrayReader::of(['a' => 5]);

        fact($reader->has('a.b'))->false();
        fact($reader->intOr('a.b', -1))->is(-1);
    }

    public function testIntegerKeyIsUnaffected(): void
    {
        fact(ArrayReader::of([0 => 'first'])->string(0))->is('first');
    }

    public function testPlainKeyWithoutDotStillWorks(): void
    {
        fact(ArrayReader::of(['name' => 'k2gl'])->string('name'))->is('k2gl');
    }
}
