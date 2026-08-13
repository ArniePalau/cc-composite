<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Command;

use ArniePalau\CcComposite\Entity\AwardPlacement;
use ArniePalau\CcComposite\Entity\CompositeDefault;
use ArniePalau\CcComposite\Entity\CompositeLayer;
use ArniePalau\CcComposite\Entity\CompositeSelection;
use ArniePalau\CcComposite\Enum\AwardCategory;
use ArniePalau\CcComposite\Enum\LayerCategory;
use ArniePalau\CcComposite\Repository\AwardPlacementRepository;
use ArniePalau\CcComposite\Repository\CompositeDefaultRepository;
use ArniePalau\CcComposite\Repository\CompositeLayerRepository;
use ArniePalau\CcComposite\Repository\CompositeSelectionRepository;
use ArniePalau\CcComposite\Service\RegenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Forumify\PerscomPlugin\Perscom\Repository\AwardRepository;
use Forumify\PerscomPlugin\Perscom\Repository\PerscomUserRepository;
use Forumify\PerscomPlugin\Perscom\Repository\RankRepository;
use Forumify\PerscomPlugin\Perscom\Repository\UnitRepository;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'cc-composite:import-legacy', description: 'Import the bundled Cavallers del Cel layer library and mappings.')]
final class ImportLegacyLibraryCommand extends Command
{
    private readonly string $resourceDirectory;

    public function __construct(
        private readonly FilesystemOperator $layerStorage,
        private readonly CompositeLayerRepository $layerRepository,
        private readonly CompositeSelectionRepository $selectionRepository,
        private readonly CompositeDefaultRepository $defaultRepository,
        private readonly AwardPlacementRepository $placementRepository,
        private readonly PerscomUserRepository $userRepository,
        private readonly UnitRepository $unitRepository,
        private readonly RankRepository $rankRepository,
        private readonly AwardRepository $awardRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RegenerationService $regenerationService,
    ) {
        parent::__construct();
        $this->resourceDirectory = dirname(__DIR__, 2) . '/resources/legacy';
    }

