<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Admin\Controller;

use ArniePalau\CcComposite\Entity\CompositeLayer;
use ArniePalau\CcComposite\Enum\LayerCategory;
use ArniePalau\CcComposite\Form\CompositeLayerType;
use ArniePalau\CcComposite\Repository\CompositeDefaultRepository;
use ArniePalau\CcComposite\Repository\CompositeLayerRepository;
use ArniePalau\CcComposite\Repository\CompositeSelectionRepository;
use ArniePalau\CcComposite\Service\RegenerationService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('layers', name: 'layers')]
final class LayerController extends AbstractController
{
    public function __construct(
        private readonly FilesystemOperator $layerStorage,
        private readonly CompositeLayerRepository $layerRepository,
        private readonly CompositeSelectionRepository $selectionRepository,
        private readonly CompositeDefaultRepository $defaultRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RegenerationService $regenerationService,
        private readonly SluggerInterface $slugger,
    ) {
    }

    #[Route('', name: '_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('cc_composite.admin.view');
        $layers = [];
        foreach (LayerCategory::cases() as $category) {
            $layers[$category->value] = $this->layerRepository->findForCategory($category);
        }

        return $this->render('@CcCompositePlugin/admin/layers/index.html.twig', [
            'categories' => LayerCategory::cases(),
            'layers' => $layers,
        ]);
    }

    #[Route('/upload', name: '_upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');
        if (!$this->isCsrfTokenValid('cc-composite-layer-upload', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $category = LayerCategory::tryFrom((string) $request->request->get('category'));
        $file = $request->files->get('file');
        if ($category === null || !$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('error', 'Invalid layer upload.');
            return $this->redirectToRoute('cc_composite_admin_layers_index');
        }

        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());
        if (!in_array($extension, ['png', 'jpg', 'jpeg'], true) || $file->getSize() > 10 * 1024 * 1024) {
            $this->addFlash('error', 'Layers must be PNG or JPEG images smaller than 10 MB.');
            return $this->redirectToRoute('cc_composite_admin_layers_index');
        }

        $dimensions = @getimagesize($file->getPathname());
        if ($dimensions === false || $dimensions[0] !== 1080 || $dimensions[1] !== 530) {
            $this->addFlash('error', 'Layers must be exactly 1080 × 530 pixels.');
            return $this->redirectToRoute('cc_composite_admin_layers_index');
        }

        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $filename = strtolower((string) $this->slugger->slug($baseName)) . '.' . $extension;
        $layer = $this->layerRepository->findOneByCategoryAndFilename($category, $filename) ?? new CompositeLayer();
        $layer->setCategory($category);
        $layer->setFilename($filename);

        $stream = fopen($file->getPathname(), 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Unable to read the uploaded layer.');
        }
        try {
            if ($this->layerStorage->fileExists($layer->getAssetPath())) {
                $this->layerStorage->delete($layer->getAssetPath());
            }
            $this->layerStorage->writeStream($layer->getAssetPath(), $stream);
        } finally {
            fclose($stream);
        }

        $this->entityManager->persist($layer);
        $this->entityManager->flush();
        $this->regenerationService->regenerateAll();
        $this->addFlash('success', 'Layer uploaded and composites regenerated.');

        return $this->redirectToRoute('cc_composite_admin_layers_index');
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(CompositeLayer $layer, Request $request): Response
    {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');
        $form = $this->createForm(CompositeLayerType::class, $layer);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Layer permissions saved.');
            return $this->redirectToRoute('cc_composite_admin_layers_index');
        }

        return $this->render('@CcCompositePlugin/admin/layers/edit.html.twig', [
            'layer' => $layer,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: '_delete', methods: ['POST'])]
    public function delete(CompositeLayer $layer, Request $request): Response
    {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');
        if (!$this->isCsrfTokenValid('cc-composite-layer-delete-' . $layer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($this->layerStorage->fileExists($layer->getAssetPath())) {
            $this->layerStorage->delete($layer->getAssetPath());
        }
        $this->removeLayerFromSelections($layer);
        $this->entityManager->remove($layer);
        $this->entityManager->flush();
        $this->regenerationService->regenerateAll();
        $this->addFlash('success', 'Layer deleted and composites regenerated.');

        return $this->redirectToRoute('cc_composite_admin_layers_index');
    }

    private function removeLayerFromSelections(CompositeLayer $layer): void
    {
        $key = $layer->getCategory()->value;
        foreach ([$this->selectionRepository->findAll(), $this->defaultRepository->findAll()] as $records) {
            foreach ($records as $record) {
                $layers = $record->getLayers();
                if (($layers[$key] ?? null) !== $layer->getFilename()) {
                    continue;
                }
                unset($layers[$key]);
                $record->setLayers($layers);
            }
        }
    }
}
