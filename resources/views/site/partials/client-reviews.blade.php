<!-- Start Clients Reviews -->
<section class="client-reviews" id="clientReview-sec">
    <div class="container">
        <div class="row">
            <div class="col-md-6 col-sm-12 col-lg-4">
                <div class="content">
                    <h4
                        class="sub-title"
                        data-aos="fade-down"
                        data-aos-duration="400"
                        data-aos-easing="ease-out-cubic"
                    >
                        {{ __('site.clients_reviews') }}
                    </h4>
                    <h2 class="general-title">
                        {{ __('site.clients_reviews_general_title') }}
                    </h2>
                    <div class="swiper-buttons">
                        <div class="swiper-button-prev">
                            <i class="fa-light fa-arrow-right"></i>
                        </div>
                        <div class="swiper-button-next">
                            <i class="fa-light fa-arrow-left"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8 col-sm-12">
                <div class="mySwiper-parent">
                    <div class="swiper">
                        <div class="swiper-wrapper">
                            @foreach($clientReviews as $review)
                            <div class="swiper-slide">
                                <div class="item">
                                    <i class="fa-sharp fa-light icon fa-quote-right"></i>
                                    <p class="quote-text">
                                        {{ $review->body }}
                                    </p>
                                    <div class="reviewer">
                                        <div class="reviewer-img">
                                            <i class="fa-light fa-user"></i>
                                        </div>
                                        <span class="name">{{ $review->name }}</span>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="swiper-pagination"></div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- End Clients Reviews -->