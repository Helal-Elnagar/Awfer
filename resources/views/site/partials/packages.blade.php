<!-- start packages -->
<section class="packages" id="packages-sec">
    <div class="container">
        <div class="titles-parent">
            <h4 class="sub-title">{{ __('site.packages') }}</h4>
            <h2
                data-aos="fade-zoom-in"
                data-aos-easing="ease-in-back"
                data-aos-delay="200"
                data-aos-offset="0"
                class="general-title"
            >
                {{ __('site.packages_general_title') }}
            </h2>
        </div>
        <div class="packages-parent">
            @foreach($plans as $index => $plan)
                <div class="packag-item {{ $index == 0 ? 'bronze' : ($index == 1 ? 'silver bestseller' : 'gold') }}">
                    <div class="icon">
                        <i class="fas fa-medal"></i>
                    </div>
                    <h4 class="title">{{ $plan->name }}</h4>
                    <p class="desc">{{ $plan->description }}</p>
                    <h3 class="price">
                        @if($plan->price)
                            {{ $plan->price }} <span>{{ __('site.currency') }}/{{ $plan->duration }} {{ __('site.day') }}</span>
                        @else
                            {{ $plan->price_before_discount }} <span>{{ __('site.currency') }}/{{ $plan->duration }} {{ __('site.day') }}</span>
                        @endif
                    </h3>
                    @if($plan->features && $plan->features->count() > 0)
                        <ul class="list">
                            @foreach($plan->features as $feature)
                                <li>{{ $feature->feature }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <a href="{{ route('provider.register') }}" class="btn">{{ __('site.start_now') }}</a>
                    @if($index == 1)
                        <span class="label"> {{ __('site.most_popular') }} </span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
<!-- End packages -->