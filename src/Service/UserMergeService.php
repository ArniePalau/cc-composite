<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use Doctrine\DBAL\Connection;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\UserRepository;
use InvalidArgumentException;
use Throwable;

final class UserMergeService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @return array{
     *     topics: int,
     *     comments: int,
     *     playerLinks: int,
     *     perscomSoldier: string|null,
     *     canMerge: bool,
     *     error: string|null
     * }
     */
    public function getPreview(int $sourceUserId, int $targetUserId): array
    {
        if ($sourceUserId <= 0) {
            return [
                'topics' => 0,
                'comments' => 0,
                'playerLinks' => 0,
                'perscomSoldier' => null,
                'canMerge' => false,
                'error' => 'Cal seleccionar un usuari d\'origen.',
            ];
        }

        if ($sourceUserId === $targetUserId) {
            return [
                'topics' => 0,
                'comments' => 0,
                'playerLinks' => 0,
                'perscomSoldier' => null,
                'canMerge' => false,
                'error' => 'L\'usuari d\'origen i el de destí no poden ser el mateix.',
            ];
        }

        $topicsCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM topic WHERE created_by = ?',
            [$sourceUserId]
        );

        $commentsCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM comment WHERE created_by = ?',
            [$sourceUserId]
        );

        $playerLinksCount = 0;
        try {
            $playerLinksCount = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM cc_composite_field_report_player_link WHERE forumify_user_id = ?',
                [$sourceUserId]
            );
        } catch (Throwable) {
        }

        $perscomSoldier = null;
        try {
            $soldierName = $this->connection->fetchOne(
                'SELECT name FROM perscom_user WHERE user_id = ? LIMIT 1',
                [$sourceUserId]
            );
            if (is_string($soldierName) && $soldierName !== '') {
                $perscomSoldier = $soldierName;
            }
        } catch (Throwable) {
        }

        return [
            'topics' => $topicsCount,
            'comments' => $commentsCount,
            'playerLinks' => $playerLinksCount,
            'perscomSoldier' => $perscomSoldier,
            'canMerge' => true,
            'error' => null,
        ];
    }

    /**
     * @return array{
     *     topics: int,
     *     comments: int,
     *     playerLinks: int,
     *     perscomLinked: bool
     * }
     */
    public function merge(User $sourceUser, User $targetUser): array
    {
        $sourceId = $sourceUser->getId();
        $targetId = $targetUser->getId();

        if ($sourceId === $targetId) {
            throw new InvalidArgumentException('Source and target users must be different.');
        }

        $preview = $this->getPreview($sourceId, $targetId);

        $this->connection->beginTransaction();
        try {
            // 1. Reassign topics
            $this->connection->executeStatement(
                'UPDATE topic SET created_by = ? WHERE created_by = ?',
                [$targetId, $sourceId]
            );
            $this->connection->executeStatement(
                'UPDATE topic SET updated_by = ? WHERE updated_by = ?',
                [$targetId, $sourceId]
            );

            // 2. Reassign comments
            $this->connection->executeStatement(
                'UPDATE comment SET created_by = ? WHERE created_by = ?',
                [$targetId, $sourceId]
            );
            $this->connection->executeStatement(
                'UPDATE comment SET updated_by = ? WHERE updated_by = ?',
                [$targetId, $sourceId]
            );

            // 3. Reassign player links
            try {
                $this->connection->executeStatement(
                    'UPDATE cc_composite_field_report_player_link SET forumify_user_id = ? WHERE forumify_user_id = ?',
                    [$targetId, $sourceId]
                );
            } catch (Throwable) {
            }

            // 4. PERSCOM soldier dossier re-link if source has one or matches target
            $perscomLinked = false;
            try {
                $targetHasPerscom = (bool) $this->connection->fetchOne(
                    'SELECT 1 FROM perscom_user WHERE user_id = ? LIMIT 1',
                    [$targetId]
                );

                if (!$targetHasPerscom) {
                    $sourcePerscomId = $this->connection->fetchOne(
                        'SELECT id FROM perscom_user WHERE user_id = ? LIMIT 1',
                        [$sourceId]
                    );
                    if ($sourcePerscomId) {
                        $this->connection->executeStatement(
                            'UPDATE perscom_user SET user_id = ? WHERE id = ?',
                            [$targetId, $sourcePerscomId]
                        );
                        $perscomLinked = true;
                    }
                }
            } catch (Throwable) {
            }

            // 5. Disable and mark source user as merged
            $metadata = [
                'merged_into' => $targetId,
                'merged_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'merged_into_username' => $targetUser->getUsername(),
            ];

            $this->connection->executeStatement(
                'UPDATE user SET signature = ?, password = ? WHERE id = ?',
                [
                    json_encode($metadata, JSON_UNESCAPED_UNICODE),
                    '!DISABLED!_MERGED_' . bin2hex(random_bytes(8)),
                    $sourceId,
                ]
            );

            $this->connection->commit();
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return [
            'topics' => $preview['topics'],
            'comments' => $preview['comments'],
            'playerLinks' => $preview['playerLinks'],
            'perscomLinked' => $perscomLinked,
        ];
    }

    /**
     * Finds ghost or imported users available for merging.
     * @return list<User>
     */
    public function findPendingGhostUsers(): array
    {
        return $this->userRepository->createQueryBuilder('u')
            ->where('u.username LIKE :ghostPrefix OR u.email LIKE :ghostEmail')
            ->andWhere('u.password NOT LIKE :disabled')
            ->setParameter('ghostPrefix', 'ghost_%')
            ->setParameter('ghostEmail', '%@ghost.cavallersdelcel.cat')
            ->setParameter('disabled', '!DISABLED!%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
