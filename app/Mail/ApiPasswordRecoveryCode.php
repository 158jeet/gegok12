<?php

namespace App\\Mail;

use Illuminate\\Bus\\Queueable;
use Illuminate\\Contracts\\Queue\\ShouldQueue;
use Illuminate\\Mail\\Mailable;
use Illuminate\\Queue\\SerializesModels;

class ApiPasswordRecoveryCode extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    private string $code;

    public function __construct(string $code)
    {
        $this->queue = 'emails';
        $this->code = $code;
    }

    public function build()
    {
        return $this->subject('Your Tagore ERP password recovery code')
            ->html(
                '<p>Use this code to reset your Tagore ERP password:</p>' .
                '<p style="font-size:24px;font-weight:bold;letter-spacing:4px;">' . e($this->code) . '</p>' .
                '<p>This code expires in 5 minutes. If you did not request it, ignore this email.</p>'
            );
    }
}
