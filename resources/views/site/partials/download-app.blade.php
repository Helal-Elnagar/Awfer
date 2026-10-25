<!-- Start download app -->
<section class="download-app">
    <div class="download-bg">
        <div class="container">
            <div class="download-parent">
                <img
                    data-aos="fade-left"
                    data-aos-easing="ease-out-cubic"
                    data-aos-duration="500"
                    class="cta-mob"
                    src="{{ asset('assets/images/cta-mobile.png') }}"
                    alt=""
                />
                <div
                    class="content"
                    data-aos="fade-down"
                    data-aos-easing="ease-out-cubic"
                    data-aos-duration="500"
                    data-aos-delay="300"
                >
                    <h3 class="head">{{ __('site.download_app') }}</h3>
                    <p class="para">{{ __('site.download_app_para') }}</p>
                    <div class="download-links">
                        <a href="{{ $googlePlayLink ?? '#' }}">
                            <img
                                class="img-fluid"
                                src="{{ asset('assets/images/google-play.svg') }}"
                                alt=""
                            />
                        </a>
                        <a href="{{ $appStoreLink ?? '#' }}">
                            <img
                                class="img-fluid"
                                src="{{ asset('assets/images/app-store.svg') }}"
                                alt=""
                            />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- End download app -->