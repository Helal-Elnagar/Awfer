@extends('admin.layout.master')
{{-- extra css files --}}
@section('css')
    <link rel="stylesheet" type="text/css"
        href="{{ asset('admin/app-assets/css-rtl/plugins/forms/validation/form-validation.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin/app-assets/vendors/css/extensions/sweetalert2.min.css') }}">
@endsection
{{-- extra css files --}}

@section('content')
    <!-- // Basic multiple Column Form section start -->
    <section id="multiple-column-form">
        <div class="row match-height">
            <div class="col-12">
                <div class="card">
                    {{-- <div class="card-header">
                        <h4 class="card-title">{{ __('admin.edit') }}</h4>
                    </div> --}}
                    <div class="card-content">
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.regions.update', ['id' => $region->id]) }}"
                                class="store form-horizontal" novalidate>
                                @csrf
                                @method('PUT')
                                <div class="form-body">
                                    <div class="row">

                                        <div class="col-12">
                                            {{-- Remove tabs, show all language inputs in one column --}}
                                            @foreach (languages() as $lang)
                                                <div class="col-md-12 col-12">
                                                    <div class="form-group">
                                                        <label for="first-name-column">
                                                            {{__('admin.name')}} {{ $lang }}
                                                            <span class="text-danger">*</span>
                                                        </label>
                                                        <div class="controls">
                                                            <input type="text" name="name[{{$lang}}]" class="form-control"
                                                                value="{{$region->getTranslations('name')[$lang]??''}}"
                                                                placeholder="{{__('admin.write') . __('admin.name')}} {{ $lang }}"
                                                                required data-validation-required-message="{{__('admin.this_field_is_required')}}">
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                            <div class="col-md-12 col-12">
                                                <div class="form-group">
                                                    <label for="first-name-column">{{ __('admin.country') }}</label>
                                                    <div class="controls">
                                                        <select name="country_id" class="select2 form-control" required
                                                            data-validation-required-message="{{ __('admin.this_field_is_required') }}">
                                                            <option value>{{ __('admin.choose_the_country') }}</option>
                                                            @foreach ($countries as $country)
                                                                <option value="{{ $country->id }}" @if($region->country_id == $country->id) selected @endif>{{ $country->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-12 col-12">
                                                <div class="form-group">
                                                    <label for="region-location-map">{{ __('admin.region_center') }}</label>
                                                    <div id="region-location-map" style="height: 300px; border: 1px solid #ccc;"></div>
                                                    <input type="hidden" name="latitude" id="latitude" value="{{ $region->latitude }}">
                                                    <input type="hidden" name="longitude" id="longitude" value="{{ $region->longitude }}">
                                                    <small class="form-text text-muted">{{ __('admin.select_region_center_on_map') }}</small>
                                                </div>
                                            </div>

                                        </div>
                                                </div>
                                            </div>

                                        
                                        </div>

                                        <div class="col-12 d-flex justify-content-center mt-3">
                                            <button type="submit"
                                                class="btn btn-primary mr-1 mb-1 submit_button">{{ __('admin.update') }}</button>
                                            <a href="{{ url()->previous() }}" type="reset"
                                                class="btn btn-outline-warning mr-1 mb-1">{{ __('admin.back') }}</a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
@section('js')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <script src="{{ asset('admin/app-assets/vendors/js/forms/validation/jqBootstrapValidation.js') }}"></script>
    <script src="{{ asset('admin/app-assets/js/scripts/forms/validation/form-validation.js') }}"></script>
    <script src="{{ asset('admin/app-assets/vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('admin/app-assets/js/scripts/extensions/sweet-alerts.js') }}"></script>

    {{-- show selected image script --}}
    @include('admin.shared.addImage')
    {{-- show selected image script --}}

    {{-- submit edit form script --}}
    @include('admin.shared.submitEditForm')
    {{-- submit edit form script --}}

    <script>
        let regionMap = L.map('region-location-map').setView([24.7136, 46.6753], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(regionMap);
        let regionMarker;
        @if ($region->latitude !== null && $region->longitude !== null)
            regionMarker = L.marker([{{ $region->latitude }}, {{ $region->longitude }}]).addTo(regionMap);
            regionMap.setView([{{ $region->latitude }}, {{ $region->longitude }}], 10);
        @endif
        regionMap.on('click', function(e) {
            if (regionMarker) {
                regionMarker.setLatLng(e.latlng);
            } else {
                regionMarker = L.marker(e.latlng).addTo(regionMap);
            }
            document.getElementById('latitude').value = e.latlng.lat;
            document.getElementById('longitude').value = e.latlng.lng;
        });
        L.Control.geocoder({
                defaultMarkGeocode: false
            })
            .on('markgeocode', function(e) {
                var latlng = e.geocode.center;
                regionMap.setView(latlng, 14);
                if (regionMarker) {
                    regionMarker.setLatLng(latlng);
                } else {
                    regionMarker = L.marker(latlng).addTo(regionMap);
                }
                document.getElementById('latitude').value = latlng.lat;
                document.getElementById('longitude').value = latlng.lng;
            })
            .addTo(regionMap);
    </script>
@endsection
