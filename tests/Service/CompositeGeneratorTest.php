<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Service;

use ArniePalau\CcComposite\Entity\CompositeLayer;
use ArniePalau\CcComposite\Entity\CompositeSelection;
use ArniePalau\CcComposite\Entity\AwardPlacement;
use ArniePalau\CcComposite\Enum\AwardCategory;
use ArniePalau\CcComposite\Enum\LayerCategory;
use ArniePalau\CcComposite\Repository\AwardPlacementRepository;
use ArniePalau\CcComposite\Repository\CompositeLayerRepository;
use ArniePalau\CcComposite\Service\CompositeGenerator;
use ArniePalau\CcComposite\Service\SelectionService;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Forumify\PerscomPlugin\Perscom\Entity\Award;
use Forumify\PerscomPlugin\Perscom\Entity\Record\AwardRecord;
use Forumify\PerscomPlugin\Perscom\Repository\AwardRecordRepository;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CompositeGeneratorTest extends TestCase
{
    private string $outputDirectory;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD is required.');
        }
        $this->outputDirectory = sys_get_temp_dir() . '/cc-composite-' . bin2hex(random_bytes(6));
        mkdir($this->outputDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        if (!isset($this->outputDirectory) || !is_dir($this->outputDirectory)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->outputDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->outputDirectory);
    }

    public function testGeneratesPerscomUniformFromLegacyLayers(): void
    {
        $layers = [
            'background' => 'background-militar.jpg',
            'face' => '4.4.png',
            'uniform' => 'u1.png',
            'hair' => 'b1.png',
            'amulet' => 'moreneta.png',
        ];

        $layerRepository = $this->createMock(CompositeLayerRepository::class);
        $layerRepository->method('findOneByCategoryAndFilename')
            ->willReturnCallback(static function (LayerCategory $category, string $filename): CompositeLayer {
                $layer = new CompositeLayer();
                $layer->setCategory($category);
                $layer->setFilename($filename);
                return $layer;
            });

        $selectionService = $this->createMock(SelectionService::class);
        $selectionService->method('resolveLayers')->willReturn($layers);

        $awardRecords = $this->createMock(AwardRecordRepository::class);
        $awardRecords->method('findBy')->willReturn([]);

        $generator = new CompositeGenerator(
            new Filesystem(new LocalFilesystemAdapter(dirname(__DIR__, 2) . '/resources/legacy')),
            new Filesystem(new LocalFilesystemAdapter($this->outputDirectory)),
            $layerRepository,
            $this->createMock(AwardPlacementRepository::class),
            $awardRecords,
            $selectionService,
            new NullLogger(),
        );

        $user = new PerscomUser();
        $id = new \ReflectionProperty(PerscomUser::class, 'id');
        $id->setValue($user, 42);
        $selection = new CompositeSelection();
        $selection->setUser($user);

        self::assertTrue($generator->generate($user, $selection));
        self::assertNotNull($user->getUniform());
        self::assertTrue($user->isUniformDirty());
        self::assertSame($user->getUniform(), $selection->getGeneratedPath());

        $path = $this->outputDirectory . '/' . $user->getUniform();
        self::assertFileExists($path);
        $size = getimagesize($path);
        self::assertIsArray($size);
        self::assertSame(1080, $size[0]);
        self::assertSame(530, $size[1]);
    }

    public function testAwardsSharingAnImageRenderOnceWithRepeatCount(): void
    {
        $storage = new Filesystem(new LocalFilesystemAdapter($this->outputDirectory));
        $ribbon = imagecreatetruecolor(80, 28);
        imagefill($ribbon, 0, 0, imagecolorallocate($ribbon, 180, 20, 20));
        ob_start();
        imagepng($ribbon);
        $ribbonBlob = ob_get_clean();
        self::assertIsString($ribbonBlob);
        $storage->write('award/repeat-one.png', $ribbonBlob);
        $storage->write('award/repeat-two.png', $ribbonBlob);

        $user = new PerscomUser();
        (new \ReflectionProperty(PerscomUser::class, 'id'))->setValue($user, 43);

        $records = [];
        foreach ([1, 2] as $position) {
            $award = new Award();
            $award->setName('Repeat ' . $position);
            $award->setPosition($position);
            $award->setImage('award/repeat-' . ($position === 1 ? 'one' : 'two') . '.png');
            $record = new AwardRecord();
            $record->setUser($user);
            $record->setAward($award);
            $records[] = $record;
        }

        $placement = new AwardPlacement();
        $placement->setCategory(AwardCategory::OPERATIONS);
        $placementRepository = $this->createMock(AwardPlacementRepository::class);
        $placementRepository->method('findForAward')->willReturn($placement);

        $recordRepository = $this->createMock(AwardRecordRepository::class);
        $recordRepository->method('findBy')->willReturn($records);
        $selectionService = $this->createMock(SelectionService::class);
        $selectionService->method('resolveLayers')->willReturn([]);

        $generator = new CompositeGenerator(
            new Filesystem(new LocalFilesystemAdapter(dirname(__DIR__, 2) . '/resources/legacy')),
            $storage,
            $this->createMock(CompositeLayerRepository::class),
            $placementRepository,
            $recordRepository,
            $selectionService,
            new NullLogger(),
        );

        $selection = new CompositeSelection();
        $selection->setUser($user);
        self::assertTrue($generator->generate($user, $selection));

        $result = imagecreatefrompng($this->outputDirectory . '/' . $user->getUniform());
        self::assertInstanceOf(\GdImage::class, $result);
        $firstRibbon = imagecolorsforindex($result, imagecolorat($result, 46, 56));
        $secondSlot = imagecolorsforindex($result, imagecolorat($result, 130, 56));
        self::assertSame(0, $firstRibbon['alpha']);
        self::assertSame(127, $secondSlot['alpha'], 'Shared-image awards must occupy one ribbon slot.');
    }
}
