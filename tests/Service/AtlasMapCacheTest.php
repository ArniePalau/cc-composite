<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Service;

use ArniePalau\CcComposite\Service\AtlasMapCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class AtlasMapCacheTest extends TestCase
{
    public function testResolvesNativeTileMetadataWithoutDownloadingTiles(): void
    {
        $requestedUrls = [];
        $client = new MockHttpClient(function (string $method, string $url) use (&$requestedUrls): MockResponse {
            $requestedUrls[] = $url;
            if (str_ends_with($url, '/maps/arma3/malden')) {
                return new MockResponse('<a href="/maps/arma3/malden/68">Topographic</a>', ['response_headers' => ['content-type: text/html']]);
            }
            $config = '{"minZoom":0,"maxZoom":5,"factorX":0.02475,"factorY":0.02475,"tileSize":317,"tilePattern":"/data/1/maps/68/68/{z}/{x}/{y}.png","sizeInMeters":12800,"originX":0,"originY":0}';

            return new MockResponse('<script>mapInit(' . $config . ');</script>', ['response_headers' => ['content-type: text/html']]);
        });
        $cache = new AtlasMapCache($client, new AsciiSlugger());

        $result = $cache->cache('Malden');

        self::assertNull($result->path);
        self::assertSame(12800, $result->sizeMeters);
        self::assertSame(317, $result->config['tileSize']);
        self::assertSame(5, $result->config['maxZoom']);
        self::assertSame('https://atlas.plan-ops.fr/data/1/maps/68/68/{z}/{x}/{y}.png', $result->config['tilePattern']);
        self::assertCount(2, $requestedUrls);
        self::assertCount(0, array_filter($requestedUrls, static fn (string $url): bool => str_contains($url, '/data/')));
    }

    public function testProvidesOfflineFallbacksForCurrentCommunityMaps(): void
    {
        $cache = new AtlasMapCache(new MockHttpClient(), new AsciiSlugger());

        $malden = $cache->knownFallback('Malden 2035');
        self::assertNotNull($malden);
        self::assertSame(12800, $malden->sizeMeters);
        self::assertStringContainsString('/maps/68/68/', $malden->config['tilePattern']);

        $tobruk = $cache->knownFallback('iron_excelsior_Tobruk');
        self::assertNotNull($tobruk);
        self::assertSame(10240, $tobruk->sizeMeters);
        self::assertStringContainsString('/maps/183/187/', $tobruk->config['tilePattern']);
        self::assertNull($cache->knownFallback('unknown_world'));
    }

    public function testPreservesUnderscoreWorldSlugUsedByAtlas(): void
    {
        $requestedUrls = [];
        $client = new MockHttpClient(function (string $method, string $url) use (&$requestedUrls): MockResponse {
            $requestedUrls[] = $url;
            if (str_ends_with($url, '/maps/arma3/kunduz_valley')) {
                return new MockResponse('<a href="/maps/arma3/kunduz_valley/65">Topographic</a>', ['response_headers' => ['content-type: text/html']]);
            }
            $config = '{"minZoom":0,"maxZoom":5,"factorX":0.0315,"factorY":0.0315,"tileSize":323,"tilePattern":"/data/1/maps/65/65/{z}/{x}/{y}.png","sizeInMeters":10240,"originX":0,"originY":0}';

            return new MockResponse('<script>mapInit(' . $config . ');</script>', ['response_headers' => ['content-type: text/html']]);
        });

        $result = (new AtlasMapCache($client, new AsciiSlugger()))->cache('kunduz_valley');

        self::assertSame(10240, $result->sizeMeters);
        self::assertSame('https://atlas.plan-ops.fr/maps/arma3/kunduz_valley', $requestedUrls[0]);
        self::assertCount(2, $requestedUrls);
    }
}
