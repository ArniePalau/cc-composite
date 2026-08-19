<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Service;

use ArniePalau\CcComposite\Service\FieldReportParticipation;
use PHPUnit\Framework\TestCase;

final class FieldReportParticipationTest extends TestCase
{
    public function testOnlyPlayersOverThirtyMinutesQualify(): void
    {
        $players = (new FieldReportParticipation())->combatRecordPlayers([
            'players' => [
                ['name' => 'Exactly Thirty', 'secondsPlayed' => 1800],
                ['name' => 'Qualified', 'secondsPlayed' => 1801],
                ['name' => 'Short', 'secondsPlayed' => 900],
                ['name' => '', 'secondsPlayed' => 4000],
            ],
        ]);

        self::assertSame(['Qualified'], $players);
    }
}
