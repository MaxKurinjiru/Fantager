<?php

declare(strict_types=1);

namespace App\Enum;

enum SpellType: string
{
    case Harm = 'harm';
    case Restore = 'restore';
    case Enhance = 'enhance';
    case Hinder = 'hinder';
}
