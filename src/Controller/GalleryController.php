<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Controller;

use ArniePalau\CcComposite\Entity\Campaign;
use ArniePalau\CcComposite\Entity\FieldReport;
use ArniePalau\CcComposite\Repository\CampaignRepository;
use ArniePalau\CcComposite\Repository\FieldReportRepository;
use ArniePalau\CcComposite\Repository\GalleryImageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GalleryController extends AbstractController
{
    #[Route('/galeria', name: 'cc_composite_galeria', methods: ['GET'])]
    #[Route('/gallery', name: 'cc_composite_gallery', methods: ['GET'])]
    public function __invoke(
        Request $request,
        CampaignRepository $campaignRepository,
        FieldReportRepository $reportRepository,
        GalleryImageRepository $imageRepository
    ): Response {
        $campaigns = $campaignRepository->findBy([], ['name' => 'ASC']);
        
        $campaignParam = $request->query->get('campaign');
        $missionParam = $request->query->get('mission');

        $selectedCampaign = null;
        $selectedReport = null;

        if ($campaignParam !== null && $campaignParam !== '') {
            if (is_numeric($campaignParam)) {
                $selectedCampaign = $campaignRepository->find((int) $campaignParam);
            } else {
                $selectedCampaign = $campaignRepository->findOneBy(['slug' => (string) $campaignParam]);
            }
        }

        if ($missionParam !== null && $missionParam !== '') {
            if (is_numeric($missionParam)) {
                $selectedReport = $reportRepository->find((int) $missionParam);
            } else {
                $selectedReport = $reportRepository->findOneBy(['code' => (string) $missionParam]);
            }

            if ($selectedReport !== null && $selectedCampaign === null && $selectedReport->getCampaign() !== null) {
                $selectedCampaign = $selectedReport->getCampaign();
            }
        }

        if ($selectedReport !== null) {
            $images = $imageRepository->findByFieldReport($selectedReport);
        } elseif ($selectedCampaign !== null) {
            $images = $imageRepository->findByCampaign($selectedCampaign);
        } else {
            $images = $imageRepository->findAllOrdered();
        }

        // Collect missions for the selected campaign to allow subfiltering
        $campaignMissions = [];
        if ($selectedCampaign !== null) {
            $campaignMissions = $reportRepository->findBy(['campaign' => $selectedCampaign], ['startedAt' => 'DESC']);
        }

        // Group images by mission, or by upload date if not linked to a mission (Google Photos style)
        $groupedSections = [];
        $globalIndex = 0;

        foreach ($images as $img) {
            $report = $img->getFieldReport();

            if ($report !== null) {
                $groupKey = 'report_' . $report->getId();
                if (!isset($groupedSections[$groupKey])) {
                    $groupedSections[$groupKey] = [
                        'type' => 'mission',
                        'title' => $report->getMissionName(),
                        'date' => $report->getStartedAt(),
                        'world' => $report->getWorldDisplayName() ?: $report->getWorld(),
                        'campaign' => $report->getCampaign()?->getName(),
                        'campaignSlug' => $report->getCampaign()?->getSlug(),
                        'reportCode' => $report->getCode(),
                        'items' => [],
                    ];
                }
                $groupedSections[$groupKey]['items'][] = [
                    'image' => $img,
                    'globalIndex' => $globalIndex++,
                ];
            } else {
                $dayKey = $img->getCreatedAt()->format('Y-m-d');
                $campaignKey = $img->getEffectiveCampaign()?->getId() ?? 'general';
                $groupKey = 'day_' . $dayKey . '_' . $campaignKey;
                if (!isset($groupedSections[$groupKey])) {
                    $groupedSections[$groupKey] = [
                        'type' => 'day',
                        'title' => $img->getEffectiveCampaign() ? $img->getEffectiveCampaign()->getName() : 'Galeria general',
                        'date' => $img->getCreatedAt(),
                        'world' => null,
                        'campaign' => $img->getEffectiveCampaign()?->getName(),
                        'campaignSlug' => $img->getEffectiveCampaign()?->getSlug(),
                        'reportCode' => null,
                        'items' => [],
                    ];
                }
                $groupedSections[$groupKey]['items'][] = [
                    'image' => $img,
                    'globalIndex' => $globalIndex++,
                ];
            }
        }

        // Sort sections chronologically (most recent first)
        uasort($groupedSections, static function (array $a, array $b): int {
            return $b['date'] <=> $a['date'];
        });

        return $this->render('@CcCompositePlugin/frontend/gallery/index.html.twig', [
            'campaigns' => $campaigns,
            'selectedCampaign' => $selectedCampaign,
            'selectedReport' => $selectedReport,
            'campaignMissions' => $campaignMissions,
            'images' => $images,
            'sections' => $groupedSections,
        ]);
    }
}
