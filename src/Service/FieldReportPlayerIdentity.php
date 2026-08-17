<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use ArniePalau\CcComposite\Entity\FieldReport;
use ArniePalau\CcComposite\Entity\FieldReportPlayerLink;

final class FieldReportPlayerIdentity
{
    public function key(string $name): string
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name), 'UTF-8');

        return hash('sha256', $normalized);
    }

    /**
     * @param iterable<FieldReport> $reports
     * @param iterable<FieldReportPlayerLink> $existingLinks
     * @return array<string, string> player key => most recently imported display name
     */
    public function collect(iterable $reports, iterable $existingLinks = []): array
    {
        $players = [];
        foreach ($existingLinks as $link) {
            $players[$link->getPlayerKey()] = $link->getPlayerName();
        }

        foreach ($reports as $report) {
            foreach ($report->getPayload()['players'] ?? [] as $player) {
                $name = trim((string) ($player['name'] ?? ''));
                if ($name !== '') {
                    $players[$this->key($name)] = $name;
                }
            }
        }

        uasort($players, static fn (string $left, string $right): int => strnatcasecmp($left, $right));

        return $players;
    }
}
