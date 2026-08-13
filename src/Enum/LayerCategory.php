<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Enum;

enum LayerCategory: string
{
    case BACKGROUND = 'background';
    case FACE = 'face';
    case UNIFORM = 'uniform';
    case HAIR = 'hair';
    case AMULET = 'amulet';

    public function directory(): string
    {
        return match ($this) {
            self::BACKGROUND => 'backgrounds',
            self::FACE => 'faces',
            self::UNIFORM => 'uniforms',
            self::HAIR => 'hair',
            self::AMULET => 'amulets',
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
