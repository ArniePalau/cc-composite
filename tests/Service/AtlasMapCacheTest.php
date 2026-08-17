<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Service;

use ArniePalau\CcComposite\Service\AtlasMapCache;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class AtlasMapCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/cc-atlas-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testDownloadsTilesOnceAndReusesCachedMap(): void
    {
        $tile = imagecreatetruecolor(4, 4);
        imagefill($tile, 0, 0, imagecolorallocate($tile, 20, 80, 140));
        ob_start();
        imagepng($tile);
        $tilePng = ob_get_clean();
        self::assertIsString($tilePng);

        $requestedUrls = [];
        $client = new MockHttpClient(function (string $method, string $url) use (&$requestedUrls, $tilePng): MockResponse {
            $requestedUrls[] = $url;
            if (str_ends_with($url, '/maps/arma3/malden')) {
                return new MockResponse('<a href="/maps/arma3/malden/68">Topographic</a>', ['response_headers' => ['content-type: text/html']]);
            }
            if (str_ends_with($url, '/maps/arma3/malden/68')) {
                $config = '{"maxZoom":5,"tileSize":4,"tilePattern":"/data/68/{z}/{x}/{y}.png","sizeInMeters":12800}';
                return new MockResponse('<script>mapInit(' . $config . ');</script>', ['response_headers' => ['content-type: text/html']]);
            }

            return new MockResponse($tilePng, ['response_headers' => ['content-type: image/png']]);
        });
        $storage = new Filesystem(new LocalFilesystemAdapter($this->directory));
        $cache = new AtlasMapCache($client, $storage, new AsciiSlugger());

        $first = $cache->cache('Malden');
        $second = $cache->cache('Malden');

        self::assertSame('maps/malden.png', $first->path);
        self::assertSame(12800, $first->sizeMeters);
        self::assertSame($first->path, $second->path);
        self::assertTrue($storage->fileExists('maps/malden.png'));
        self::assertCount(16, array_filter($requestedUrls, static fn (string $url): bool => str_contains($url, '/data/68/')));
        $dimensions = getimagesize($this->directory . '/maps/malden.png');
        self::assertIsArray($dimensions);
        self::assertSame([16, 16], [$dimensions[0], $dimensions[1]]);
    }
}
