<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Enum;

use ArniePalau\CcComposite\Enum\LayerCategory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LayerCategoryTest extends TestCase
{
    #[DataProvider('directoryCases')]
    public function testStorageDirectoryMatchesLegacyLayout(LayerCategory $category, string $directory): void
    {
        self::assertSame($directory, $category->directory());
    }

    public static function directoryCases(): iterable
    {
        yield [LayerCategory::BACKGROUND, 'backgrounds'];
        yield [LayerCategory::FACE, 'faces'];
        yield [LayerCategory::UNIFORM, 'uniforms'];
        yield [LayerCategory::HAIR, 'hair'];
        yield [LayerCategory::AMULET, 'amulets'];
    }
}
