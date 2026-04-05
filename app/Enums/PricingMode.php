<?php

namespace App\Enums;

enum PricingMode: string
{
    case Fixed = 'fixed';
    case Markup = 'markup';
    case Manual = 'manual';
}
