<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Service;

use ArniePalau\CcComposite\Entity\FieldReport;
use ArniePalau\CcComposite\Service\FieldReportPlayerIdentity;
use PHPUnit\Framework\TestCase;

final class FieldReportPlayerIdentityTest extends TestCase
{
    public function testCollectsUniquePlayersAcrossReportsUsingNormalizedNames(): void
    {
        $first = new FieldReport();
        $first->setPayload(['players' => [['name' => ' CC_Via '], ['name' => 'Arnie']]]);
        $second = new FieldReport();
        $second->setPayload(['players' => [['name' => 'cc_via'], ['name' => 'Via']]]);
        $identity = new FieldReportPlayerIdentity();

        $players = $identity->collect([$first, $second]);

        self::assertCount(3, $players);
        self::assertSame('cc_via', $players[$identity->key('CC_VIA')]);
        self::assertContains('Arnie', $players);
        self::assertContains('Via', $players);
    }
}
