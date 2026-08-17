<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use GdImage;
use League\Flysystem\FilesystemOperator;
use RuntimeException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AtlasMapCache
{
    private const string ATLAS_BASE = 'https://atlas.plan-ops.fr';
    private const int CACHE_ZOOM = 2;
    private const int MAX_RESPONSE_BYTES = 12_000_000;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly FilesystemOperator $layerStorage,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function cache(string $world): MapCacheResult
    {
        $slug = strtolower((string) $this->slugger->slug(str_replace('_', ' ', $world)));
        if ($slug === '') {
            throw new RuntimeException('The map name is empty.');
        }

        $mapPageUrl = self::ATLAS_BASE . '/maps/arma3/' . rawurlencode($slug);
        $mapPage = $this->requestText($mapPageUrl);
        if (!preg_match(sprintf('~href="(/maps/arma3/%s/\d+)"~i', preg_quote($slug, '~')), $mapPage, $layerMatch)) {
            throw new RuntimeException(sprintf('No topographic Atlas layer was found for "%s".', $world));
        }

        $layerPage = $this->requestText(self::ATLAS_BASE . $layerMatch[1]);
        if (!preg_match('~mapInit\((\{.*?\})\);~s', $layerPage, $configMatch)) {
            throw new RuntimeException('The Atlas map configuration could not be read.');
        }

        $config = json_decode($configMatch[1], true, 512, JSON_THROW_ON_ERROR);
        $tilePattern = $config['tilePattern'] ?? null;
        $sizeMeters = (int) ($config['sizeInMeters'] ?? 0);
        $tileSize = (int) ($config['tileSize'] ?? 0);
        $maxZoom = (int) ($config['maxZoom'] ?? 0);
        if (!is_string($tilePattern) || $sizeMeters <= 0 || $tileSize <= 0) {
            throw new RuntimeException('The Atlas map configuration is incomplete.');
        }

        $path = sprintf('maps/%s.png', $slug);
        if ($this->layerStorage->fileExists($path)) {
            return new MapCacheResult($path, $sizeMeters);
        }

        $zoom = min(self::CACHE_ZOOM, $maxZoom);
        $tilesPerAxis = 2 ** $zoom;
        $canvas = $this->transparentImage($tileSize * $tilesPerAxis, $tileSize * $tilesPerAxis);
        for ($x = 0; $x < $tilesPerAxis; ++$x) {
            for ($y = 0; $y < $tilesPerAxis; ++$y) {
                $tilePath = strtr($tilePattern, [
                    '{z}' => (string) $zoom,
                    '{x}' => (string) $x,
                    '{y}' => (string) $y,
                ]);
                $tile = $this->decodeImage($this->requestBinary(self::ATLAS_BASE . $tilePath));
                imagecopy($canvas, $tile, $x * $tileSize, $y * $tileSize, 0, 0, imagesx($tile), imagesy($tile));
            }
        }

        ob_start();
        imagepng($canvas, null, 7);
        $png = ob_get_clean();
        if (!is_string($png)) {
            throw new RuntimeException('The cached Atlas map could not be encoded.');
        }
        $this->layerStorage->write($path, $png);

        return new MapCacheResult($path, $sizeMeters);
    }

    private function requestText(string $url): string
    {
        return $this->request($url, ['text/html']);
    }

    private function requestBinary(string $url): string
    {
        return $this->request($url, ['image/png', 'image/webp', 'image/jpeg']);
    }

    /** @param list<string> $allowedTypes */
    private function request(string $url, array $allowedTypes): string
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
        if (!array_any($allowedTypes, static fn (string $type): bool => str_starts_with($contentType, $type))) {
            throw new RuntimeException('Atlas returned an unexpected content type.');
        }
        $content = $response->getContent();
        if (strlen($content) > self::MAX_RESPONSE_BYTES) {
            throw new RuntimeException('The Atlas response is too large.');
        }

        return $content;
    }

    private function transparentImage(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);

        return $image;
    }

    private function decodeImage(string $content): GdImage
    {
        $image = @imagecreatefromstring($content);
        if (!$image instanceof GdImage) {
            throw new RuntimeException('An Atlas tile could not be decoded.');
        }

        return $image;
    }
}
