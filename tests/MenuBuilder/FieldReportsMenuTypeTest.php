<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\MenuBuilder;

use ArniePalau\CcComposite\MenuBuilder\FieldReportsMenuType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class FieldReportsMenuTypeTest extends TestCase
{
    public function testRegistersNativeMenuBuilderTypeWithoutPayload(): void
    {
        $type = new FieldReportsMenuType($this->createMock(UrlGeneratorInterface::class));

        self::assertSame('field_reports', $type->getType());
        self::assertNull($type->getPayloadFormType());
    }
}
