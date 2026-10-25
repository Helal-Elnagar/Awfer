<div class="products-grid">
  @forelse($products as $product)
    <div class="product-item">
      <a href="{{ route('website.product.show', $product->id) }}" class="item-img" aria-label="product item">
        <img loading="lazy" src="{{ $product->image_url }}" class="img-contain" alt="{{ $product->name }}" />
      </a>
      <div class="item-info">
        <h3 class="item-title">
          <a href="{{ route('website.product.show', $product->id) }}" aria-label="category item">{{ $product->name }}</a>
        </h3>
        <div class="item-prices">
          <strong class="price-current">
            {{ number_format($product->final_price, 2) }}
            <span class="curreny">
              <img loading="lazy" src="{{ asset('website/images/icons/sar.svg') }}" alt="sar" class="svg" />
            </span>
          </strong>
          @if($product->discount_percentage)
            <span class="price-discount"> -{{ $product->discount_percentage }}% </span>
            <del class="price-old">
              {{ number_format($product->base_price, 2) }}
              <span class="curreny">
                <img loading="lazy" src="{{ asset('website/images/icons/sar.svg') }}" alt="sar" class="svg" />
              </span>
            </del>
          @endif
        </div>
        <div class="item-tools">
          <div class="item-quantity">
            <input type="number" class="quantity-input" min="1" max="{{ $product->quantity }}" value="1" readonly />
            <button class="quantity-btn plus-btn" type="button" onclick="this.parentNode.querySelector('input[type=number]').stepUp()">
              <i class="fal fa-plus"></i>
            </button>
            <button class="quantity-btn minus-btn" type="button" onclick="this.parentNode.querySelector('input[type=number]').stepDown()">
              <i class="fal fa-minus"></i>
            </button>
          </div>
          <button class="item-button add-to-cart-btn" data-product-id="{{ $product->id }}" @disabled($product->quantity < 1)>
            <i class="fal fa-shopping-cart"></i>
          </button>
          <button class="item-fav {{ $product->is_favourite ? 'is-favorited' : '' }}" data-favorite-toggle data-product-id="{{ $product->id }}">
            <i class="far fa-heart"></i>
          </button>
        </div>
      </div>
    </div>
  @empty
    <p>{{ __('site.no_products_matching_filters') }}</p>
  @endforelse
</div>
<div class="mt-3">
  {{ $products->withQueryString()->links() }}
</div>

