<?php

namespace App\Services\Security;

use App\Traits\MSG91;

class RecoveryCodeDeliveryService
{
    use MSG91;

    public function send(string $code, string $mobileNumber): void
    {
        $this->getOTP($code, $mobileNumber);
    }
}
