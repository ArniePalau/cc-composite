<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use ArniePalau\CcComposite\Entity\CompositeSelection;
use ArniePalau\CcComposite\Enum\LayerCategory;
use ArniePalau\CcComposite\Repository\CompositeDefaultRepository;
use ArniePalau\CcComposite\Repository\CompositeSelectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;

class SelectionService
{
    public function __construct(
        private readonly CompositeSelectionRepository $selectionRepository,
        private readonly CompositeDefaultRepository $defaultRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getOrCreate(PerscomUser $user): CompositeSelection
    {
        $selection = $this->selectionRepository->findForUser($user);
        if ($selection !== null) {
            return $selection;
        }

        $selection = new CompositeSelection();
        $selection->setUser($user);
        $this->entityManager->persist($selection);

        return $selection;
    }

    public function getExplicitLayers(PerscomUser $user): array
    {
        return $this->selectionRepository->findForUser($user)?->getLayers() ?? [];
    }

    public function getInheritedLayers(PerscomUser $user): array
    {
        $global = $this->defaultRepository->findGlobal()?->getLayers() ?? [];
        $unit = $this->defaultRepository->findForUnit($user->getUnit())?->getLayers() ?? [];

        return array_replace($global, $unit);
    }

    public function resolveLayers(PerscomUser $user, ?CompositeSelection $selection = null): array
    {
        $selection ??= $this->selectionRepository->findForUser($user);
        $resolved = array_replace($this->getInheritedLayers($user), $selection?->getLayers() ?? []);
        $validKeys = array_map(static fn (LayerCategory $category): string => $category->value, LayerCategory::cases());

        return array_intersect_key($resolved, array_flip($validKeys));
    }

    public function save(PerscomUser $user, ?array $layers): CompositeSelection
    {
        $selection = $this->getOrCreate($user);
        $selection->setLayers($layers);
        $this->entityManager->persist($selection);

        return $selection;
    }
}
