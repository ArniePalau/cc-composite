<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use ArniePalau\CcComposite\Entity\FieldReport;
use ArniePalau\CcComposite\Entity\FieldReportCombatRecord;
use ArniePalau\CcComposite\Repository\FieldReportPlayerLinkRepository;
use ArniePalau\CcComposite\Repository\FieldReportRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\PerscomPlugin\Admin\Service\RecordService;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Forumify\PerscomPlugin\Perscom\Entity\Record\CombatRecord;
use Forumify\PerscomPlugin\Perscom\Repository\PerscomUserRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class FieldReportCombatRecordSynchronizer
{
    public function __construct(
        private readonly ReportVisibilityPolicy $visibilityPolicy,
        private readonly FieldReportParticipation $participation,
        private readonly FieldReportPlayerIdentity $identity,
        private readonly FieldReportPlayerLinkRepository $playerLinkRepository,
        private readonly FieldReportRepository $fieldReportRepository,
        private readonly PerscomUserRepository $perscomUserRepository,
        private readonly RecordService $recordService,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function syncAllEligible(): int
    {
        $created = 0;
        foreach ($this->fieldReportRepository->findBy(['visible' => true], ['startedAt' => 'DESC']) as $report) {
            $created += $this->syncReport($report);
        }

        return $created;
    }

    public function syncReport(FieldReport $report): int
    {
        if (!$this->isEligibleReport($report)) {
            return 0;
        }

        $links = $this->playerLinkRepository->findIndexedByPlayerKey();
        $recordLinks = $this->entityManager->getRepository(FieldReportCombatRecord::class)->findBy([
            'fieldReport' => $report,
        ]);
        $existing = [];
        foreach ($recordLinks as $recordLink) {
            $existing[$recordLink->getPerscomUser()->getId()] = $recordLink;
        }

        $text = $this->linkedRecordText($report);
        $newRecords = [];
        $newLinks = [];
        $processedUsers = [];
        foreach ($this->participation->combatRecordPlayers($report->getPayload()) as $playerName) {
            $playerLink = $links[$this->identity->key($playerName)] ?? null;
            if ($playerLink === null) {
                continue;
            }

            $perscomUser = $this->perscomUserRepository->findOneBy([
                'user' => $playerLink->getForumifyUser(),
            ]);
            if (!$perscomUser instanceof PerscomUser) {
                continue;
            }

            $perscomUserId = $perscomUser->getId();
            if ($perscomUserId === null || isset($processedUsers[$perscomUserId])) {
                continue;
            }
            $processedUsers[$perscomUserId] = true;

            $existingLink = $existing[$perscomUserId] ?? null;
            if ($existingLink instanceof FieldReportCombatRecord) {
                $existingLink->getCombatRecord()->setText($text);
                continue;
            }

            $combatRecord = new CombatRecord();
            $combatRecord->setUser($perscomUser);
            $combatRecord->setText($text);
            $combatRecord->setCreatedAt(DateTime::createFromImmutable($report->getStartedAt()));
            $newRecords[] = $combatRecord;

            $recordLink = new FieldReportCombatRecord();
            $recordLink->setFieldReport($report);
            $recordLink->setPerscomUser($perscomUser);
            $recordLink->setCombatRecord($combatRecord);
            $newLinks[] = $recordLink;
        }

        if ($newRecords !== []) {
            $this->recordService->createRecords($newRecords, false);
            foreach ($newLinks as $recordLink) {
                $this->entityManager->persist($recordLink);
            }
        }
        $this->entityManager->flush();

        return count($newRecords);
    }

    private function isEligibleReport(FieldReport $report): bool
    {
        return $report->isVisible()
            && $report->getCampaign() !== null
            && $this->visibilityPolicy->shouldAutoPublish(
                $report->getStartedAt(),
                $report->getEndedAt(),
                $report->getDurationSeconds(),
            );
    }

    private function linkedRecordText(FieldReport $report): string
    {
        $operation = trim((string) ($report->getPayload()['missionTitle'] ?? ''));
        if ($operation === '') {
            $operation = str_replace('_', ' ', $report->getMissionName());
        }
        $label = $report->getCampaign()?->getName() . ' - ' . $operation;
        $url = $this->urlGenerator->generate('cc_composite_field_reports_show', [
            'code' => $report->getCode(),
        ]);

        return sprintf(
            '<a href="%s">%s</a>',
            htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );
    }
}
