<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use ArniePalau\CcComposite\Repository\FieldReportPlayerLinkRepository;
use Forumify\Core\Repository\SettingRepository;
use Forumify\PerscomPlugin\Perscom\Repository\PerscomUserRepository;
use Symfony\Component\Asset\Packages;

final class FieldReportPlayerProfileResolver
{
    public function __construct(
        private readonly FieldReportPlayerLinkRepository $linkRepository,
        private readonly PerscomUserRepository $perscomUserRepository,
        private readonly FieldReportPlayerIdentity $identity,
        private readonly Packages $packages,
        private readonly SettingRepository $settingRepository,
    ) {
    }

    /** @param array<string, mixed> $payload @return array<string, array{id: int, avatar: string|null}> */
    public function resolve(array $payload): array
    {
        $links = $this->linkRepository->findIndexedByPlayerKey();
        $resolved = [];
        $profilesByForumifyUser = [];
        foreach ($this->collectNames($payload) as $name) {
            $link = $links[$this->identity->key($name)] ?? null;
            if ($link === null) {
                continue;
            }

            $forumifyUser = $link->getForumifyUser();
            $forumifyUserId = $forumifyUser->getId();
            if (!array_key_exists($forumifyUserId, $profilesByForumifyUser)) {
                $profilesByForumifyUser[$forumifyUserId] = $this->perscomUserRepository->findOneBy(['user' => $forumifyUser])?->getId();
            }
            if (is_int($profilesByForumifyUser[$forumifyUserId])) {
                $avatar = $forumifyUser->getAvatar() ?? $this->settingRepository->get('forumify.default_avatar');
                $resolved[$name] = [
                    'id' => $profilesByForumifyUser[$forumifyUserId],
                    'avatar' => $avatar ? $this->packages->getUrl($avatar, 'forumify.avatar') : null,
                ];
            }
        }

        return $resolved;
    }

    /** @param array<string, mixed> $payload @return list<string> */
    public function collectNames(array $payload): array
    {
        $names = [];
        $add = static function (mixed $name) use (&$names): void {
            $name = trim((string) $name);
            if ($name !== '') {
                $names[$name] = true;
            }
        };

        $add($payload['mvp']['name'] ?? null);
        foreach ($payload['players'] ?? [] as $player) {
            $add($player['name'] ?? null);
        }
        foreach ($payload['rankings'] ?? [] as $ranking) {
            foreach ($ranking['entries'] ?? [] as $entry) {
                $add($entry['name'] ?? null);
            }
        }
        foreach ($payload['killFeed'] ?? [] as $kill) {
            $add($kill['killer'] ?? null);
            $add($kill['victim'] ?? null);
        }

        return array_keys($names);
    }
}
