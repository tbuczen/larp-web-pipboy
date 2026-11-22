<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Larp;
use App\Entity\LarpMap;
use DomainException;
use PHPUnit\Framework\TestCase;

final class LarpMapTest extends TestCase
{
    public function testUnalignedMapsHaveNoCorners(): void
    {
        $map = new LarpMap(new Larp(), 'a.png', 'image/png', 800, 600);

        self::assertFalse($map->isAligned());
        self::assertNull($map->corners());
    }

    public function testAlignmentStoresRoundedCorners(): void
    {
        $map = new LarpMap(new Larp(), 'a.png', 'image/png', 800, 600);
        $map->align([52.229676123456, 21.012229], [52.229676, 21.022229], ['52.2236', '21.012229']);

        self::assertTrue($map->isAligned());
        self::assertSame(['topLeft' => [52.2296761, 21.012229], 'topRight' => [52.229676, 21.022229], 'bottomLeft' => [52.2236, 21.012229]], $map->corners());
    }

    public function testReplacingTheImageKeepsTheAlignment(): void
    {
        $map = new LarpMap(new Larp(), 'a.png', 'image/png', 800, 600);
        $map->align([1, 1], [1, 2], [0, 1]);
        $map->replaceImage('b.jpg', 'image/jpeg', 1600, 1200);

        self::assertSame('b.jpg', $map->getImageFilename());
        self::assertTrue($map->isAligned());
    }

    /** @return iterable<string, array{array<mixed>, array<mixed>, array<mixed>}> */
    public static function invalidCorners(): iterable
    {
        yield 'latitude out of range' => [[91, 1], [1, 2], [0, 1]];
        yield 'longitude out of range' => [[1, 181], [1, 2], [0, 1]];
        yield 'not a number' => [['north', 1], [1, 2], [0, 1]];
        yield 'missing value' => [[1], [1, 2], [0, 1]];
        yield 'repeated corner' => [[1, 1], [1, 1], [0, 1]];
        yield 'collinear corners' => [[0, 0], [1, 1], [2, 2]];
    }

    /**
     * @param array<mixed> $topLeft
     * @param array<mixed> $topRight
     * @param array<mixed> $bottomLeft
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCorners')]
    public function testInvalidCornersAreRejected(array $topLeft, array $topRight, array $bottomLeft): void
    {
        $map = new LarpMap(new Larp(), 'a.png', 'image/png', 800, 600);

        $this->expectException(DomainException::class);
        $map->align($topLeft, $topRight, $bottomLeft);
    }
}
