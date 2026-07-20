<?php

declare(strict_types=1);

namespace Modules\Authentication\Enums;

enum MfaMethod: string
{
    case EmailOtp = 'email';
    case Totp = 'totp';
}
