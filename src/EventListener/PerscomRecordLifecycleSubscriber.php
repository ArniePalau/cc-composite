<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\EventListener;

use ArniePalau\CcComposite\Message\RegenerateUserMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Forumify\PerscomPlugin\Perscom\Entity\Record\AssignmentRecord;
use Forumify\PerscomPlugin\Perscom\Entity\Record\AwardRecord;
use Forumify\PerscomPlugin\Perscom\Entity\Record\RankRecord;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
final class PerscomRecordLifecycleSubscriber
{
    /** @var array<int, PerscomUser> */
    private array $queuedUsers = [];
    private bool $processing = false;

    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $entity = $event->getObject();
        if (!$entity instanceof PerscomUser) {
            return;
        }

        if ($event->hasChangedField('rank') || $event->hasChangedField('unit')) {
            $this->queue($entity);
        }
    }

    public function postPersist(PostPersistEventArgs $event): void
    {
        $entity = $event->getObject();
        if ($entity instanceof PerscomUser) {
            $this->queue($entity);
            return;
        }
        $this->queueRecordOwner($entity);
    }

    public function postRemove(PostRemoveEventArgs $event): void
    {
        $this->queueRecordOwner($event->getObject());
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if ($this->processing || $this->queuedUsers === []) {
            return;
        }

        $users = $this->queuedUsers;
        $this->queuedUsers = [];
        $this->processing = true;
        try {
            foreach ($users as $user) {
                $this->messageBus->dispatch(new RegenerateUserMessage($user->getId()));
            }
        } finally {
            $this->processing = false;
        }
    }

    private function queueRecordOwner(object $entity): void
    {
        if ($entity instanceof AwardRecord || $entity instanceof RankRecord || $entity instanceof AssignmentRecord) {
            $this->queue($entity->getUser());
        }
    }

    private function queue(PerscomUser $user): void
    {
        $key = $user->getId() ?? spl_object_id($user);
        $this->queuedUsers[$key] = $user;
    }
}
