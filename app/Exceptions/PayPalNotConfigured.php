<?php

namespace App\Exceptions;

class PayPalNotConfigured extends PayPalException
{
    public function __construct()
    {
        parent::__construct('PayPal sandbox credentials are not configured.');
    }
}
