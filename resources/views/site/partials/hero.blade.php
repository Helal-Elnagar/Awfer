<!-- Start Main -->
<main class="mainBanner">
    <div class="mainBanner-parent">
        <a href="#about-sec" class="scroll-btn">
            <span></span>
        </a>
        <div class="container">
            <div class="banner-desc">
                <h1
                    class="main-title"
                    data-aos-duration="400"
                    data-aos-easing="ease-out-cubic"
                    data-aos="fade-down"
                >
                    {{ __('site.main_title') }}
                </h1>
                <h3
                    data-aos-delay="500"
                    data-aos-duration="400"
                    data-aos-easing="ease-out-cubic"
                    data-aos="fade-down"
                    class="second-title"
                >
                    {{ __('site.second_title') }}
                </h3>
                <div class="download-links">
                    <a href="{{ $googlePlayLink ?? '#' }}"  target="_blank">
                        <img
                            class="google img-fluid"
                            src="{{ asset('assets/images/google-play.svg') }}"
                            alt=""
                        />
                    </a>
                    <a href="{{ $appStoreLink ?? '#' }}" target="_blank">
                        <img
                            class="apple img-fluid"
                            src="{{ asset('assets/images/app-store.svg') }}"
                            alt=""
                        />
                    </a>
                </div>
            </div>
        </div>
        <img
            class="hero-mobile"
            data-aos-easing="ease-out-cubic"
            data-aos-duration="500"
            data-aos="fade-right"
            data-aos-delay="800"
            src="{{ asset('assets/images/hero-bg.png') }}"
            alt=""
        />
    </div>
</main>
<!-- end of main -->