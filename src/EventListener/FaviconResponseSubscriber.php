<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\EventListener;

use Forumify\Core\Repository\SettingRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class FaviconResponseSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly SettingRepository $settingRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -100]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        if (!str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }

        $logo = $this->settingRepository->get('forumify.logo');
        if (!is_string($logo) || $logo === '' || basename($logo) !== $logo) {
            return;
        }

        $content = $response->getContent();
        if (!is_string($content) || !str_contains($content, '</head>')) {
            return;
        }

        $tag = sprintf(
            '<link rel="icon" type="image/png" href="/storage/assets/%s">',
            rawurlencode($logo),
        );
        $updated = preg_replace('/<link\s+rel=["\']icon["\'][^>]*>/i', $tag, $content, 1, $replacements);
        if (!is_string($updated)) {
            return;
        }
        if ($replacements === 0) {
            $updated = str_replace('</head>', $tag . "\n</head>", $updated);
        }

        $response->setContent($updated);
    }
}
