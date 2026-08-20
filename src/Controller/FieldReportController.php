<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Controller;

use ArniePalau\CcComposite\Entity\FieldReport;
use ArniePalau\CcComposite\Repository\FieldReportRepository;
use ArniePalau\CcComposite\Repository\CampaignRepository;
use ArniePalau\CcComposite\Repository\GalleryImageRepository;
use ArniePalau\CcComposite\Service\FieldReportPlayerProfileResolver;
use ArniePalau\CcComposite\Service\AtlasMapCache;
use ArniePalau\CcComposite\Service\ArmaBriefingFormatter;
use ArniePalau\CcComposite\Service\FieldReportMediaProxy;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/field-reports', name: 'cc_composite_field_reports_')]
final class FieldReportController extends AbstractController
{
    private const int PAGE_SIZE = 6;

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, FieldReportRepository $repository, CampaignRepository $campaignRepository): Response
    {
        $total = $repository->countAll();
        $lastPage = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($lastPage, max(1, $request->query->getInt('page', 1)));

        return $this->render('@CcCompositePlugin/frontend/field_report/index.html.twig', [
            'reports' => $repository->findPage($page, self::PAGE_SIZE),
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
            'campaigns' => $campaignRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/campaign/{slug}', name: 'campaign', methods: ['GET'])]
    public function campaign(string $slug, CampaignRepository $campaignRepository, FieldReportRepository $reportRepository): Response
    {
        $campaign = $campaignRepository->findOneBy(['slug' => $slug]);
        if ($campaign === null) {
            throw $this->createNotFoundException('Campaign not found.');
        }

        return $this->render('@CcCompositePlugin/frontend/field_report/campaign.html.twig', [
            'campaign' => $campaign,
            'reports' => $reportRepository->findForCampaign($campaign),
        ]);
    }

    #[Route('/{code}/media/{kind}/{assetClass}', name: 'media', requirements: ['code' => '[A-Za-z0-9_-]+', 'kind' => 'weapon|vehicle|item|avatar|marker', 'assetClass' => '[A-Za-z0-9_.-]{1,120}'], methods: ['GET'])]
    public function media(string $code, string $kind, string $assetClass, FieldReportRepository $repository, FieldReportMediaProxy $mediaProxy): Response
    {
        $report = $repository->findOneBy(['code' => $code]);
        if (!$report instanceof FieldReport) {
            throw $this->createNotFoundException('Field report not found.');
        }

        try {
            return $mediaProxy->fetch($report, $kind, $assetClass);
        } catch (Throwable) {
            throw $this->createNotFoundException('Report media not found.');
        }
    }

    #[Route('/{code}/media/mission', name: 'mission_media', requirements: ['code' => '[A-Za-z0-9_-]+'], methods: ['GET'])]
    public function missionMedia(string $code, FieldReportRepository $repository, FieldReportMediaProxy $mediaProxy): Response
    {
        $report = $repository->findOneBy(['code' => $code]);
        if (!$report instanceof FieldReport) {
            throw $this->createNotFoundException('Field report not found.');
        }

        try {
            return $mediaProxy->fetchMissionImage($report);
        } catch (Throwable) {
            throw $this->createNotFoundException('Mission image not found.');
        }
    }

    #[Route('/{code}', name: 'show', requirements: ['code' => '[A-Za-z0-9_-]+'], methods: ['GET'])]
    public function show(string $code, FieldReportRepository $repository, GalleryImageRepository $galleryImageRepository, FieldReportPlayerProfileResolver $profileResolver, AtlasMapCache $mapCache, ArmaBriefingFormatter $briefingFormatter, EntityManagerInterface $entityManager): Response
    {
        $report = $repository->findOneBy(['code' => $code]);
        if (!$report instanceof FieldReport) {
            throw $this->createNotFoundException('Field report not found.');
        }

        $payload = $report->getPayload();
        if (!is_array($payload['_ccMap'] ?? null) && $report->getMapPath() === null) {
            $map = $mapCache->knownFallback($report->getWorld());
            if ($map === null) {
                try {
                    $map = $mapCache->cache($report->getWorld());
                } catch (Throwable) {
                    $map = null;
                }
            }
            if ($map !== null) {
                $payload['_ccMap'] = $map->config;
                $report->setPayload($payload);
                $report->setMapSizeMeters($map->sizeMeters);
                $entityManager->flush();
            }
        }

        $frago = null;
        foreach ($payload['briefing'] ?? [] as $entry) {
            if (!is_array($entry) || !in_array(strtolower((string) ($entry['subject'] ?? '')), ['frago', 'fragord'], true)) {
                continue;
            }
            $frago = [
                'title' => (string) ($entry['title'] ?? ''),
                'bodyHtml' => $briefingFormatter->format((string) ($entry['body'] ?? '')),
            ];
            break;
        }

        $tasks = [];
        foreach ($payload['tasks'] ?? [] as $task) {
            if (!is_array($task)) {
                continue;
            }
            $tasks[] = [
                'title' => (string) ($task['title'] ?? ''),
                'state' => strtoupper((string) ($task['state'] ?? '')),
                'descriptionHtml' => $briefingFormatter->format((string) ($task['description'] ?? '')),
            ];
        }

        $playerGroups = [];
        foreach ($payload['players'] ?? [] as $player) {
            if (!is_array($player)) {
                continue;
            }
            $group = trim((string) ($player['groupName'] ?? '')) ?: 'Sense grup';
            $playerGroups[$group][] = $player;
        }

        return $this->render('@CcCompositePlugin/frontend/field_report/show.html.twig', [
            'report' => $report,
            'data' => $payload,
            'playerProfiles' => $profileResolver->resolve($payload),
            'frago' => $frago,
            'tasks' => $tasks,
            'killTimeline' => $this->buildKillTimeline($payload),
            'playerGroups' => $playerGroups,
            'galleryImages' => $galleryImageRepository->findByFieldReport($report),
        ]);
    }

    /** @return array{player: list<int>, hostile: list<int>, max: int, buckets: list<array{start: int, end: int, midpoint: int, player: int, hostile: int}>} */
    private function buildKillTimeline(array $payload): array
    {
        $buckets = 48;
        $duration = max(1, (int) ($payload['durationSeconds'] ?? 1));
        $player = array_fill(0, $buckets, 0);
        $hostile = array_fill(0, $buckets, 0);
        foreach ($payload['killFeed'] ?? [] as $kill) {
            if (!is_array($kill)) {
                continue;
            }
            $index = min($buckets - 1, max(0, (int) floor(((float) ($kill['missionTime'] ?? 0) / $duration) * $buckets)));
            if ((bool) ($kill['victimIsPlayer'] ?? false)) {
                ++$player[$index];
            } elseif ((bool) ($kill['killerIsPlayer'] ?? false)) {
                ++$hostile[$index];
            }
        }

        $timelineBuckets = [];
        for ($index = 0; $index < $buckets; ++$index) {
            $start = (int) floor(($index / $buckets) * $duration);
            $end = (int) floor((($index + 1) / $buckets) * $duration);
            $timelineBuckets[] = [
                'start' => $start,
                'end' => $end,
                'midpoint' => (int) round(($start + $end) / 2),
                'player' => $player[$index],
                'hostile' => $hostile[$index],
            ];
        }

        return [
            'player' => $player,
            'hostile' => $hostile,
            'max' => max(1, ...$player, ...$hostile),
            'buckets' => $timelineBuckets,
        ];
    }
}
