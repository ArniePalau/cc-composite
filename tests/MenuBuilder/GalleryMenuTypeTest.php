<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\MenuBuilder;

use ArniePalau\CcComposite\MenuBuilder\GalleryMenuType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class GalleryMenuTypeTest extends TestCase
{
    public function testRegistersGaleriaMenuBuilderType(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $type = new GalleryMenuType($urlGenerator);

        self::assertSame('galeria', $type->getType());
        self::assertNull($type->getPayloadFormType());
    }
}
