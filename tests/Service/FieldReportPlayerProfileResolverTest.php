<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Service;

use ArniePalau\CcComposite\Service\FieldReportPlayerProfileResolver;
use PHPUnit\Framework\TestCase;

final class FieldReportPlayerProfileResolverTest extends TestCase
{
    public function testCollectsEveryPlayerOccurrenceWithoutDuplicates(): void
    {
        $resolver = (new \ReflectionClass(FieldReportPlayerProfileResolver::class))->newInstanceWithoutConstructor();
        $names = $resolver->collectNames([
            'mvp' => ['name' => 'CC_Via'],
            'players' => [['name' => 'CC_Via'], ['name' => 'Arnie']],
            'rankings' => [['entries' => [['name' => 'Arnie'], ['name' => 'Viper']]]],
            'killFeed' => [['killer' => 'CC_Via', 'victim' => 'Waaka'], ['killer' => 'Waaka', 'victim' => 'CC_Via']],
        ]);

        self::assertSame(['CC_Via', 'Arnie', 'Viper', 'Waaka'], $names);
    }
}
