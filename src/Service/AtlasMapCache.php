<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use RuntimeException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves PLANOPS Atlas metadata. The browser then requests only the native
 * tiles visible at its current zoom instead of stretching one stitched image.
 */
final class AtlasMapCache
{
    private const string ATLAS_BASE = 'https://atlas.plan-ops.fr';
    private const int MAX_RESPONSE_BYTES = 12_000_000;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function cache(string $world): MapCacheResult
    {
        $normalized = strtolower(trim($world));
        $slugs = array_values(array_unique(array_filter([
            preg_match('/^[a-z0-9_-]+$/', $normalized) === 1 ? $normalized : null,
            strtolower((string) $this->slugger->slug(str_replace('_', ' ', $world))),
            str_replace('-', '_', strtolower((string) $this->slugger->slug(str_replace('_', ' ', $world)))),
        ])));
        if ($slugs === []) {
            throw new RuntimeException('The map name is empty.');
        }

        $mapPage = null;
        $slug = null;
        foreach ($slugs as $candidate) {
            try {
                $mapPage = $this->requestText(self::ATLAS_BASE . '/maps/arma3/' . rawurlencode($candidate));
                $slug = $candidate;
                break;
            } catch (RuntimeException) {
                continue;
            }
        }
        if ($mapPage === null || $slug === null) {
            throw new RuntimeException(sprintf('No Atlas map was found for "%s".', $world));
        }
        if (!preg_match(sprintf('~href="(/maps/arma3/%s/\d+)"~i', preg_quote($slug, '~')), $mapPage, $layerMatch)) {
            throw new RuntimeException(sprintf('No topographic Atlas layer was found for "%s".', $world));
        }

        $layerPage = $this->requestText(self::ATLAS_BASE . $layerMatch[1]);
        if (!preg_match('~mapInit\((\{.*?\})\);~s', $layerPage, $configMatch)) {
            throw new RuntimeException('The Atlas map configuration could not be read.');
        }

        $raw = json_decode($configMatch[1], true, 512, JSON_THROW_ON_ERROR);
        $tilePattern = $raw['tilePattern'] ?? null;
        $sizeMeters = (int) ($raw['sizeInMeters'] ?? 0);
        $tileSize = (int) ($raw['tileSize'] ?? 0);
        $maxZoom = (int) ($raw['maxZoom'] ?? -1);
        if (!is_string($tilePattern)
            || !str_starts_with($tilePattern, '/data/')
            || !str_contains($tilePattern, '{z}')
            || !str_contains($tilePattern, '{x}')
            || !str_contains($tilePattern, '{y}')
            || $sizeMeters <= 0
            || $tileSize <= 0
            || $maxZoom < 0
        ) {
            throw new RuntimeException('The Atlas map configuration is incomplete.');
        }

        $config = [
            'minZoom' => max(0, (int) ($raw['minZoom'] ?? 0)),
            'maxZoom' => $maxZoom,
            'factorX' => (float) ($raw['factorX'] ?? ($tileSize / $sizeMeters)),
            'factorY' => (float) ($raw['factorY'] ?? ($tileSize / $sizeMeters)),
            'tileSize' => $tileSize,
            'tilePattern' => self::ATLAS_BASE . $tilePattern,
            'attribution' => (string) ($raw['attribution'] ?? '© Bohemia Interactive'),
            'originX' => (float) ($raw['originX'] ?? 0),
            'originY' => (float) ($raw['originY'] ?? 0),
            'sizeInMeters' => $sizeMeters,
            'defaultPosition' => [$sizeMeters / 2, $sizeMeters / 2],
            'defaultZoom' => min(2, $maxZoom),
            'isSvg' => (bool) ($raw['isSvg'] ?? false),
            'isAerial' => (bool) ($raw['isAerial'] ?? false),
        ];

        return new MapCacheResult(null, $sizeMeters, $config);
    }

    public function knownFallback(string $world): ?MapCacheResult
    {
        $key = strtolower(trim(str_replace(['_', '-'], ' ', $world)));
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;
        if (in_array($key, ['malden', 'malden 2035'], true)) {
            return new MapCacheResult(null, 12800, [
                'minZoom' => 0, 'maxZoom' => 5, 'factorX' => 0.02475, 'factorY' => 0.02475,
                'tileSize' => 317, 'tilePattern' => self::ATLAS_BASE . '/data/1/maps/68/68/{z}/{x}/{y}.png',
                'attribution' => '© Bohemia Interactive', 'originX' => 0, 'originY' => 0,
                'sizeInMeters' => 12800, 'defaultPosition' => [6400, 6400], 'defaultZoom' => 2,
                'isSvg' => false, 'isAerial' => false,
            ]);
        }
        if (in_array($key, ['iron excelsior tobruk', 'ww2 tobruk libya', 'tobruk'], true)) {
            return new MapCacheResult(null, 10240, [
                'minZoom' => 0, 'maxZoom' => 5, 'factorX' => 0.0315, 'factorY' => 0.0315,
                'tileSize' => 323, 'tilePattern' => self::ATLAS_BASE . '/data/1/maps/183/187/{z}/{x}/{y}.png',
                'attribution' => '© Bohemia Interactive, ww2ina3', 'originX' => 0, 'originY' => 0,
                'sizeInMeters' => 10240, 'defaultPosition' => [5120, 5120], 'defaultZoom' => 2,
                'isSvg' => false, 'isAerial' => false,
            ]);
        }

        return null;
    }

    private function requestText(string $url): string
    {
        $response = $this->httpClient->request('GET', $url, [
            'max_redirects' => 0,
            'timeout' => 12,
            'max_duration' => 20,
            'headers' => ['User-Agent' => 'CC-Composite-Field-Reports/1.0'],
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf('Atlas returned HTTP %d.', $response->getStatusCode()));
        }
        $headers = $response->getHeaders(false);
        $contentType = strtolower((string) ($headers['content-type'][0] ?? ''));
        if (!str_starts_with($contentType, 'text/html')) {
            throw new RuntimeException('Atlas returned an unexpected content type.');
        }
        $content = $response->getContent();
        if (strlen($content) > self::MAX_RESPONSE_BYTES) {
            throw new RuntimeException('The Atlas response is too large.');
        }

        return $content;
    }
}
