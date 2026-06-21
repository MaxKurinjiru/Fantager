<?php

declare(strict_types=1);

namespace App\Enum;

enum FormationPosition: string
{
    case Front1 = 'front_1';
    case Front2 = 'front_2';
    case Front3 = 'front_3';
    case Back1 = 'back_1';
    case Back2 = 'back_2';
    case Back3 = 'back_3';
}
