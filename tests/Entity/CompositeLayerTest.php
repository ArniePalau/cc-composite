<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Entity;

use ArniePalau\CcComposite\Entity\CompositeLayer;
use ArniePalau\CcComposite\Enum\LayerCategory;
use Forumify\PerscomPlugin\Perscom\Entity\PerscomUser;
use Forumify\PerscomPlugin\Perscom\Entity\Unit;
use PHPUnit\Framework\TestCase;

final class CompositeLayerTest extends TestCase
{
    public function testUnrestrictedLayerIsAvailableToEveryone(): void
    {
        $layer = new CompositeLayer();
        $layer->setCategory(LayerCategory::FACE);
        $layer->setFilename('face.png');

        self::assertTrue($layer->isAllowedFor(new PerscomUser()));
        self::assertSame('layers/faces/face.png', $layer->getAssetPath());
    }

    public function testUnitRestrictionUsesOrPolicy(): void
    {
        $allowedUnit = new Unit();
        $allowedUnit->setName('Allowed');
        $otherUnit = new Unit();
        $otherUnit->setName('Other');

        $layer = new CompositeLayer();
        $layer->setCategory(LayerCategory::UNIFORM);
        $layer->setFilename('pilot.png');
        $layer->addAllowedUnit($allowedUnit);

        $allowed = new PerscomUser();
        $allowed->setUnit($allowedUnit);
        $denied = new PerscomUser();
        $denied->setUnit($otherUnit);

        self::assertTrue($layer->isAllowedFor($allowed));
        self::assertFalse($layer->isAllowedFor($denied));
    }
}
