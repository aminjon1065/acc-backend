<?php

namespace App\Enums;

enum DebtTransactionType: string
{
    case Give = 'give';
    case Take = 'take';
    case Repay = 'repay';
}
