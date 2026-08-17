<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Controller;

use ArniePalau\CcComposite\Entity\FieldReport;
use ArniePalau\CcComposite\Repository\FieldReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/field-reports', name: 'cc_composite_field_reports_')]
final class FieldReportController extends AbstractController
{
    private const int PAGE_SIZE = 6;

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, FieldReportRepository $repository): Response
    {
        $total = $repository->countAll();
        $lastPage = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($lastPage, max(1, $request->query->getInt('page', 1)));

        return $this->render('@CcCompositePlugin/frontend/field_report/index.html.twig', [
            'reports' => $repository->findPage($page, self::PAGE_SIZE),
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
        ]);
    }

    #[Route('/{code}', name: 'show', requirements: ['code' => '[A-Za-z0-9_-]+'], methods: ['GET'])]
    public function show(string $code, FieldReportRepository $repository): Response
    {
        $report = $repository->findOneBy(['code' => $code]);
        if (!$report instanceof FieldReport) {
            throw $this->createNotFoundException('Field report not found.');
        }

        return $this->render('@CcCompositePlugin/frontend/field_report/show.html.twig', [
            'report' => $report,
            'data' => $report->getPayload(),
        ]);
    }
}
