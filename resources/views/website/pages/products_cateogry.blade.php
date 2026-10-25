@extends('website.layouts.app')

@section('title', $category->name . ' - Lia')

@section('meta_description', __('site.browse_products') . ' ' . $category->name)
@section('content')
  <!-- Start Breadcrumb -->
    <section class="breadcrumb-section">
      <div class="container">
        <ul class="breadcrumb-list">
          <li class="breadcrumb-item">
            <a href="{{ url('/') }}" class="breadcrumb-link"> {{ __('site.home') }} </a>
          </li>
          <li class="breadcrumb-item">
            <a href="{{ route('website.category', $category->id) }}" class="breadcrumb-link">
              {{ $category->name }}
            </a>
          </li>
        </ul>
      </div>
    </section>
    <!-- End Breadcrumb -->

    <section class="page-content">
      <div class="container">
        <div class="category-content">
          <div class="filters-overlay"></div>
          <aside class="category-side">
            <div class="search-filters">
              <form id="filters-form" method="GET" action="{{ route('website.category', $category->id) }}">
                <div class="filters-head">
                  <h3 class="filters-title">{{ __('site.filter_search') }}</h3>
                  <button class="filters-reset" type="reset">
                    <i class="fal fa-rotate-right"></i>
                  </button>
                </div>
                <div class="filters-list">
                  <div class="filter-item">
                    <label class="filter-label">{{ __('site.subcategories') }}</label>
                    <div class="filter-values">
                      @foreach(($category->children ?? []) as $child)
                        @if(($child->is_active ?? 1) == 1)
                        <div class="checkbox">
                          <label>
                            <input type="checkbox" name="category_ids[]" value="{{ $child->id }}" {{ in_array($child->id, (array)request()->input('category_ids', [])) ? 'checked' : '' }} />
                            <span class="mark"><i class="fa-regular fa-check"></i></span>
                            <span class="text"> {{ $child->name }} </span>
                          </label>
                        </div>
                        @endif
                      @endforeach
                    </div>
                  </div>
                  <div class="filter-item">
                    <label class="filter-label">{{ __('site.brands') }}</label>
                    <div class="filter-values">
                      @foreach($brands as $brand)
                        <div class="checkbox">
                          <label>
                            <input type="checkbox" name="brand_ids[]" value="{{ $brand->id }}" {{ in_array($brand->id, (array)request()->input('brand_ids', [])) ? 'checked' : '' }} />
                            <span class="mark"><i class="fa-regular fa-check"></i></span>
                            <span class="text"> {{ $brand->name }} </span>
                          </label>
                        </div>
                      @endforeach
                    </div>
                  </div>
                  <div class="filter-item">
                    <label class="filter-label">{{ __('site.price') }}</label>
                    <div class="filter-values">
                      <div class="price-filter">
                        <div
                          id="price-slider"
                          class="price-slider"
                          data-min-price="0"
                          data-max-price="500"
                        ></div>
                        <div class="tooltips">
                          <p>
                            <span id="max-tooltip"> </span>
                            <i>
                              <img
                                src="{{ asset('website/images/icons/sar.svg') }}"
                                alt="curreny"
                                class="svg"
                              />
                            </i>
                          </p>
                          <p>
                            <span id="min-tooltip"> </span>
                            <i>
                              <img
                                src="{{ asset('website/images/icons/sar.svg') }}"
                                alt="curreny"
                                class="svg"
                              />
                            </i>
                          </p>
                        </div>
                        <div class="values-inputs">
                          <input
                            id="min-price"
                            type="number"
                            class="min-price"
                            name="min_price"
                            value="{{ request('min_price') }}"
                          />
                          <input
                            id="max-price"
                            type="number"
                            class="max-price"
                            name="max_price"
                            value="{{ request('max_price') }}"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <button class="filters-btn" type="submit">{{ __('site.filter') }}</button>
              </form>
            </div>
          </aside>
          <div>
            <div class="section-head page-head">
              <h1 class="section-title">{{ $category->name }}</h1>
              <div class="archive-tools">
                <div class="archive-sort">
                  <span>{{ __('site.sort_by') }}</span>
                  <select class="form-control" name="sort_by">
                    <option value="latest" {{ request('sort_by') === 'latest' ? 'selected' : '' }}>{{ __('site.latest') }}</option>
                    <option value="price_low" {{ request('sort_by') === 'price_low' ? 'selected' : '' }}>{{ __('site.price_low') }}</option>
                    <option value="price_high" {{ request('sort_by') === 'price_high' ? 'selected' : '' }}>{{ __('site.price_high') }}</option>
                  </select>
                </div>
                <button class="filter-trigger">
                  <i class="fal fa-filter"></i>
                </button>
              </div>
            </div>
            <div id="products-list">
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
              <input
                type="number"
                class="quantity-input"
                min="1"
                max="{{ $product->quantity }}"
                value="1"
                readonly
              />
              <button
                class="quantity-btn plus-btn"
                type="button"
                onclick="this.parentNode.querySelector('input[type=number]').stepUp()"
              >
                <i class="fal fa-plus"></i>
              </button>
              <button
                class="quantity-btn minus-btn"
                type="button"
                onclick="this.parentNode.querySelector('input[type=number]').stepDown()"
              >
                <i class="fal fa-minus"></i>
              </button>
            </div>
            <button class="item-button add-to-cart-btn" data-product-id="{{ $product->id }}" @disabled($product->quantity < 1)>
              <i class="fal fa-shopping-cart"></i>
            </button>
            <button class="item-fav" data-favorite-toggle data-product-id="{{ $product->id }}">
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
            </div>

          </div>
        </div>
      </div>
    </section>
@endsection

@push('scripts')
   <script>
              document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('filters-form');
                const sort = document.querySelector('select[name="sort_by"]');
                const container = document.getElementById('products-list');
                if (!form || !sort || !container) { return; }

                async function fetchAndRender(url) {
                  try {
                    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!res.ok) return;
                    const html = await res.text();
                    container.innerHTML = html;
                  } catch (e) {
                    console.error('Failed to update products list:', e);
                  }
                }

                sort.addEventListener('change', function () {
                  const params = new URLSearchParams(new FormData(form));
                  params.set('sort_by', sort.value);
                  params.set('ajax', '1');
                  const url = form.action + '?' + params.toString();
                  fetchAndRender(url);

                  // Update URL (without ajax=1) to keep current state navigable
                  params.delete('ajax');
                  window.history.replaceState({}, '', form.action + '?' + params.toString());
                });


              });
            </script>
@endpush