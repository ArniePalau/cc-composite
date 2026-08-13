<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Enum;

enum AwardCategory: string
{
    case OPERATIONS = 'operations';
    case UNIT_MERIT = 'unit_merit';
    case INSIGNIA = 'insignia';
    case PERSONAL_MERIT = 'personal_merit';
    case EXEMPLARY = 'exemplary';
    case SPECIAL = 'special';

    public function label(): string
    {
        return match ($this) {
            self::OPERATIONS => 'SERVEI (EN CONFLICTES)',
            self::UNIT_MERIT => 'MÈRIT A LA UNITAT',
            self::INSIGNIA => 'INSÍGNIES',
            self::PERSONAL_MERIT => 'MÈRIT PERSONAL',
            self::EXEMPLARY => 'EXEMPLARITAT',
            self::SPECIAL => 'ESPECIALS',
        };
    }

    public function panelX(): int
    {
        return match ($this) {
            self::OPERATIONS, self::UNIT_MERIT, self::INSIGNIA => 45,
            self::PERSONAL_MERIT, self::EXEMPLARY, self::SPECIAL => 780,
        };
    }

    public function isLeftPanel(): bool
    {
        return $this->panelX() < 500;
    }
}
