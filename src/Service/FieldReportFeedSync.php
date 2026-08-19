<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use ArniePalau\CcComposite\Repository\FieldReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class FieldReportFeedSync
{
    public const string FEED_URL = 'http://188.165.210.53:8080/api/public/feed';
    private const string FEED_ORIGIN = 'http://188.165.210.53:8080';
    private const int MAX_JSON_BYTES = 2_000_000;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly FieldReportUrlParser $urlParser,
        private readonly FieldReportRepository $reportRepository,
        private readonly FieldReportImporter $importer,
        private readonly ReportVisibilityPolicy $visibilityPolicy,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sync(): FeedSyncResult
    {
        $response = $this->httpClient->request('GET', self::FEED_URL, [
            'max_redirects' => 0,
            'timeout' => 15,
            'max_duration' => 25,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf('The report feed returned HTTP %d.', $response->getStatusCode()));
        }
        $content = $response->getContent();
        if (strlen($content) > self::MAX_JSON_BYTES) {
            throw new RuntimeException('The report feed is too large.');
        }
        try {
            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The report feed returned invalid JSON.', previous: $exception);
        }
        if (!is_array($payload)) {
            throw new RuntimeException('The report feed payload is invalid.');
        }
        $matches = $payload['matches'] ?? $payload;
        if (!is_array($matches)) {
            throw new RuntimeException('The report feed does not contain a matches list.');
        }

        $urls = [];
        $failed = 0;
        foreach ($matches as $entry) {
            try {
                $url = $this->reportUrl($entry);
                $parsed = $this->urlParser->parse($url);
                if (!str_starts_with($parsed['source_url'], self::FEED_ORIGIN . '/r/')) {
                    throw new RuntimeException('Feed entries must belong to the configured report server.');
                }
                $urls[$parsed['code']] = $parsed['source_url'];
            } catch (Throwable $exception) {
                ++$failed;
                $this->logger->warning('Ignored an invalid field report feed entry.', ['exception' => $exception]);
            }
        }

        $imported = 0;
        $existing = 0;
        foreach ($urls as $code => $url) {
            if ($this->reportRepository->findOneBy(['code' => $code]) !== null) {
                ++$existing;
                continue;
            }
            try {
                $report = $this->importer->import($url, false);
                $report->setVisible($this->visibilityPolicy->shouldAutoPublish(
                    $report->getStartedAt(),
                    $report->getEndedAt(),
                    $report->getDurationSeconds(),
                ));
                $this->entityManager->flush();
                ++$imported;
            } catch (Throwable $exception) {
                ++$failed;
                $this->logger->error('Unable to import a field report feed entry.', [
                    'code' => $code,
                    'exception' => $exception,
                ]);
            }
        }

        return new FeedSyncResult(count($urls), $imported, $existing, $failed);
    }

    private function reportUrl(mixed $entry): string
    {
        if (is_string($entry)) {
            return $this->normalizeUrlOrCode($entry);
        }
        if (!is_array($entry)) {
            throw new RuntimeException('Invalid feed entry.');
        }
        foreach (['url', 'reportUrl', 'publicUrl', 'shareUrl', 'sourceUrl', 'link'] as $key) {
            if (isset($entry[$key]) && is_string($entry[$key]) && trim($entry[$key]) !== '') {
                return $this->normalizeUrlOrCode($entry[$key]);
            }
        }
        foreach (['code', 'reportCode', 'publicCode', 'shareCode', 'shortCode'] as $key) {
            if (isset($entry[$key]) && is_string($entry[$key]) && trim($entry[$key]) !== '') {
                return $this->normalizeUrlOrCode($entry[$key]);
            }
        }

        throw new RuntimeException('Feed entry has no report URL or code.');
    }

    private function normalizeUrlOrCode(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^[A-Za-z0-9_-]{3,64}$/', $value) === 1) {
            return self::FEED_ORIGIN . '/r/' . $value;
        }
        if (str_starts_with($value, '/')) {
            return self::FEED_ORIGIN . $value;
        }

        return $value;
    }
}
