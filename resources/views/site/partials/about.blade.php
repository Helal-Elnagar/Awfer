<!-- Start About -->
<section class="about" id="about-sec">
    <div class="container">
        <h4 class="sub-title">{{ __('site.about_us') }}</h4>

        <p class="para">
            {!! $about !!}
        </p>
        <div class="readmore-holder">
            <a href="{{ route('site.about-us') }}" class="read-more">{{ __('site.read_more') }}</a>
        </div>
        <div class="items">
            <div
                class="item"
                data-aos-easing="ease-out-cubic"
                data-aos-duration="400"
                data-aos="fade-up"
                data-aos-delay="0"
            >
                <i class="fa-light fa-filter icon"></i>
                <h4 class="title">{{ __('site.more_than_500_providers') }}</h4>
            </div>
            <div
                class="item"
                data-aos-easing="ease-out-cubic"
                data-aos-duration="400"
                data-aos="fade-up"
                data-aos-delay="150"
            >
                <i class="fa-light fa-circle-check icon"></i>
                <h4 class="title">{{ __('site.easy_and_fast_bookings') }}</h4>
            </div>
            <div
                class="item"
                data-aos-easing="ease-out-cubic"
                data-aos-duration="400"
                data-aos="fade-up"
                data-aos-delay="200"
            >
                <i class="fa-light fa-star icon"></i>
                <h4 class="title">{{ __('site.real_client_reviews') }}</h4>
            </div>
            <div
                class="item"
                data-aos-easing="ease-out-cubic"
                data-aos-duration="400"
                data-aos="fade-up"
                data-aos-delay="250"
            >
                <i class="fa-light fa-comments icon"></i>
                <h4 class="title">{{ __('site.direct_chat_with_providers') }}</h4>
            </div>
        </div>
    </div>
</section>
<!-- End About -->