<?php

declare(strict_types=1);

namespace App\Enum;

enum ChronicleReleaseReason: string
{
    case Inactivity = 'inactivity';
    case Bankruptcy = 'bankruptcy';
    case UnverifiedRegistration = 'unverified_registration';
    case AccountDeleted = 'account_deleted';
}
