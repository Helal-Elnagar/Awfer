<!-- header -->
<header>
    <div class="container">
        <div class="header-content">
            <div class="logo" style="width: 180px; height: 44px; display: inline-block;">
                @php
                    $appName = $settings['name_' . app()->getLocale()] ?? config('app.name');
                @endphp
                <a href="{{ route('intro') }}" style="display: inline-block; width: 100%; height: 100%;">
                    <img class="logo-dark" src="{{ $settings['logo'] ?? asset('assets/images/logo-dark.svg') }}" alt="{{ $appName }}" style="width: 100%; height: 100%; object-fit: contain;" />
                    <img class="logo-light" src="{{ $settings['logo'] ?? asset('assets/images/logo-dark.svg') }}" alt="{{ $appName }}" style="width: 100%; height: 100%; object-fit: contain;" />
                </a>
            </div>
            <div class="navigation">
                <ul class="big-menu list-unstyled">
                    <li><a href="#about-sec" class="nav-link" data-section="about-sec">{{ __('site.about_us') }}</a></li>
                    <li><a href="#features-sec" class="nav-link" data-section="features-sec">{{ __('site.app_features') }}</a></li>
                    <li><a href="#appScreens-sec" class="nav-link" data-section="appScreens-sec">{{ __('site.app_images') }}</a></li>
                    <li><a href="#clientReview-sec" class="nav-link" data-section="clientReview-sec">{{ __('site.clients_reviews') }}</a></li>
                    <li><a href="#faq-sec" class="nav-link" data-section="faq-sec">{{ __('site.fqs') }}</a></li>
                    <li><a href="#contact-sec" class="nav-link" data-section="contact-sec">{{ __('site.contact_us') }}</a></li>
                    <li>
                        @php $isRtl = app()->getLocale() == 'ar'; @endphp
                        @if ($isRtl)
                            <a href="{{ url('lang/en') }}">
                                English 
                            </a>
                        @else
                            <a href="{{ url('lang/ar') }}">
                                عربي 
                            </a>
                        @endif
                    </li>
                </ul>
            </div>
            <div class="links-bars-holder">
                <div class="header-links">
                    <a href="{{ $googlePlayLink ?? '#' }}"  target="_blank">
                        <i class="fa-brands google fa-google-play icon"></i>
                    </a>
                    <a href="{{ $appStoreLink ?? '#' }}" target="_blank">
                        <i class="fa-brands apple fa-apple icon"></i>
                    </a>
                </div>
                <button class="openBtn"><i class="fa-regular fa-bars"></i></button>
            </div>
        </div>
    </div>
</header>
<!-- end of header -->