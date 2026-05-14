<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    /** @use HasFactory<\Database\Factories\ShopFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'owner_id',
        'owner_name',
        'phone',
        'email',
        'address',
        'status',
    ];

    /**
     * Keep `owner_name` in lockstep with the assigned owner's name. The text
     * column is preserved as a denormalized cache so DB-direct viewers
     * (TablePlus, SQL reports, exports) see a human-readable owner without
     * joining `users`. The API still resolves through the FK at read time,
     * which covers the edge case where the linked user is later renamed.
     *
     * Setting `owner_id` to null preserves the existing `owner_name` so the
     * legacy free-form label is not silently lost on unassign.
     */
    protected static function booted(): void
    {
        static::saving(function (Shop $shop): void {
            if ($shop->isDirty('owner_id') && $shop->owner_id !== null) {
                $owner = User::query()->find($shop->owner_id);
                if ($owner !== null) {
                    $shop->owner_name = $owner->name;
                }
            }
        });
    }

    /**
     * The owning user. NULL means the shop has been created but not yet
     * assigned to an owner — admin assigns via `owner_id` on edit. The
     * legacy `owner_name` text column stays for display when an owner
     * record doesn't exist (historical data import path).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function setting(): HasOne
    {
        return $this->hasOne(ShopSetting::class);
    }
}
