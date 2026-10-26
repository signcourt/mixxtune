<?php

namespace App\Services\V2\Payouts;

use RuntimeException;

/* W115: a RazorpayX call that did not work. definite = Razorpay refused it (nothing was created). */
class RazorpayError extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 0, public readonly bool $definite = false)
    {
        parent::__construct($message);
    }
}
