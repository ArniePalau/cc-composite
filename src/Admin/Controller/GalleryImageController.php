<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Admin\Controller;

use ArniePalau\CcComposite\Entity\Campaign;
use ArniePalau\CcComposite\Entity\GalleryImage;
use ArniePalau\CcComposite\Form\GalleryImageType;
use ArniePalau\CcComposite\Repository\CampaignRepository;
use ArniePalau\CcComposite\Repository\FieldReportRepository;
use ArniePalau\CcComposite\Repository\GalleryImageRepository;
use ArniePalau\CcComposite\Service\ImageOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('gallery-images', name: 'gallery_images')]
final class GalleryImageController extends AbstractController
{
    #[Route('', name: '_index', methods: ['GET'])]
    public function index(
        Request $request,
        GalleryImageRepository $imageRepository,
        CampaignRepository $campaignRepository,
        FieldReportRepository $reportRepository
    ): Response {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');

        $campaignId = $request->query->getInt('campaign');
        $reportId = $request->query->getInt('report');

        $criteria = [];
        if ($campaignId > 0) {
            $campaign = $campaignRepository->find($campaignId);
            if ($campaign) {
                $images = $imageRepository->findByCampaign($campaign);
            } else {
                $images = $imageRepository->findAllOrdered();
            }
        } elseif ($reportId > 0) {
            $report = $reportRepository->find($reportId);
            if ($report) {
                $images = $imageRepository->findByFieldReport($report);
            } else {
                $images = $imageRepository->findAllOrdered();
            }
        } else {
            $images = $imageRepository->findAllOrdered();
        }

        return $this->render('@CcCompositePlugin/admin/gallery_images/index.html.twig', [
            'images' => $images,
            'campaigns' => $campaignRepository->findBy([], ['name' => 'ASC']),
            'selectedCampaignId' => $campaignId,
            'selectedReportId' => $reportId,
        ]);
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ImageOptimizationService $optimizationService
    ): Response {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');

        $galleryImage = new GalleryImage();
        $form = $this->createForm(GalleryImageType::class, $galleryImage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('image')->getData();
            if ($file instanceof UploadedFile) {
                $campaignSlug = $galleryImage->getEffectiveCampaign()?->getSlug() ?? 'gallery';
                $path = $optimizationService->optimizeAndSave($file, $campaignSlug);
                $galleryImage->setImagePath($path);

                $entityManager->persist($galleryImage);
                $entityManager->flush();
                $this->addFlash('success', 'Fotografia afegida a la galeria amb èxit.');

                return $this->redirectToRoute('cc_composite_admin_gallery_images_index');
            }
        }

        return $this->render('@CcCompositePlugin/admin/gallery_images/edit.html.twig', [
            'form' => $form->createView(),
            'galleryImage' => null,
        ]);
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(
        GalleryImage $galleryImage,
        Request $request,
        EntityManagerInterface $entityManager,
        ImageOptimizationService $optimizationService
    ): Response {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');

        $form = $this->createForm(GalleryImageType::class, $galleryImage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('image')->getData();
            if ($file instanceof UploadedFile) {
                $oldPath = $galleryImage->getImagePath();
                $campaignSlug = $galleryImage->getEffectiveCampaign()?->getSlug() ?? 'gallery';
                $newPath = $optimizationService->optimizeAndSave($file, $campaignSlug);
                $galleryImage->setImagePath($newPath);

                if ($oldPath && $oldPath !== $newPath) {
                    $optimizationService->delete($oldPath);
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Fotografia actualitzada.');

            return $this->redirectToRoute('cc_composite_admin_gallery_images_index');
        }

        return $this->render('@CcCompositePlugin/admin/gallery_images/edit.html.twig', [
            'form' => $form->createView(),
            'galleryImage' => $galleryImage,
        ]);
    }

    #[Route('/{id}/delete', name: '_delete', methods: ['POST'])]
    public function delete(
        GalleryImage $galleryImage,
        Request $request,
        EntityManagerInterface $entityManager,
        ImageOptimizationService $optimizationService
    ): Response {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');

        if (!$this->isCsrfTokenValid('cc-composite-gallery-delete-' . $galleryImage->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invàlid.');
        }

        $path = $galleryImage->getImagePath();
        $entityManager->remove($galleryImage);
        $entityManager->flush();

        if ($path) {
            $optimizationService->delete($path);
        }

        $this->addFlash('success', 'Fotografia eliminada correctament.');

        return $this->redirectToRoute('cc_composite_admin_gallery_images_index');
    }
}
