<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Admin\Controller;

use ArniePalau\CcComposite\Entity\AwardPlacement;
use ArniePalau\CcComposite\Enum\AwardCategory;
use ArniePalau\CcComposite\Repository\AwardPlacementRepository;
use ArniePalau\CcComposite\Service\RegenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\PerscomPlugin\Perscom\Repository\AwardRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('awards', name: 'awards')]
final class AwardPlacementController extends AbstractController
{
    public function __construct(
        private readonly AwardRepository $awardRepository,
        private readonly AwardPlacementRepository $placementRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RegenerationService $regenerationService,
    ) {
    }

    #[Route('', name: '_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('cc-composite-awards', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $submitted = $request->request->all('placements');
            foreach ($this->awardRepository->findAll() as $award) {
                $placement = $this->placementRepository->findForAward($award);
                $category = AwardCategory::tryFrom((string) ($submitted[$award->getId()] ?? ''));
                if ($category === null) {
                    if ($placement !== null) {
                        $this->entityManager->remove($placement);
                    }
                    continue;
                }
                $placement ??= new AwardPlacement();
                $placement->setAward($award);
                $placement->setCategory($category);
                $this->entityManager->persist($placement);
            }
            $this->entityManager->flush();
            $this->regenerationService->regenerateAll();
            $this->addFlash('success', 'Award layout saved and composites regenerated.');
            return $this->redirectToRoute('cc_composite_admin_awards_index');
        }

        $placements = [];
        foreach ($this->placementRepository->findAll() as $placement) {
            $placements[$placement->getAward()->getId()] = $placement->getCategory()->value;
        }

        return $this->render('@CcCompositePlugin/admin/awards/index.html.twig', [
            'awards' => $this->awardRepository->findAll(),
            'categories' => AwardCategory::cases(),
            'placements' => $placements,
        ]);
    }
}