    protected function configure(): void
    {
        $this
            ->addOption('overwrite-assets', null, InputOption::VALUE_NONE, 'Replace layer files already in storage')
            ->addOption('generate', null, InputOption::VALUE_NONE, 'Generate all composites after importing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $manifest = $this->readManifest();
        $stats = ['layers' => 0, 'selections' => 0, 'defaults' => 0, 'placements' => 0, 'unmatched' => []];

        foreach ($manifest['layers'] as $data) {
            $category = LayerCategory::from($data['category']);
            $source = $this->resourceDirectory . '/layers/' . $category->directory() . '/' . $data['filename'];
            if (!is_file($source)) {
                $stats['unmatched'][] = 'Missing bundled asset: ' . $source;
                continue;
            }

            $layer = $this->layerRepository->findOneByCategoryAndFilename($category, $data['filename']) ?? new CompositeLayer();
            $layer->setCategory($category);
            $layer->setFilename($data['filename']);
            $this->applyPermissions($layer, $data, $stats['unmatched']);
            $this->entityManager->persist($layer);

            $target = $layer->getAssetPath();
            if ($input->getOption('overwrite-assets') || !$this->layerStorage->fileExists($target)) {
                if ($this->layerStorage->fileExists($target)) {
                    $this->layerStorage->delete($target);
                }
                $stream = fopen($source, 'rb');
                if ($stream === false) {
                    throw new \RuntimeException('Unable to read ' . $source);
                }
                try {
                    $this->layerStorage->writeStream($target, $stream);
                } finally {
                    fclose($stream);
                }
            }
            ++$stats['layers'];
        }
        $this->entityManager->flush();

        foreach ($manifest['defaults'] as $unitName => $layers) {
            $unit = $unitName === 'global' ? null : $this->findByName($this->unitRepository->findAll(), $unitName);
            if ($unitName !== 'global' && $unit === null) {
                $stats['unmatched'][] = 'Unit default: ' . $unitName;
                continue;
            }
            $default = $unit === null ? $this->defaultRepository->findGlobal() : $this->defaultRepository->findForUnit($unit);
            $default ??= new CompositeDefault();
            $default->setUnit($unit);
            $default->setLayers($layers);
            $this->entityManager->persist($default);
            ++$stats['defaults'];
        }

        foreach ($manifest['selections'] as $userName => $layers) {
            /** @var PerscomUser|null $user */
            $user = $this->findByName($this->userRepository->findAll(), $userName);
            if ($user === null) {
                $stats['unmatched'][] = 'Soldier selection: ' . $userName;
                continue;
            }
            $selection = $this->selectionRepository->findForUser($user) ?? new CompositeSelection();
            $selection->setUser($user);
            $selection->setLayers($layers);
            $this->entityManager->persist($selection);
            ++$stats['selections'];
        }

        foreach ($manifest['award_categories'] as $awardName => $categoryValue) {
            $award = $this->findByName($this->awardRepository->findAll(), $awardName);
            if ($award === null) {
                $stats['unmatched'][] = 'Award category: ' . $awardName;
                continue;
            }
            $placement = $this->placementRepository->findForAward($award) ?? new AwardPlacement();
            $placement->setAward($award);
            $placement->setCategory(AwardCategory::from($categoryValue));
            $this->entityManager->persist($placement);
            ++$stats['placements'];
        }
        $this->entityManager->flush();

        $io->success(sprintf(
            'Imported %d layers, %d defaults, %d soldier selections and %d award placements.',
            $stats['layers'],
            $stats['defaults'],
            $stats['selections'],
            $stats['placements'],
        ));
        if ($stats['unmatched'] !== []) {
            $io->warning(array_merge(['These names were not found and were safely skipped:'], $stats['unmatched']));
        }
        if ($input->getOption('generate')) {
            $io->note(sprintf('Generated %d composite image(s).', $this->regenerationService->regenerateAll()));
        }

        return Command::SUCCESS;
    }

    private function readManifest(): array
    {
        $json = file_get_contents($this->resourceDirectory . '/manifest.json');
        if ($json === false) {
            throw new \RuntimeException('The bundled legacy manifest is missing.');
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param list<string> $unmatched */
    private function applyPermissions(CompositeLayer $layer, array $data, array &$unmatched): void
    {
        foreach ($layer->getAllowedRanks()->toArray() as $rank) {
            $layer->removeAllowedRank($rank);
        }
        foreach ($layer->getAllowedUnits()->toArray() as $unit) {
            $layer->removeAllowedUnit($unit);
        }
        foreach ($layer->getAllowedUsers()->toArray() as $user) {
            $layer->removeAllowedUser($user);
        }

        foreach ($data['allowed_ranks'] ?? [] as $name) {
            $rank = $this->findByName($this->rankRepository->findAll(), $name);
            if ($rank === null) {
                $unmatched[] = 'Layer rank: ' . $name;
            } else {
                $layer->addAllowedRank($rank);
            }
        }
        foreach ($data['allowed_units'] ?? [] as $name) {
            $unit = $this->findByName($this->unitRepository->findAll(), $name);
            if ($unit === null) {
                $unmatched[] = 'Layer unit: ' . $name;
            } else {
                $layer->addAllowedUnit($unit);
            }
        }
        foreach ($data['allowed_users'] ?? [] as $name) {
            $user = $this->findByName($this->userRepository->findAll(), $name);
            if ($user === null) {
                $unmatched[] = 'Layer soldier: ' . $name;
            } else {
                $layer->addAllowedUser($user);
            }
        }
    }

    private function findByName(iterable $entities, string $name): ?object
    {
        $needle = $this->normalizeName($name);
        foreach ($entities as $entity) {
            if (method_exists($entity, 'getName') && $this->normalizeName($entity->getName()) === $needle) {
                return $entity;
            }
        }

        return null;
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return $name;
    }
}
