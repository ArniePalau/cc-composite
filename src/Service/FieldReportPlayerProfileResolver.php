<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use ArniePalau\CcComposite\Repository\FieldReportPlayerLinkRepository;
use Forumify\Core\Entity\User;
use Forumify\PerscomPlugin\Perscom\Repository\PerscomUserRepository;

final class FieldReportPlayerProfileResolver
{
    public function __construct(
        private readonly FieldReportPlayerLinkRepository $linkRepository,
        private readonly PerscomUserRepository $perscomUserRepository,
        private readonly FieldReportPlayerIdentity $identity,
    ) {
    }

    /** @param array<string, mixed> $payload @return array<string, array{id: int|null, user: User}> */
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
            $resolved[$name] = [
                'id' => is_int($profilesByForumifyUser[$forumifyUserId]) ? $profilesByForumifyUser[$forumifyUserId] : null,
                'user' => $forumifyUser,
            ];
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
