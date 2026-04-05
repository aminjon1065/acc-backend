<?php

namespace App\Models;

use App\Concerns\BelongsToShop;
use App\Enums\PaymentType;
use App\Enums\SaleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    /** @use HasFactory<\Database\Factories\SaleFactory> */
    use HasFactory, BelongsToShop;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'user_id',
        'customer_name',
        'type',
        'discount',
        'paid',
        'debt',
        'total',
        'payment_type',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SaleType::class,
            'discount' => 'decimal:2',
            'paid' => 'decimal:2',
            'debt' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_type' => PaymentType::class,
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
