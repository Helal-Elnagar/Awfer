
<!-- Start Footer -->
<footer>
    <div class="container">
        <div class="footer-content">
            <div class="main-links">
                <a href="{{ route('site.about-us') }}"> {{ __('site.about_us') }} </a>
                <a href="{{ route('site.term') }}"> {{ __('site.terms') }} </a>
                <a href="{{ route('site.privacy-policy') }}"> {{ __('site.policy') }} </a>
                <a href="{{ route('site.fqs') }}"> {{ __('site.fqs') }} </a>
            </div>
            <p class="copyright"> {{ __('site.copyright') }} {{ date('Y') }}</p>
            <div class="tasawk">
                {{ __('site.design_by') }}
                <a target="_blank" href="https://tasawk.com.sa/">
                    <img src="{{ asset('assets/images/tasawk-ar-dark.png') }}" alt="" />
                </a>
            </div>
        </div>
    </div>
</footer>
<!-- End Footer -->