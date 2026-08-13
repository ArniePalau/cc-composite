<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Service;

use ArniePalau\CcComposite\Entity\CompositeLayer;
use ArniePalau\CcComposite\Entity\CompositeSelection;
use ArniePalau\CcComposite\Enum\LayerCategory;
use ArniePalau\CcComposite\Repository\AwardPlacementRepository;
use ArniePalau\CcComposite\Repository\CompositeLayerRepository;
use ArniePalau\CcComposite\Service\CompositeGenerator;
use ArniePalau\CcComposite\Service\SelectionService;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
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
}
