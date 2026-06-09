<?php

declare(strict_types=1);

namespace App\Enum;

enum TokenType: string
{
    case EmailVerify = 'email_verify';
    case PasswordReset = 'password_reset';
    case ChangeEmailOld = 'change_email_old';
    case ChangeEmailNew = 'change_email_new';
    case DeleteAccount = 'delete_account';
}
