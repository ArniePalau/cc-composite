<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Message;

use ArniePalau\CcComposite\Service\RegenerationService;
use Forumify\PerscomPlugin\Perscom\Repository\PerscomUserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegenerateUserMessageHandler
{
    public function __construct(
        private PerscomUserRepository $userRepository,
        private RegenerationService $regenerationService,
    ) {
    }

    public function __invoke(RegenerateUserMessage $message): void
    {
        $user = $this->userRepository->find($message->userId);
        if ($user !== null) {
            $this->regenerationService->regenerate($user);
        }
    }
}
