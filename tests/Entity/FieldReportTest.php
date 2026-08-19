<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Entity;

use ArniePalau\CcComposite\Entity\FieldReport;
use PHPUnit\Framework\TestCase;

final class FieldReportTest extends TestCase
{
    public function testFormatsMissionDuration(): void
    {
        $report = new FieldReport();
        $report->setDurationSeconds(616);
        self::assertSame('10min 16s', $report->getDurationLabel());

        $report->setDurationSeconds(7_500);
        self::assertSame('2h 05min', $report->getDurationLabel());
    }

    public function testNegativeCountersAreClamped(): void
    {
        $report = new FieldReport();
        $report->setPlayerCount(-1);
        $report->setTotalKills(-2);
        $report->setTotalFriendlyKills(-3);
        $report->setTotalShots(-4);

        self::assertSame(0, $report->getPlayerCount());
        self::assertSame(0, $report->getTotalKills());
        self::assertSame(0, $report->getTotalFriendlyKills());
        self::assertSame(0, $report->getTotalShots());
    }

    public function testReportsAreVisibleByDefaultAndCanBeHidden(): void
    {
        $report = new FieldReport();
        self::assertTrue($report->isVisible());
        $report->setVisible(false);
        self::assertFalse($report->isVisible());
    }
}
