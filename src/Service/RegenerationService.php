<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use Doctrine\ORM\EntityManagerInterface;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Forumify\PerscomPlugin\Perscom\Repository\PerscomUserRepository;

final class RegenerationService
{
    public function __construct(
        private readonly SelectionService $selectionService,
        private readonly CompositeGenerator $generator,
        private readonly PerscomUserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function regenerate(PerscomUser $user, bool $flush = true): bool
    {
        $selection = $this->selectionService->getOrCreate($user);
        $generated = $this->generator->generate($user, $selection);
        if ($flush) {
            $this->entityManager->flush();
        }

        return $generated;
    }

    /** @param iterable<PerscomUser> $users */
    public function regenerateMany(iterable $users): int
    {
        $generated = 0;
        foreach ($users as $user) {
            $generated += (int) $this->regenerate($user, false);
        }
        $this->entityManager->flush();

        return $generated;
    }

    public function regenerateAll(): int
    {
        return $this->regenerateMany($this->userRepository->findAll());
    }
}
