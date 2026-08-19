<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Service;

use ArniePalau\CcComposite\Service\ArmaBriefingFormatter;
use PHPUnit\Framework\TestCase;

final class ArmaBriefingFormatterTest extends TestCase
{
    public function testItPreservesSafeArmaFormatting(): void
    {
        $html = (new ArmaBriefingFormatter())->format(
            '<font color="#E5E27F">Context</font><br><br><font color="#FFFFFF">Text</font>',
        );

        self::assertStringContainsString('style="color:#e5e27f"', $html);
        self::assertStringContainsString('Context</span><br><br>', $html);
        self::assertStringContainsString('style="color:#ffffff"', $html);
    }

    public function testItEscapesTextAndDropsUnsafeTagsAndAttributes(): void
    {
        $html = (new ArmaBriefingFormatter())->format(
            '<script>alert(1)</script><font color="red" onclick="bad()">Safe & sound</font>',
        );

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('onclick', $html);
        self::assertStringNotContainsString('color:red', $html);
        self::assertStringContainsString('alert(1)', $html);
        self::assertStringContainsString('Safe &amp; sound', $html);
    }
}
