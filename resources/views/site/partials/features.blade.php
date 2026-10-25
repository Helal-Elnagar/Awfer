<!-- Start features -->
<section class="features" id="features-sec">
    <div class="container">
        <div class="features-parent">
            <img
                class="mobile-hand-img"
                data-aos-anchor=".list-item"
                data-aos-duration="400"
                data-aos-delay="0"
                data-aos="fade-left"
                data-aos-easing="ease-out-cubic"
                src="{{ asset('assets/images/features-bg.png') }}"
                alt=""
            />
            <div class="features-cont">
                <div class="titles-parent">
                    <h4 class="sub-title">{{ __('site.features_sub_title') }}</h4>
                    <h2 class="general-title">
                        {{ __('site.features_general_title') }}
                    </h2>
                </div>
                <div class="features-list">
                    @foreach($features as $feature)
                        <div class="list-item">
                            <div class="icon-circle">
                                <i class="{{ $feature->icon }} icon"></i>
                            </div>
                            <h4 class="item-title">{{ $feature->title }}</h4>
                        </div>
                    @endforeach

                </div>
            </div>
        </div>
    </div>
</section>
<!-- End features -->