<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use ArniePalau\CcComposite\Entity\CompositeSelection;
use ArniePalau\CcComposite\Enum\AwardCategory;
use ArniePalau\CcComposite\Enum\LayerCategory;
use ArniePalau\CcComposite\Repository\AwardPlacementRepository;
use ArniePalau\CcComposite\Repository\CompositeLayerRepository;
use Exception;
use Forumify\PerscomPlugin\Perscom\Entity\Award;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Forumify\PerscomPlugin\Perscom\Repository\AwardRecordRepository;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;

final class CompositeGenerator
{
    public const int CANVAS_WIDTH = 1080;
    public const int CANVAS_HEIGHT = 530;

    private const int RIBBON_WIDTH = 80;
    private const int RIBBON_HEIGHT = 28;
    private const int RIBBONS_PER_ROW = 3;
    private const int GRID_GAP = 4;
    private const string OUTPUT_PREFIX = 'user/uniform/cc-composite/';

    public function __construct(
        private readonly FilesystemOperator $layerStorage,
        private readonly FilesystemOperator $perscomAssetStorage,
        private readonly CompositeLayerRepository $layerRepository,
        private readonly AwardPlacementRepository $placementRepository,
        private readonly AwardRecordRepository $awardRecordRepository,
        private readonly SelectionService $selectionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generate(PerscomUser $user, CompositeSelection $selection): bool
    {
        if (!extension_loaded('imagick')) {
            $this->logger->error('CC Composite requires the Imagick PHP extension.');
            return false;
        }

        $canvas = new Imagick();
        try {
            $canvas->newImage(self::CANVAS_WIDTH, self::CANVAS_HEIGHT, new ImagickPixel('transparent'));
            $canvas->setImageFormat('png');

            $this->renderLayers($user, $selection, $canvas);
            $this->renderAwards($user, $canvas);

            $hash = substr(hash('sha256', $canvas->getImageBlob()), 0, 16);
            $userId = $user->getId();

            $outputPath = sprintf('%s%d_%s.png', self::OUTPUT_PREFIX, $userId, $hash);
            if (!$this->perscomAssetStorage->fileExists($outputPath)) {
                $this->perscomAssetStorage->write($outputPath, $canvas->getImageBlob());
            }

            $oldPath = $selection->getGeneratedPath();
            $selection->setGeneratedPath($outputPath);
            $user->setUniform($outputPath);
            $user->setUniformDirty(true);

            if ($oldPath !== null
                && $oldPath !== $outputPath
                && str_starts_with($oldPath, self::OUTPUT_PREFIX)
                && $this->perscomAssetStorage->fileExists($oldPath)
            ) {
                $this->perscomAssetStorage->delete($oldPath);
            }

            return true;
        } catch (Exception $exception) {
            $this->logger->error('Failed to generate PERSCOM composite.', [
                'user' => $user->getId(),
                'exception' => $exception,
            ]);
            return false;
        } finally {
            $canvas->clear();
            $canvas->destroy();
        }
    }

    private function renderLayers(PerscomUser $user, CompositeSelection $selection, Imagick $canvas): void
    {
        $resolved = $this->selectionService->resolveLayers($user, $selection);
        foreach (LayerCategory::cases() as $category) {
            $filename = $resolved[$category->value] ?? null;
            if (!is_string($filename) || $filename === '') {
                continue;
            }

            $layer = $this->layerRepository->findOneByCategoryAndFilename($category, $filename);
            if ($layer === null || !$this->layerStorage->fileExists($layer->getAssetPath())) {
                $this->logger->warning('Composite layer is missing.', [
                    'category' => $category->value,
                    'filename' => $filename,
                ]);
                continue;
            }

            $this->compositeBlob($canvas, $this->layerStorage->read($layer->getAssetPath()), 0, 0);
        }
    }

    private function renderAwards(PerscomUser $user, Imagick $canvas): void
    {
        $records = $this->awardRecordRepository->findBy(['user' => $user]);
        if ($records === []) {
            return;
        }

        usort($records, static fn ($a, $b): int => $a->getAward()->getPosition() <=> $b->getAward()->getPosition());

        /** @var array<string, array<int, array{award: Award, count: int}>> $grouped */
        $grouped = [];
        foreach (AwardCategory::cases() as $category) {
            $grouped[$category->value] = [];
        }

        foreach ($records as $record) {
            $award = $record->getAward();
            $placement = $this->placementRepository->findForAward($award);
            if ($placement === null) {
                continue;
            }

            $key = $award->getId() ?? spl_object_id($award);
            $category = $placement->getCategory()->value;
            if (!isset($grouped[$category][$key])) {
                $grouped[$category][$key] = ['award' => $award, 'count' => 0];
            }
            ++$grouped[$category][$key]['count'];
        }

        foreach ([true, false] as $leftPanel) {
            $currentY = 40;
            foreach (AwardCategory::cases() as $category) {
                if ($category->isLeftPanel() !== $leftPanel || $grouped[$category->value] === []) {
                    continue;
                }

                $this->drawCategoryTitle($canvas, $category, $category->panelX(), $currentY);
                $currentY += 15;
                $this->renderRibbonGrid($canvas, $grouped[$category->value], $category->panelX(), $currentY);
                $rows = (int) ceil(count($grouped[$category->value]) / self::RIBBONS_PER_ROW);
                $currentY += $rows * (self::RIBBON_HEIGHT + self::GRID_GAP) + 15;
            }
        }
    }

    private function drawCategoryTitle(Imagick $canvas, AwardCategory $category, int $x, int $y): void
    {
        $draw = new ImagickDraw();
        $font = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        if (is_file($font)) {
            $draw->setFont($font);
        }
        $draw->setFillColor(new ImagickPixel('white'));
        $draw->setFontSize(10);
        $draw->setTextAlignment(Imagick::ALIGN_LEFT);
        $canvas->annotateImage($draw, $x, $y + 10, 0, $category->label());
    }

    /** @param array<int, array{award: Award, count: int}> $awards */
    private function renderRibbonGrid(Imagick $canvas, array $awards, int $startX, int $startY): void
    {
        $index = 0;
        foreach ($awards as $item) {
            $imagePath = $item['award']->getImage();
            if ($imagePath === null || !$this->perscomAssetStorage->fileExists($imagePath)) {
                ++$index;
                continue;
            }

            try {
                $ribbon = new Imagick();
                $ribbon->readImageBlob($this->perscomAssetStorage->read($imagePath));
                $ribbon->resizeImage(self::RIBBON_WIDTH, self::RIBBON_HEIGHT, Imagick::FILTER_LANCZOS, 1, true);

                if ($item['count'] > 1) {
                    $this->drawAwardCount($ribbon, $item['count']);
                }

                $column = $index % self::RIBBONS_PER_ROW;
                $row = intdiv($index, self::RIBBONS_PER_ROW);
                $x = $startX + $column * (self::RIBBON_WIDTH + self::GRID_GAP);
                $y = $startY + $row * (self::RIBBON_HEIGHT + self::GRID_GAP);
                $x += (int) ((self::RIBBON_WIDTH - $ribbon->getImageWidth()) / 2);
                $y += (int) ((self::RIBBON_HEIGHT - $ribbon->getImageHeight()) / 2);
                $canvas->compositeImage($ribbon, Imagick::COMPOSITE_OVER, $x, $y);
            } catch (Exception $exception) {
                $this->logger->warning('Unable to render award image.', [
                    'path' => $imagePath,
                    'exception' => $exception,
                ]);
            } finally {
                if (isset($ribbon)) {
                    $ribbon->clear();
                    $ribbon->destroy();
                }
            }

            ++$index;
        }
    }

    private function drawAwardCount(Imagick $image, int $count): void
    {
        $draw = new ImagickDraw();
        $font = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        if (is_file($font)) {
            $draw->setFont($font);
        }
        $draw->setFillColor(new ImagickPixel('white'));
        $draw->setStrokeColor(new ImagickPixel('black'));
        $draw->setStrokeWidth(1);
        $draw->setFontSize(16);
        $draw->setGravity(Imagick::GRAVITY_SOUTHEAST);
        $image->annotateImage($draw, 4, 2, 0, 'x' . $count);
    }

    private function compositeBlob(Imagick $canvas, string $blob, int $x, int $y): void
    {
        $image = new Imagick();
        try {
            $image->readImageBlob($blob);
            if ($image->getImageWidth() !== self::CANVAS_WIDTH || $image->getImageHeight() !== self::CANVAS_HEIGHT) {
                $image->resizeImage(self::CANVAS_WIDTH, self::CANVAS_HEIGHT, Imagick::FILTER_LANCZOS, 1);
            }
            $canvas->compositeImage($image, Imagick::COMPOSITE_OVER, $x, $y);
        } finally {
            $image->clear();
            $image->destroy();
        }
    }
}
