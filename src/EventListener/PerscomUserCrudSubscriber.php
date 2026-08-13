<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\EventListener;

use ArniePalau\CcComposite\Service\CompositeGenerator;
use ArniePalau\CcComposite\Service\SelectionService;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Admin\Crud\Event\PostSaveCrudEvent;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PerscomUserCrudSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SelectionService $selectionService,
        private readonly CompositeGenerator $generator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostSaveCrudEvent::getName(PerscomUser::class) => ['onPostSave', -100],
        ];
    }

    /** @param PostSaveCrudEvent<PerscomUser> $event */
    public function onPostSave(PostSaveCrudEvent $event): void
    {
        $form = $event->getForm();
        if (!$form->has('ccCompositeAppearance')) {
            return;
        }

        $user = $event->getEntity();
        $selection = $this->selectionService->save(
            $user,
            $form->get('ccCompositeAppearance')->getData(),
        );
        $this->generator->generate($user, $selection);
        $this->entityManager->flush();
    }
}
