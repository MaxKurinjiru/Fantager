<?php

declare(strict_types=1);

namespace App\Enum;

enum Race: string
{
    case Human = 'human';
    case Elf = 'elf';
    case Dwarf = 'dwarf';
    case Orc = 'orc';
    case Undead = 'undead';
    case Giant = 'giant';
    case Ent = 'ent';
    case Genie = 'genie';

    /**
     * Combat hex-ball radius. Ents and Giants occupy the origin plus its 6 neighbours (a 7-hex flower).
     */
    public function hexFootprintRadius(): int
    {
        return match ($this) {
            self::Ent, self::Giant => 1,
            default => 0,
        };
    }
}
