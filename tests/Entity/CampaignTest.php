<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Entity;

use ArniePalau\CcComposite\Entity\Campaign;
use PHPUnit\Framework\TestCase;

final class CampaignTest extends TestCase
{
    public function testNormalizesEditableMetadata(): void
    {
        $campaign = new Campaign();
        $campaign->setName('  Operació Llevant  ');
        $campaign->setDescription('   ');
        $campaign->setSlug('operacio-llevant');
        $campaign->setImagePath('campaigns/operacio-llevant.webp');

        self::assertSame('Operació Llevant', $campaign->getName());
        self::assertNull($campaign->getDescription());
        self::assertSame('operacio-llevant', $campaign->getSlug());
        self::assertSame('campaigns/operacio-llevant.webp', $campaign->getImagePath());
        self::assertCount(0, $campaign->getReports());
    }
}
