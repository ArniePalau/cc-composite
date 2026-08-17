<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Entity;

use ArniePalau\CcComposite\Entity\Campaign;
use ArniePalau\CcComposite\Entity\FieldReport;
use ArniePalau\CcComposite\Entity\GalleryImage;
use PHPUnit\Framework\TestCase;

final class GalleryImageTest extends TestCase
{
    public function testHandlesCampaignAndMissionAssociations(): void
    {
        $campaign = new Campaign();
        $campaign->setName('Operació Llevant');
        $campaign->setSlug('operacio-llevant');

        $report = new FieldReport();
        $report->setCode('rep-123');
        $report->setMissionName('Assalt Inicial');
        $report->setCampaign($campaign);

        $image = new GalleryImage();
        $image->setTitle('  Foto de combat  ');
        $image->setImagePath('gallery/llevant-abc.webp');
        $image->setPosition(5);
        $image->setFieldReport($report);

        self::assertSame('Foto de combat', $image->getTitle());
        self::assertSame('gallery/llevant-abc.webp', $image->getImagePath());
        self::assertSame(5, $image->getPosition());
        self::assertSame($report, $image->getFieldReport());
        self::assertSame($campaign, $image->getEffectiveCampaign());

        // Test campaign image collection methods
        $campaign->addImage($image);
        self::assertCount(1, $campaign->getImages());
        self::assertTrue($campaign->getImages()->contains($image));

        $campaign->removeImage($image);
        self::assertCount(0, $campaign->getImages());
    }
}
