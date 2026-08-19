<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Service;

use ArniePalau\CcComposite\Service\ReportVisibilityPolicy;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportVisibilityPolicyTest extends TestCase
{
    /** @return iterable<string, array{string, string, bool}> */
    public static function windows(): iterable
    {
        yield 'spans Wednesday 23:00' => ['2026-08-19 20:00', '2026-08-20 03:00', true];
        yield 'ends before Wednesday slot' => ['2026-08-19 20:00', '2026-08-19 22:59', false];
        yield 'spans Saturday 23:30' => ['2026-08-22 18:00', '2026-08-23 02:00', true];
        yield 'starts after Saturday slot' => ['2026-08-22 23:31', '2026-08-23 02:00', false];
        yield 'unrelated weekday' => ['2026-08-18 20:00', '2026-08-19 02:00', false];
    }

    #[DataProvider('windows')]
    public function testScheduledVisibilityUsesMissionOverlap(string $start, string $end, bool $expected): void
    {
        $timezone = new DateTimeZone('Europe/Madrid');
        $policy = new ReportVisibilityPolicy();

        self::assertSame($expected, $policy->shouldAutoPublish(
            new DateTimeImmutable($start, $timezone),
            new DateTimeImmutable($end, $timezone),
        ));
    }
}
