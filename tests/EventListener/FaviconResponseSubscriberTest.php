<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\EventListener;

use ArniePalau\CcComposite\EventListener\FaviconResponseSubscriber;
use Forumify\Core\Repository\SettingRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class FaviconResponseSubscriberTest extends TestCase
{
    public function testReplacesAbsoluteFaviconWithConfiguredRelativeLogo(): void
    {
        $settings = $this->createMock(SettingRepository::class);
        $settings->method('get')->with('forumify.logo')->willReturn('cc-logo.png');
        $response = new Response(
            '<html><head><link rel="icon" href="http://example.test/old.png"></head><body></body></html>',
            headers: ['Content-Type' => 'text/html; charset=UTF-8'],
        );
        $event = new ResponseEvent(
            $this->createMock(KernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        (new FaviconResponseSubscriber($settings))->onResponse($event);

        self::assertStringContainsString(
            '<link rel="icon" type="image/png" href="/storage/assets/cc-logo.png">',
            (string) $response->getContent(),
        );
        self::assertStringNotContainsString('http://example.test/old.png', (string) $response->getContent());
    }
}
