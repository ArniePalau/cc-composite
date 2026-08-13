<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Admin\Controller;

use ArniePalau\CcComposite\Entity\CompositeDefault;
use ArniePalau\CcComposite\Form\AppearanceType;
use ArniePalau\CcComposite\Repository\CompositeDefaultRepository;
use ArniePalau\CcComposite\Service\RegenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\PerscomPlugin\Perscom\Entity\Unit;
use Forumify\PerscomPlugin\Perscom\Repository\PerscomUserRepository;
use Forumify\PerscomPlugin\Perscom\Repository\UnitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('defaults', name: 'defaults')]
final class DefaultsController extends AbstractController
{
    public function __construct(
        private readonly CompositeDefaultRepository $defaultRepository,
        private readonly UnitRepository $unitRepository,
        private readonly PerscomUserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RegenerationService $regenerationService,
    ) {
    }

    #[Route('', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $this->denyAccessUnlessGranted('cc_composite.admin.manage');
        $unitId = $request->query->getInt('unit');
        /** @var Unit|null $unit */
        $unit = $unitId > 0 ? $this->unitRepository->find($unitId) : null;
        $default = $unit === null ? $this->defaultRepository->findGlobal() : $this->defaultRepository->findForUnit($unit);
        if ($default === null) {
            $default = new CompositeDefault();
            $default->setUnit($unit);
            $this->entityManager->persist($default);
        }

        $form = $this->createFormBuilder(['appearance' => $default->getLayers()])
            ->add('appearance', AppearanceType::class, [
                'label' => false,
                'admin_context' => true,
                'empty_label' => 'None',
                'inherited_layers' => [],
            ])
            ->add('save', SubmitType::class, ['label' => 'Save defaults'])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $default->setLayers($form->get('appearance')->getData());
            $this->entityManager->flush();
            if ($unit === null) {
                $this->regenerationService->regenerateAll();
            } else {
                $this->regenerationService->regenerateMany($this->userRepository->findBy(['unit' => $unit]));
            }
            $this->addFlash('success', 'Composite defaults saved.');
            return $this->redirectToRoute('cc_composite_admin_defaults_edit', $unit ? ['unit' => $unit->getId()] : []);
        }

        return $this->render('@CcCompositePlugin/admin/defaults/edit.html.twig', [
            'form' => $form->createView(),
            'selectedUnit' => $unit,
            'units' => $this->unitRepository->findBy([], ['name' => 'ASC']),
        ]);
    }
}
