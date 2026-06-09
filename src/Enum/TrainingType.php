<?php

declare(strict_types=1);

namespace App\Enum;

enum TrainingType: string
{
    case Attribute = 'attribute';
    case Magic = 'magic';
    case Form = 'form';
}
