<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Enum;

use ArniePalau\CcComposite\Enum\AwardCategory;
use PHPUnit\Framework\TestCase;

final class AwardCategoryTest extends TestCase
{
    public function testCategoriesAreSplitAcrossBothPanels(): void
    {
        self::assertTrue(AwardCategory::OPERATIONS->isLeftPanel());
        self::assertTrue(AwardCategory::UNIT_MERIT->isLeftPanel());
        self::assertTrue(AwardCategory::INSIGNIA->isLeftPanel());
        self::assertFalse(AwardCategory::PERSONAL_MERIT->isLeftPanel());
        self::assertFalse(AwardCategory::EXEMPLARY->isLeftPanel());
        self::assertFalse(AwardCategory::SPECIAL->isLeftPanel());
    }

    public function testCatalanLabelsUseCorrectUtf8Text(): void
    {
        self::assertSame('MÈRIT A LA UNITAT', AwardCategory::UNIT_MERIT->label());
        self::assertSame('INSÍGNIES', AwardCategory::INSIGNIA->label());
        self::assertSame('MÈRIT PERSONAL', AwardCategory::PERSONAL_MERIT->label());
    }
}
