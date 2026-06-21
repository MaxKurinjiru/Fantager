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
}
