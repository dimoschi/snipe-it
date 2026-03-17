<?php

namespace App\Enums;

enum CheckoutRequestType: string
{
    case Checkout = 'checkout';
    case Purchase = 'purchase';
}
