<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Package;

use ArniePalau\CcComposite\CcCompositePlugin;
use ArniePalau\CcComposite\Command\GenerateCompositeCommand;
use ArniePalau\CcComposite\Command\ImportLegacyLibraryCommand;
use ArniePalau\CcComposite\EventListener\PerscomRecordLifecycleSubscriber;
use ArniePalau\CcComposite\MenuBuilder\FieldReportsMenuType;
use ArniePalau\CcComposite\Service\CompositeGenerator;
use ArniePalau\CcComposite\Service\FieldReportImporter;
use ArniePalau\CcComposite\Entity\FieldReportPlayerLink;
use ArniePalau\CcComposite\Service\FieldReportPlayerIdentity;
use ArniePalau\CcComposite\Service\FieldReportPlayerProfileResolver;
use ArniePalau\CcComposite\Form\DTO\AdminNewUser;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\TwigFunction;
use Twig\Loader\ArrayLoader;
use Twig\Source;

final class PackageIntegrityTest extends TestCase
{
    public function testCoreIntegrationClassesAutoload(): void
    {
        self::assertTrue(class_exists(CcCompositePlugin::class));
        self::assertTrue(class_exists(CompositeGenerator::class));
        self::assertTrue(class_exists(GenerateCompositeCommand::class));
        self::assertTrue(class_exists(ImportLegacyLibraryCommand::class));
        self::assertTrue(class_exists(FieldReportsMenuType::class));
        self::assertTrue(class_exists(FieldReportImporter::class));
        self::assertTrue(class_exists(FieldReportPlayerLink::class));
        self::assertTrue(class_exists(FieldReportPlayerIdentity::class));
        self::assertTrue(class_exists(FieldReportPlayerProfileResolver::class));
        self::assertTrue(class_exists(AdminNewUser::class));
        self::assertTrue(class_exists(PerscomUser::class));
    }

    public function testComposerUsesForumifyAvailableGdRenderer(): void
    {
        $composer = json_decode(
            file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertArrayHasKey('ext-gd', $composer['require']);
        self::assertArrayNotHasKey('ext-imagick', $composer['require']);
        self::assertContains('symfony-ux', $composer['keywords']);
    }

    public function testLifecycleListenerUsesCurrentDoctrineEventTypes(): void
    {
        $postPersist = new \ReflectionMethod(PerscomRecordLifecycleSubscriber::class, 'postPersist');
        $postRemove = new \ReflectionMethod(PerscomRecordLifecycleSubscriber::class, 'postRemove');

        self::assertSame(PostPersistEventArgs::class, (string) $postPersist->getParameters()[0]->getType());
        self::assertSame(PostRemoveEventArgs::class, (string) $postRemove->getParameters()[0]->getType());
    }

    public function testOriginalCompositeFontIsBundled(): void
    {
        $font = dirname(__DIR__, 2) . '/assets/fonts/DejaVuSans-Bold.ttf';

        self::assertFileExists($font);
        self::assertGreaterThan(100_000, filesize($font));
    }

    public function testConfigurationFilesAreValidYaml(): void
    {
        $root = dirname(__DIR__, 2);
        $files = glob($root . '/config/{,packages/}*.yaml', GLOB_BRACE);
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            self::assertIsArray(Yaml::parseFile($file), $file);
        }
    }

    public function testTwigTemplatesParse(): void
    {
        $root = dirname(__DIR__, 2);
        $environment = new Environment(new ArrayLoader());
        foreach (['asset', 'csrf_token', 'form', 'form_end', 'form_errors', 'form_rest', 'form_row', 'form_start', 'is_granted', 'path', 'stimulus_controller'] as $function) {
            $environment->addFunction(new TwigFunction($function, static fn (): string => ''));
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/templates'));
        $parsed = 0;
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'twig') {
                continue;
            }
            $source = new Source(file_get_contents($file->getPathname()), $file->getPathname());
            $environment->parse($environment->tokenize($source));
            ++$parsed;
        }

        self::assertGreaterThan(0, $parsed);
    }

    public function testLegacyManifestMatchesBundledAssets(): void
    {
        $root = dirname(__DIR__, 2) . '/resources/legacy';
        $manifest = json_decode(file_get_contents($root . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(19, $manifest['layers']);
        self::assertCount(107, $manifest['award_categories']);
        self::assertArrayHasKey('Via (E)', $manifest['selections']);
    }

    public function testFieldReportIncludesInteractiveMapAndRichStatistics(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/templates/frontend/field_report/show.html.twig');

        self::assertStringContainsString('data-cc-tiled-map', $template);
        self::assertStringContainsString('GameMapUtils.basicInit', $template);
        self::assertStringContainsString('data-map-time', $template);
        self::assertStringContainsString('player.shotsFired', $template);
        self::assertStringContainsString('cc-icon-skull', $template);
        self::assertStringContainsString('rankingIcons', $template);
        self::assertStringContainsString('cc-feed__arrow', $template);
        self::assertStringContainsString('cc-person--civilian', $template);
        self::assertStringContainsString('cc-player-avatar', $template);
        self::assertStringContainsString('rankingValue', $template);
        self::assertStringNotContainsString('text-decoration:underline', $template);
    }
}
