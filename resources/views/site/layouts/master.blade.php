<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="@yield('meta_description', '')" />
    <meta name="keywords" content="@yield('meta_keywords', '')" />
    <link rel="icon" href="{{ $settings['fav_icon'] ?? asset('favicon.ico') }}" />
        <link rel="stylesheet" href="{{asset('admin/app-assets/vendors/fonts/fontawesome.css')}}" />

    <link rel="stylesheet" href="{{ asset('assets/style/swiper-bundle.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/style/all.min.css') }}" />
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.9/css/select2.min.css"
        integrity="sha512-nMNlpuaDPrqlEls3IX/Q56H36qvBASwb3ipuo3MxeWbsQB1881ox0cRv7UPTgBlriqoynt35KjEwgGUeUXIPnw=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
    />
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css"
        integrity="sha512-1cK78a1o+ht2JcaW6g8OXYwqpev9+6GqOkz9xmBN9iUUhIndKtxwILGWYOSibOKjLsEdjyjZvYDq/cZwNeak0w=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
    />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" integrity="sha512-vKMx8UnXk60zUwyUnUPM3HbQo8QfmNx7+ltw8Pm5zLusl1XIfwcxo8DbWCqMGKaWeNxWA8yrx5v3SaVpMvR3CA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    @php $isRtl = app()->getLocale() == 'ar'; @endphp
    @if ($isRtl)
        <link rel="stylesheet" href="{{ asset('assets/style/bootstrap.rtl.min.css') }}">
    @else
        <link rel="stylesheet" href="{{ asset('assets/style/bootstrap.min.css') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('assets/style/main.css') }}" />
    
    @stack('styles')
    
    <title>@yield('title', $settings['name_' . app()->getLocale()] ?? config('app.name'))</title>
</head>

<body data-bs-spy="scroll" data-bs-target="#navbar-example">
    <!-- preloader -->
    <div class="preloader-container">
        <div class="preloader"></div>
    </div>
    <!-- End preloader -->

    @include('site.partials.header')

    @yield('content')

    @include('site.partials.footer')

    <!-- to top button -->
    <button class="toTop">
        <i class="fa-light toTop-icon fa-arrow-up"></i>
    </button>
    <!-- end of to top button -->

    <script src="{{ asset('assets/js/jquery-3.7.0.min.js') }}"></script>
    <script src="{{ asset('assets/js/swiper-bundle.min.js') }}"></script>
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.9/js/select2.min.js"
        integrity="sha512-9p/L4acAjbjIaaGXmZf0Q2bV42HetlCLbv8EP0z3rLbQED2TAFUlDvAezy7kumYqg5T8jHtDdlm1fgIsr5QzKg=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
    ></script>
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"
        integrity="sha512-A7AYk1fGKX6S2SsHywmPkrnzTZHrgiVT7GcQkLGDe2ev0aWb8zejytzS8wjo7PGEXKqJOrjQ4oORtnimIRZBtw=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
    ></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js" integrity="sha512-VEd+nq25CkR676O+pLBnDW09R7VQX9Mdiij052gVCp5yVH3jGtH70Ho/UUv4mJDsEdTvqRCFZg0NKGiojGnUCw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    @include('site.partials.scripts')
    
    @stack('scripts')
</body>
</html>