<?php

namespace App\Models;

use App\Concerns\BelongsToShop;
use App\Enums\PricingMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use BelongsToShop, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'created_by',
        'name',
        'code',
        'unit',
        'cost_price',
        'sale_price',
        'pricing_mode',
        'markup_percent',
        'bulk_price',
        'bulk_threshold',
        'stock_quantity',
        'low_stock_alert',
        'image_path',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'pricing_mode' => PricingMode::class,
            'markup_percent' => 'decimal:2',
            'bulk_price' => 'decimal:2',
            'bulk_threshold' => 'integer',
            'stock_quantity' => 'decimal:3',
            'low_stock_alert' => 'decimal:3',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
