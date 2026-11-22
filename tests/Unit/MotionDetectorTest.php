<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Player;
use App\Entity\PlayerPosition;
use App\Service\MotionDetector;
use PHPUnit\Framework\TestCase;

final class MotionDetectorTest extends TestCase
{
    public function testOffsetsAreMetresEastAndNorth(): void
    {
        $from = new PlayerPosition(new Player(), 52.2297, 21.0122, 5);
        // About 111 m north and 68 m east at this latitude.
        $to = new PlayerPosition(new Player(), 52.2307, 21.0132, 5);

        [$east, $north] = MotionDetector::offset($from, $to);

        self::assertEqualsWithDelta(111.3, $north, 0.5);
        self::assertEqualsWithDelta(68.2, $east, 0.5);
    }
}
