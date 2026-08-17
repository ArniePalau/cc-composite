<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Admin\Controller;

use ArniePalau\CcComposite\Entity\FieldReport;
use ArniePalau\CcComposite\Repository\FieldReportRepository;
use ArniePalau\CcComposite\Service\FieldReportImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('field-reports', name: 'field_reports')]
final class FieldReportController extends AbstractController
{
    #[Route('', name: '_index', methods: ['GET'])]
    public function index(FieldReportRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');

        return $this->render('@CcCompositePlugin/admin/field_reports/index.html.twig', [
            'reports' => $repository->findBy([], ['startedAt' => 'DESC']),
        ]);
    }

    #[Route('/import', name: '_import', methods: ['POST'])]
    public function import(Request $request, FieldReportImporter $importer): Response
    {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');
        if (!$this->isCsrfTokenValid('cc-composite-field-report-import', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $report = $importer->import((string) $request->request->get('url'));
            $this->addFlash('success', sprintf('Imported field report "%s".', $report->getMissionName()));
        } catch (Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('cc_composite_admin_field_reports_index');
    }

    #[Route('/{id}/refresh', name: '_refresh', methods: ['POST'])]
    public function refresh(FieldReport $report, Request $request, FieldReportImporter $importer): Response
    {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');
        if (!$this->isCsrfTokenValid('cc-composite-field-report-refresh-' . $report->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $importer->import($report->getSourceUrl());
            $this->addFlash('success', sprintf('Refreshed field report "%s".', $report->getMissionName()));
        } catch (Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('cc_composite_admin_field_reports_index');
    }

    #[Route('/{id}/delete', name: '_delete', methods: ['POST'])]
    public function delete(FieldReport $report, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');
        if (!$this->isCsrfTokenValid('cc-composite-field-report-delete-' . $report->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $entityManager->remove($report);
        $entityManager->flush();
        $this->addFlash('success', 'Field report deleted. Its shared map cache was preserved.');

        return $this->redirectToRoute('cc_composite_admin_field_reports_index');
    }
}
