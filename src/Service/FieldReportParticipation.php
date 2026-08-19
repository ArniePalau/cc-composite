<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

final class FieldReportParticipation
{
    public const int COMBAT_RECORD_MINIMUM_SECONDS = 1800;

    /** @param array<string, mixed> $payload @return list<string> */
    public function combatRecordPlayers(array $payload): array
    {
        $players = [];
        foreach ($payload['players'] ?? [] as $player) {
            if (!is_array($player) || (int) ($player['secondsPlayed'] ?? 0) <= self::COMBAT_RECORD_MINIMUM_SECONDS) {
                continue;
            }

            $name = trim((string) ($player['name'] ?? ''));
            if ($name !== '') {
                $players[$name] = true;
            }
        }

        return array_keys($players);
    }
}
