<?php

namespace App\Models;

use App\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'shop_id',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'pin_reset_required' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Shops this user owns. Sellers always have an empty collection here —
     * their single-shop relationship is `shop()` above. Owners use this to
     * scope all of their data; the list grows / shrinks as the admin
     * assigns / unassigns shops via `shops.owner_id`.
     */
    public function ownedShops(): HasMany
    {
        return $this->hasMany(Shop::class, 'owner_id');
    }

    /**
     * Cached array of `owned_shop_ids` so repository scope filters can
     * issue `whereIn('shop_id', $user->owned_shop_ids)` without re-running
     * the query for every entity touched in the same request.
     *
     * @return list<int>
     */
    public function getOwnedShopIdsAttribute(): array
    {
        return $this->ownedShops()->pluck('id')->all();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'created_by');
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

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    /**
     * Shop IDs this user is authorized to see at all. Repositories use this
     * as the base scope filter for every entity that has a `shop_id` column.
     *
     *   • super_admin → null  (no filter; the request `shop_id` param can
     *                          narrow further per-call)
     *   • owner      → list of shops where `shops.owner_id = user.id`
     *   • seller     → exactly [user.shop_id], or [] if shop_id is missing
     *                  (shouldn't happen in practice; defensive empty list
     *                  ensures no data leak rather than throwing)
     *
     * @return list<int>|null
     */
    public function accessibleShopIds(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null;
        }
        if ($this->role === UserRole::Owner) {
            return $this->owned_shop_ids;
        }

        return $this->shop_id !== null ? [(int) $this->shop_id] : [];
    }

    /**
     * True if the user has any shop they can act in.
     *
     *   • super_admin: always true (operates above shop scope)
     *   • owner: true iff at least one shop is assigned to them
     *   • seller: true iff `shop_id` is set (which is enforced at creation
     *     for sellers, so practically always true)
     *
     * Used by policies that gate "can do X at all" (vs "can do X on
     * specific entity Y" — that's `canAccessShop`).
     */
    public function hasAnyShop(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        if ($this->role === UserRole::Owner) {
            return $this->owned_shop_ids !== [];
        }

        return $this->shop_id !== null;
    }

    /**
     * True if the user can act on entities belonging to the given shop.
     *
     *   • super_admin: always true
     *   • owner: shop must be in their owned set
     *   • seller: shop must equal their single `shop_id`
     */
    public function canAccessShop(int $shopId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        if ($this->role === UserRole::Owner) {
            return in_array($shopId, $this->owned_shop_ids, true);
        }

        return $this->shop_id !== null && (int) $this->shop_id === $shopId;
    }

    /**
     * Resolve the shop_id to write to when creating an entity (sale,
     * expense, debt, etc.) in a request. Used by controllers to remove
     * boilerplate around the "is shop_id required from request, or
     * implied by the user role?" decision.
     *
     *   • super_admin: `$requestedShopId` required, must be a real shop.
     *     Throws `ValidationException` if missing.
     *   • owner with multiple shops: `$requestedShopId` required and
     *     must be in the owner's set. Throws if missing or not owned.
     *   • owner with exactly one shop, OR seller: implied. Returns that
     *     shop. `$requestedShopId` is honored only if it matches.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function resolveShopIdForWrite(?int $requestedShopId): int
    {
        $accessible = $this->accessibleShopIds();

        if ($accessible === null) {
            // super_admin
            if (! $requestedShopId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'shop_id' => ['shop_id is required.'],
                ]);
            }

            return $requestedShopId;
        }

        if (count($accessible) === 1 && $requestedShopId === null) {
            return (int) $accessible[0];
        }

        if (! $requestedShopId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'shop_id' => ['shop_id is required because you have multiple shops.'],
            ]);
        }

        if (! in_array($requestedShopId, $accessible, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'shop_id' => ['You do not have access to that shop.'],
            ]);
        }

        return $requestedShopId;
    }

    public function updatePushToken(string $token, string $platform): void
    {
        $this->forceFill([
            'push_token' => $token,
            'push_platform' => $platform,
        ])->save();
    }

    public function sendPushNotification(string $title, string $body, array $data = []): void
    {
        if (! $this->push_token) {
            return;
        }

        $notification = [
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ];

        if ($this->push_platform === 'ios') {
            // APNs payload
            $payload = [
                'aps' => [
                    'alert' => $notification,
                    'sound' => 'default',
                ],
                'data' => $data,
            ];
        } else {
            // FCM payload
            $payload = [
                'notification' => $notification,
                'data' => $data,
                'token' => $this->push_token,
            ];
        }

        // Send via Laravel's notification system or direct FCM/APNs
        $this->notify(new App\Notifications\PushNotification($title, $body, $data));
    }
}
