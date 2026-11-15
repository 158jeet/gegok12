<?php

namespace App\\Services\\Security;

use App\\Mail\\ApiPasswordRecoveryCode;
use App\\Models\\User;
use Illuminate\\Support\\Facades\\Mail;
use InvalidArgumentException;

class RecoveryCodeDeliveryService
{
    /**
     * Deliver a recovery code only to the account's own verified email.
     * The caller cannot supply or override the destination.
     */
    public function send(string $code, User $user): void
    {
        $email = trim((string) $user->email);
        $verified = (bool) $user->email_verified || !empty($user->email_verified_at);

        if (!$verified || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A verified account email is required for recovery.');
        }

        Mail::to($email)->queue(new ApiPasswordRecoveryCode($code));
    }
}
