<?php

namespace App\Models;

use App\Concerns\BelongsToShop;
use App\Enums\DebtDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    /** @use HasFactory<\Database\Factories\DebtFactory> */
    use BelongsToShop, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'user_id',
        'person_name',
        'direction',
        'balance',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => DebtDirection::class,
            'balance' => 'decimal:2',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(DebtTransaction::class);
    }
}
