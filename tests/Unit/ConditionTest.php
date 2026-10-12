<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Condition;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConditionTest extends TestCase
{
    public function testConditionIncludesStartAndExcludesEnd(): void
    {
        $start = new DateTimeImmutable('2026-10-18T10:00:00+00:00');
        $end = new DateTimeImmutable('2026-10-18T11:00:00+00:00');
        $condition = (new Condition())->setInterval($start, $end);

        self::assertFalse($condition->isActiveAt($start->modify('-1 second')));
        self::assertTrue($condition->isActiveAt($start));
        self::assertTrue($condition->isActiveAt($end->modify('-1 second')));
        self::assertFalse($condition->isActiveAt($end));
    }

    public function testOpenEndedConditionRemainsActiveAfterStart(): void
    {
        $start = new DateTimeImmutable('2026-10-18T10:00:00+00:00');
        $condition = (new Condition())->setInterval($start, null);

        self::assertTrue($condition->isActiveAt($start->modify('+1 year')));
    }

    public function testInvalidIntervalDoesNotOverwriteExistingInterval(): void
    {
        $start = new DateTimeImmutable('2026-10-18T10:00:00+00:00');
        $end = $start->modify('+1 hour');
        $condition = (new Condition())->setInterval($start, $end);

        try {
            $condition->setInterval($end, $start);
            self::fail('Invalid condition intervals must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame($start, $condition->getStartsAt());
            self::assertSame($end, $condition->getEndsAt());
        }
    }
}
