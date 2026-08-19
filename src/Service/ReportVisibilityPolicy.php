<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class ReportVisibilityPolicy
{
    private const string TIMEZONE = 'Europe/Madrid';

    public function shouldAutoPublish(DateTimeInterface $startedAt, ?DateTimeInterface $endedAt, int $durationSeconds = 0): bool
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $start = DateTimeImmutable::createFromInterface($startedAt)->setTimezone($timezone);
        $end = $endedAt !== null
            ? DateTimeImmutable::createFromInterface($endedAt)->setTimezone($timezone)
            : $start->modify(sprintf('+%d seconds', max(0, $durationSeconds)));
        if ($end < $start) {
            return false;
        }

        for ($date = $start->setTime(0, 0); $date <= $end; $date = $date->modify('+1 day')) {
            $target = match ((int) $date->format('N')) {
                3 => $date->setTime(23, 0),
                6 => $date->setTime(23, 30),
                default => null,
            };
            if ($target !== null && $target >= $start && $target <= $end) {
                return true;
            }
        }

        return false;
    }
}
