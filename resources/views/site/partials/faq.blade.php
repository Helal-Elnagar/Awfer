<!-- start faq -->
<section class="faq" id="faq-sec">
    <div class="container">
        <div class="titles-parent">
            <h4 class="sub-title">{{ __('site.fqs') }}</h4>
            <h2
                data-aos="fade-zoom-in"
                data-aos-easing="ease-in-back"
                data-aos-delay="200"
                data-aos-offset="0"
                class="general-title"
            >
                {{ __('site.fqs_general_title') }}
            </h2>
        </div>
        <div class="questions-parent">
            @foreach($fqs as $index => $faq)
            <div class="main-question">
                <div class="ques-details">
                    <div class="ques-num">{{ sprintf('%02d', $index + 1) }}</div>
                    <h3 class="ques-text">{{ $faq->question }}</h3>
                    <i class="fa-regular icon fa-plus"></i>
                </div>
                <div class="ques-answer">
                    {!! $faq->answer !!}
                </div>
            </div>
            @endforeach
        </div>

        <div class="readmore-holder">
            <a href="{{ route('site.fqs') }}" class="read-more"> {{ __('site.read_more') }} </a>
        </div>
    </div>
</section>
<!-- End faq -->