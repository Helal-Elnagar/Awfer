<?php
namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Translatable\HasTranslations;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Product extends BaseModel implements HasMedia
{
    use HasFactory, SoftDeletes, HasTranslations, InteractsWithMedia;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'parent_category_id',
        'base_price',
        'is_refunded',
        'discount_percentage',
        'is_active',
        'brand_id',
        'quantity',
    ];

    public $translatable = ['name', 'description'];

    protected $casts = [
        'base_price'          => 'decimal:2',
        'discount_percentage' => 'int',
        'is_active'           => 'boolean',
        'quantity'            => 'int',
    ];

    protected $appends = [
        'in_cart',
        'cart_quantity',
    ];

    public function getAvailableQuantityAttribute()
    {
        return $this->quantity;
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Cache the authenticated user's cart item quantities keyed by product id.
     * Stored on the request-scoped container so it is loaded only once per
     * request and never leaks between requests (safe under Octane/Swoole).
     *
     * @var array<int, array<int, int>>
     */
    private const CART_QUANTITIES_CACHE_KEY = 'product_cart_item_quantities';

    /**
     * Get the authenticated user's cart item quantities as [product_id => quantity].
     *
     * @return array<int, int>
     */
    protected function getCartItemQuantities(): array
    {
        $userId = Auth::id();
        if (! $userId) {
            return [];
        }

        $cache = app()->bound(self::CART_QUANTITIES_CACHE_KEY)
            ? app(self::CART_QUANTITIES_CACHE_KEY)
            : [];

        if (isset($cache[$userId])) {
            return $cache[$userId];
        }

        $cart = Auth::user()->cart;
        $cache[$userId] = $cart
            ? $cart->items()
                ->pluck('quantity', 'product_id')
                ->map(fn ($quantity) => (int) $quantity)
                ->all()
            : [];

        app()->instance(self::CART_QUANTITIES_CACHE_KEY, $cache);

        return $cache[$userId];
    }

    public function getInCartAttribute()
    {
        if (! Auth::check()) {
            return false;
        }

        if (array_key_exists('in_cart', $this->attributes)) {
            return (bool) $this->attributes['in_cart'];
        }

        return array_key_exists($this->id, $this->getCartItemQuantities());
    }

    public function getCartQuantityAttribute()
    {
        if (! Auth::check()) {
            return 0;
        }

        if (array_key_exists('cart_quantity', $this->attributes)) {
            return (int) $this->attributes['cart_quantity'];
        }

        return (int) ($this->getCartItemQuantities()[$this->id] ?? 0);
    }

    public function getIsAvailableAttribute()
    {
        return $this->available_quantity > 0;
    }

    public function parentCategory()
    {
        return $this->belongsTo(Category::class, 'parent_category_id');
    }

    public function getIsFavouriteAttribute()
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        return $user->favourites()->where('product_id', $this->id)->exists();
    }

    public function options()
    {
        return $this->hasMany(ProductOption::class);
    }

    public function getFinalPriceAttribute()
    {
        if ($this->discount_percentage) {
            return $this->base_price - ($this->base_price * $this->discount_percentage / 100);
        }

        return $this->base_price;
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getImageUrlAttribute()
    {
        return $this->getFirstMediaUrl('product-images') ?: asset('storage/images/default.png');
    }

    public function scopeWithCartState(Builder $query, ?int $userId = null): Builder
    {
        $userId = $userId ?? (Auth::id() ?: null);

        if (! $userId) {
            return $query->selectSub('0', 'in_cart')
                ->selectSub('0', 'cart_quantity');
        }

        $cartSub = Cart::select('id')
            ->where('user_id', $userId)
            ->limit(1);

        $query->selectSub(
            "EXISTS(SELECT 1 FROM cart_items WHERE cart_items.product_id = products.id AND cart_items.cart_id = ({$cartSub->toSql()}))",
            'in_cart'
        );

        $query->selectSub(
            "COALESCE((SELECT SUM(quantity) FROM cart_items WHERE cart_items.product_id = products.id AND cart_items.cart_id = ({$cartSub->toSql()})), 0)",
            'cart_quantity'
        );

        return $query;
    }
}
