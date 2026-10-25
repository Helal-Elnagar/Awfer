<!-- Start contact -->
<section class="contact" id="contact-sec">
    <div class="container">
        <div class="titles-parent">
            <h4 class="sub-title">{{ __('site.contact_us') }}</h4>
            <h2
                class="general-title"
                data-aos="fade-down"
                data-aos-easing="ease-in"
                data-aos-duration="400"
            >
                {{ __('site.contact_general_title') }}
            </h2>
        </div>
        <div class="contact-content">
            <div class="form-div">
                <p class="text">
                    {{ __('site.contact_title') }}
                </p>
                <form class="myform" action="{{url('send-message')}}" enctype="multipart/form-data" method="POST">
                    @csrf
                    <div class="inputs">
                        <input type="text" name="name" placeholder="{{__('site.name')}}" required />
                        <input type="tel" name="phone" placeholder="{{ __('site.phone') }}" required />
                        <input type="hidden" name="title" value="{{ __('site.contact_us') }}"  />

                        <select class="myselect" name="city_id" required>
                            <option disabled selected value="">{{ __('site.city') }}*</option>
                            @foreach($cities as $city)
                            <option value="{{ $city->id }}">{{ $city->name }}</option>
                            @endforeach
                        </select>
                        <input type="email" name="email" placeholder="{{ __('site.email') }}*" required />
                        <textarea placeholder="{{ __('site.message') }}*" name="body" id="message" required></textarea>
                    </div>
                    <div class="submit-holder">
                        <button type="submit" class="btn-main">{{ __('site.send_message') }}</button>
                    </div>
                </form>
            </div>
            <div class="social-div">
                <h3 class="head">{{ __('site.contact_us') }}</h3>
                <div class="social-items">
                    @if(isset($contacts['1']))
                    <div class="item">
                        <div class="d-flex text-icon">
                            <i class="fa-regular fa-mobile icon"></i>
                            <span class="text">{{ __('site.phone') }}</span>
                        </div>
                        <a href="tel:{{ $contacts['1']['value'] }}" class="item-val phone-val"> {{ $contacts['1']['value'] }} </a>
                    </div>
                    @endif
                    @if(isset($contacts['2']))
                    <div class="item">
                        <div class="d-flex text-icon">
                            <i class="fa-brands fa-whatsapp icon"></i>
                            <span class="text">{{ __('site.whatsapp') }}</span>
                        </div>
                        <a href="https://wa.me/{{ $contacts['2']['value'] }}" class="item-val phone-val"> {{ $contacts['2']['value'] }} </a>
                    </div>
                    @endif
                    @if(isset($contacts['2']))
                    <div class="item">
                        <div class="d-flex text-icon">
                            <i class="fa-regular fa-envelope icon"></i>
                            <span class="text">{{ __('site.email') }}</span>
                        </div>
                        <a href="mailto:{{ $contacts['2'] }}" class="item-val"> {{ $contacts['0']['value'] }} </a>
                    </div>
                    @endif
                </div>
                <div class="social-icons">
                    @php
                        $iconMap = [
                            'instagram' => 'fa-brands fa-instagram',
                            'twitter'   => 'fa-brands fa-x-twitter',
                            'youtube'   => 'fa-brands fa-youtube',
                            'snapchat'  => 'fa-brands fa-snapchat',
                            'facebook'  => 'fa-brands fa-facebook-f',
                        ];
                        $links = [];
                        if(isset($socialLinks) && is_array($socialLinks)){
                            $links = $socialLinks;
                        } elseif(isset($socials)) {
                            try {
                                $links = collect($socials)->mapWithKeys(function($s){
                                    return [strtolower($s->name) => $s->link ?? $s->url ?? '#'];
                                })->toArray();
                            } catch (\Throwable $e) {
                                $links = [];
                            }
                        }
                    @endphp
                    @foreach($iconMap as $name => $class)
                        <a href="{{ $links[$name] ?? '#' }}" target="_blank" rel="noopener">
                            <i class="{{ $class }} icon"></i>
                        </a>
                    @endforeach
                </div>
              
            </div>
        </div>
    </div>
</section>
<!-- End contact -->