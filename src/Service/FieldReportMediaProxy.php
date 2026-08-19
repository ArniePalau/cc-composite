<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use ArniePalau\CcComposite\Entity\FieldReport;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FieldReportMediaProxy
{
    private const int MAX_IMAGE_BYTES = 2_000_000;
    private const int MAX_MISSION_IMAGE_BYTES = 5_000_000;

    public function __construct(
        private readonly FieldReportUrlParser $urlParser,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function fetch(FieldReport $report, string $kind, string $assetClass): Response
    {
        $endpoints = [
            'weapon' => 'weapon-icon',
            'vehicle' => 'vehicle-image',
            'item' => 'weapon-icon',
            'avatar' => 'player-avatar',
            'marker' => 'marker-icon',
        ];
        if (!isset($endpoints[$kind])) {
            throw new RuntimeException('Unsupported report media type.');
        }
        if (preg_match('/^[A-Za-z0-9_.-]{1,120}$/', $assetClass) !== 1) {
            throw new RuntimeException('Invalid report media class.');
        }

        $parsed = $this->urlParser->parse($report->getSourceUrl());
        $api = parse_url($parsed['api_url']);
        if (!is_array($api) || !isset($api['scheme'], $api['host'])) {
            throw new RuntimeException('Invalid report media origin.');
        }
        $port = isset($api['port']) ? ':' . $api['port'] : '';
        $endpoint = $endpoints[$kind];
        $url = sprintf('%s://%s%s/api/public/%s/%s', $api['scheme'], $api['host'], $port, $endpoint, rawurlencode($assetClass));

        $remote = $this->httpClient->request('GET', $url, [
            'max_redirects' => 0,
            'timeout' => 12,
            'max_duration' => 20,
            'headers' => ['Accept' => 'image/png, image/jpeg'],
        ]);
        if ($remote->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf('The report media server returned HTTP %d.', $remote->getStatusCode()));
        }
        $headers = $remote->getHeaders(false);
        $contentType = strtolower((string) ($headers['content-type'][0] ?? ''));
        if (!in_array($contentType, ['image/png', 'image/jpeg'], true)) {
            throw new RuntimeException('The report media server returned an unexpected content type.');
        }
        $content = $remote->getContent();
        if (strlen($content) > self::MAX_IMAGE_BYTES) {
            throw new RuntimeException('The report media image is too large.');
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=86400, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function fetchMissionImage(FieldReport $report): Response
    {
        $parsed = $this->urlParser->parse($report->getSourceUrl());
        $api = parse_url($parsed['api_url']);
        if (!is_array($api) || !isset($api['scheme'], $api['host'])) {
            throw new RuntimeException('Invalid report media origin.');
        }
        $port = isset($api['port']) ? ':' . $api['port'] : '';
        $url = sprintf(
            '%s://%s%s/api/public/mission-image/%s',
            $api['scheme'],
            $api['host'],
            $port,
            rawurlencode($report->getMissionName()),
        );

        $remote = $this->httpClient->request('GET', $url, [
            'max_redirects' => 0,
            'timeout' => 12,
            'max_duration' => 20,
            'headers' => ['Accept' => 'image/png, image/jpeg'],
        ]);
        if ($remote->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf('The report media server returned HTTP %d.', $remote->getStatusCode()));
        }
        $headers = $remote->getHeaders(false);
        $contentType = strtolower((string) ($headers['content-type'][0] ?? ''));
        if (!in_array($contentType, ['image/png', 'image/jpeg'], true)) {
            throw new RuntimeException('The report media server returned an unexpected content type.');
        }
        $content = $remote->getContent();
        if (strlen($content) > self::MAX_MISSION_IMAGE_BYTES) {
            throw new RuntimeException('The mission image is too large.');
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=86400, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
