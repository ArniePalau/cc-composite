<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use ArniePalau\CcComposite\Entity\FieldReport;
use ArniePalau\CcComposite\Repository\FieldReportRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class FieldReportImporter
{
    private const int MAX_JSON_BYTES = 8_000_000;

    public function __construct(
        private readonly FieldReportUrlParser $urlParser,
        private readonly HttpClientInterface $httpClient,
        private readonly FieldReportRepository $reportRepository,
        private readonly AtlasMapCache $mapCache,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function import(string $url): FieldReport
    {
        $parsed = $this->urlParser->parse($url);
        $response = $this->httpClient->request('GET', $parsed['api_url'], [
            'max_redirects' => 0,
            'timeout' => 15,
            'max_duration' => 25,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf('The report server returned HTTP %d.', $response->getStatusCode()));
        }
        $content = $response->getContent();
        if (strlen($content) > self::MAX_JSON_BYTES) {
            throw new RuntimeException('The report JSON is too large.');
        }

        try {
            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The report server returned invalid JSON.', previous: $exception);
        }
        if (!is_array($payload)) {
            throw new RuntimeException('The report payload is invalid.');
        }
        $this->validatePayload($payload, $parsed['code']);

        $report = $this->reportRepository->findOneBy(['code' => $parsed['code']]) ?? new FieldReport();
        $previousWorld = isset($report->getPayload()['world']) ? $report->getWorld() : null;
        $previousMapConfig = $report->getPayload()['_ccMap'] ?? null;
        $previousMapSize = $report->getMapSizeMeters();
        $report->setCode($parsed['code']);
        $report->setSourceUrl($parsed['source_url']);
        $report->setMissionName((string) $payload['missionName']);
        $report->setWorld((string) $payload['world']);
        $report->setWorldDisplayName($this->nullableString($payload['worldDisplayName'] ?? null));
        $report->setServerName($this->nullableString($payload['serverName'] ?? null));
        $report->setStartedAt(new DateTimeImmutable((string) $payload['startedAt']));
        $report->setEndedAt(isset($payload['endedAt']) ? new DateTimeImmutable((string) $payload['endedAt']) : null);
        $report->setDurationSeconds((int) ($payload['durationSeconds'] ?? 0));
        $report->setPlayerCount((int) ($payload['playerCount'] ?? count($payload['players'] ?? [])));
        $report->setTotalKills((int) ($payload['totalKills'] ?? 0));
        $report->setTotalFriendlyKills((int) ($payload['totalFriendlyKills'] ?? 0));
        $report->setTotalShots((int) ($payload['totalShots'] ?? 0));
        $report->setImportedAt(new DateTimeImmutable());

        $report->setMapPath(null);
        $report->setMapSizeMeters(null);
        $sameCachedWorld = $previousWorld !== null
            && strcasecmp($previousWorld, $report->getWorld()) === 0
            && is_array($previousMapConfig)
            && $previousMapSize !== null;
        $existingMap = $sameCachedWorld ? null : $this->reportRepository->findOneWithMapForWorld($report->getWorld());
        if ($sameCachedWorld) {
            $payload['_ccMap'] = $previousMapConfig;
            $report->setMapSizeMeters($previousMapSize);
        } elseif ($existingMap !== null) {
            $payload['_ccMap'] = $existingMap->getPayload()['_ccMap'];
            $report->setMapSizeMeters($existingMap->getMapSizeMeters());
        } else {
            try {
                $map = $this->mapCache->cache($report->getWorld());
                $payload['_ccMap'] = $map->config;
                $report->setMapSizeMeters($map->sizeMeters);
            } catch (Throwable $exception) {
                $this->logger->warning('A field report was imported without an Atlas map.', [
                    'world' => $report->getWorld(),
                    'exception' => $exception,
                ]);
            }
        }
        $report->setPayload($payload);

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return $report;
    }

    /** @param array<string, mixed> $payload */
    private function validatePayload(array $payload, string $expectedCode): void
    {
        foreach (['code', 'missionName', 'world', 'startedAt'] as $field) {
            if (!isset($payload[$field]) || !is_string($payload[$field]) || trim($payload[$field]) === '') {
                throw new RuntimeException(sprintf('The report JSON is missing "%s".', $field));
            }
        }
        if (!hash_equals($expectedCode, (string) $payload['code'])) {
            throw new RuntimeException('The report code does not match the requested URL.');
        }
        try {
            new DateTimeImmutable((string) $payload['startedAt']);
        } catch (Throwable $exception) {
            throw new RuntimeException('The report start date is invalid.', previous: $exception);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
