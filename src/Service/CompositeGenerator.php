<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use ArniePalau\CcComposite\Entity\CompositeSelection;
use ArniePalau\CcComposite\Enum\AwardCategory;
use ArniePalau\CcComposite\Enum\LayerCategory;
use ArniePalau\CcComposite\Repository\AwardPlacementRepository;
use ArniePalau\CcComposite\Repository\CompositeLayerRepository;
use Forumify\PerscomPlugin\Perscom\Entity\Award;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Forumify\PerscomPlugin\Perscom\Repository\AwardRecordRepository;
use GdImage;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Throwable;

final class CompositeGenerator
{
    public const int CANVAS_WIDTH = 1080;
    public const int CANVAS_HEIGHT = 530;

    private const int RIBBON_WIDTH = 80;
    private const int RIBBON_HEIGHT = 28;
    private const int RIBBONS_PER_ROW = 3;
    private const int GRID_GAP = 4;
    private const string OUTPUT_PREFIX = 'user/uniform/cc-composite/';
    private const string FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

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
        if (!extension_loaded('gd')) {
            $this->logger->error('CC Composite requires the GD PHP extension.');
            return false;
        }

        $canvas = $this->createTransparentImage(self::CANVAS_WIDTH, self::CANVAS_HEIGHT);
        try {
            $this->renderLayers($user, $selection, $canvas);
            $this->renderAwards($user, $canvas);
            $blob = $this->encodePng($canvas);

            $hash = substr(hash('sha256', $blob), 0, 16);
            $outputPath = sprintf('%s%d_%s.png', self::OUTPUT_PREFIX, $user->getId(), $hash);
            if (!$this->perscomAssetStorage->fileExists($outputPath)) {
                $this->perscomAssetStorage->write($outputPath, $blob);
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
        } catch (Throwable $exception) {
            $this->logger->error('Failed to generate PERSCOM composite.', [
                'user' => $user->getId(),
                'exception' => $exception,
            ]);
            return false;
        }
    }

    private function renderLayers(PerscomUser $user, CompositeSelection $selection, GdImage $canvas): void
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

    private function renderAwards(PerscomUser $user, GdImage $canvas): void
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

                $this->drawText($canvas, $category->label(), $category->panelX(), $currentY + 10, 10);
                $currentY += 15;
                $this->renderRibbonGrid($canvas, $grouped[$category->value], $category->panelX(), $currentY);
                $rows = (int) ceil(count($grouped[$category->value]) / self::RIBBONS_PER_ROW);
                $currentY += $rows * (self::RIBBON_HEIGHT + self::GRID_GAP) + 15;
            }
        }
    }

    /** @param array<int, array{award: Award, count: int}> $awards */
    private function renderRibbonGrid(GdImage $canvas, array $awards, int $startX, int $startY): void
    {
        $index = 0;
        foreach ($awards as $item) {
            $imagePath = $item['award']->getImage();
            if ($imagePath === null || !$this->perscomAssetStorage->fileExists($imagePath)) {
                ++$index;
                continue;
            }

            $ribbon = null;
            $resized = null;
            try {
                $ribbon = $this->decodeImage($this->perscomAssetStorage->read($imagePath));
                [$width, $height] = $this->fitDimensions(
                    imagesx($ribbon),
                    imagesy($ribbon),
                    self::RIBBON_WIDTH,
                    self::RIBBON_HEIGHT,
                );
                $resized = $this->resize($ribbon, $width, $height);

                if ($item['count'] > 1) {
                    $this->drawAwardCount($resized, $item['count']);
                }

                $column = $index % self::RIBBONS_PER_ROW;
                $row = intdiv($index, self::RIBBONS_PER_ROW);
                $x = $startX + $column * (self::RIBBON_WIDTH + self::GRID_GAP);
                $y = $startY + $row * (self::RIBBON_HEIGHT + self::GRID_GAP);
                $x += intdiv(self::RIBBON_WIDTH - $width, 2);
                $y += intdiv(self::RIBBON_HEIGHT - $height, 2);
                imagecopy($canvas, $resized, $x, $y, 0, 0, $width, $height);
            } catch (Throwable $exception) {
                $this->logger->warning('Unable to render award image.', [
                    'path' => $imagePath,
                    'exception' => $exception,
                ]);
            }

            ++$index;
        }
    }

    private function drawAwardCount(GdImage $image, int $count): void
    {
        $text = 'x' . $count;
        if (is_file(self::FONT) && function_exists('imagettfbbox')) {
            $box = imagettfbbox(12, 0, self::FONT, $text);
            $width = $box === false ? 16 : abs($box[2] - $box[0]);
            $x = max(1, imagesx($image) - $width - 3);
            $y = max(13, imagesy($image) - 3);
            $black = imagecolorallocate($image, 0, 0, 0);
            $white = imagecolorallocate($image, 255, 255, 255);
            foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dy]) {
                imagettftext($image, 12, 0, $x + $dx, $y + $dy, $black, self::FONT, $text);
            }
            imagettftext($image, 12, 0, $x, $y, $white, self::FONT, $text);
            return;
        }

        $font = 3;
        $x = max(0, imagesx($image) - imagefontwidth($font) * strlen($text) - 2);
        $y = max(0, imagesy($image) - imagefontheight($font) - 1);
        imagestring($image, $font, $x + 1, $y + 1, $text, imagecolorallocate($image, 0, 0, 0));
        imagestring($image, $font, $x, $y, $text, imagecolorallocate($image, 255, 255, 255));
    }

    private function drawText(GdImage $image, string $text, int $x, int $baselineY, int $size): void
    {
        $white = imagecolorallocate($image, 255, 255, 255);
        if (is_file(self::FONT) && function_exists('imagettftext')) {
            imagettftext($image, $size, 0, $x, $baselineY, $white, self::FONT, $text);
            return;
        }
        imagestring($image, 2, $x, max(0, $baselineY - imagefontheight(2)), $text, $white);
    }

    private function compositeBlob(GdImage $canvas, string $blob, int $x, int $y): void
    {
        $image = $this->decodeImage($blob);
        if (imagesx($image) === self::CANVAS_WIDTH && imagesy($image) === self::CANVAS_HEIGHT) {
            imagecopy($canvas, $image, $x, $y, 0, 0, imagesx($image), imagesy($image));
            return;
        }

        imagecopyresampled(
            $canvas,
            $image,
            $x,
            $y,
            0,
            0,
            self::CANVAS_WIDTH,
            self::CANVAS_HEIGHT,
            imagesx($image),
            imagesy($image),
        );
    }

    private function decodeImage(string $blob): GdImage
    {
        $image = @imagecreatefromstring($blob);
        if (!$image instanceof GdImage) {
            throw new \RuntimeException('Unsupported or invalid image data.');
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);
        return $image;
    }

    private function createTransparentImage(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);
        return $image;
    }

    private function resize(GdImage $source, int $width, int $height): GdImage
    {
        $target = $this->createTransparentImage($width, $height);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        return $target;
    }

    /** @return array{int, int} */
    private function fitDimensions(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $scale = min($maxWidth / $width, $maxHeight / $height);
        return [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];
    }

    private function encodePng(GdImage $image): string
    {
        ob_start();
        try {
            if (!imagepng($image)) {
                throw new \RuntimeException('Unable to encode the composite PNG.');
            }
            $blob = ob_get_contents();
            if (!is_string($blob)) {
                throw new \RuntimeException('Unable to read the encoded composite PNG.');
            }
            return $blob;
        } finally {
            ob_end_clean();
        }
    }
}
