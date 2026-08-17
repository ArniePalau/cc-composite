<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Controller;

use ArniePalau\CcComposite\Entity\FieldReport;
use ArniePalau\CcComposite\Repository\FieldReportRepository;
use ArniePalau\CcComposite\Repository\CampaignRepository;
use ArniePalau\CcComposite\Service\FieldReportPlayerProfileResolver;
use ArniePalau\CcComposite\Service\AtlasMapCache;
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

    #[Route('/{code}', name: 'show', requirements: ['code' => '[A-Za-z0-9_-]+'], methods: ['GET'])]
    public function show(string $code, FieldReportRepository $repository, FieldReportPlayerProfileResolver $profileResolver, AtlasMapCache $mapCache, EntityManagerInterface $entityManager): Response
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

        return $this->render('@CcCompositePlugin/frontend/field_report/show.html.twig', [
            'report' => $report,
            'data' => $payload,
            'playerProfiles' => $profileResolver->resolve($payload),
        ]);
    }
}
