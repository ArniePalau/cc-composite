<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Admin\Controller;

use ArniePalau\CcComposite\Entity\FieldReportPlayerLink;
use ArniePalau\CcComposite\Repository\FieldReportPlayerLinkRepository;
use ArniePalau\CcComposite\Repository\FieldReportRepository;
use ArniePalau\CcComposite\Service\FieldReportPlayerIdentity;
use ArniePalau\CcComposite\Service\FieldReportCombatRecordSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\User;
use Forumify\Core\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('field-report-players', name: 'field_report_players')]
final class FieldReportPlayerLinkController extends AbstractController
{
    #[Route('', name: '_index', methods: ['GET'])]
    public function index(
        FieldReportRepository $reportRepository,
        FieldReportPlayerLinkRepository $linkRepository,
        UserRepository $userRepository,
        FieldReportPlayerIdentity $identity,
    ): Response {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');
        $links = $linkRepository->findIndexedByPlayerKey();

        return $this->render('@CcCompositePlugin/admin/field_reports/player_links.html.twig', [
            'players' => $identity->collect($reportRepository->findBy([], ['importedAt' => 'ASC']), $links),
            'links' => $links,
            'users' => $userRepository->findBy([], ['displayName' => 'ASC', 'username' => 'ASC']),
        ]);
    }

    #[Route('/save', name: '_save', methods: ['POST'])]
    public function save(
        Request $request,
        FieldReportRepository $reportRepository,
        FieldReportPlayerLinkRepository $linkRepository,
        UserRepository $userRepository,
        FieldReportPlayerIdentity $identity,
        FieldReportCombatRecordSynchronizer $combatRecordSynchronizer,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');
        if (!$this->isCsrfTokenValid('cc-composite-field-report-player-links', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $existing = $linkRepository->findIndexedByPlayerKey();
        $players = $identity->collect($reportRepository->findBy([], ['importedAt' => 'ASC']), $existing);
        $submitted = $request->request->all('links');
        foreach ($players as $playerKey => $playerName) {
            $userId = (int) ($submitted[$playerKey] ?? 0);
            $link = $existing[$playerKey] ?? null;
            if ($userId <= 0) {
                if ($link instanceof FieldReportPlayerLink) {
                    $entityManager->remove($link);
                }
                continue;
            }

            $user = $userRepository->find($userId);
            if (!$user instanceof User) {
                continue;
            }

            if (!$link instanceof FieldReportPlayerLink) {
                $link = new FieldReportPlayerLink();
                $link->setPlayerKey($playerKey);
                $entityManager->persist($link);
            }
            $link->setPlayerName($playerName);
            $link->setForumifyUser($user);
        }

        $entityManager->flush();
        $created = $combatRecordSynchronizer->syncAllEligible();
        $this->addFlash('success', sprintf('Field-report player links saved. %d combat record(s) created.', $created));

        return $this->redirectToRoute('cc_composite_admin_field_report_players_index');
    }
}
