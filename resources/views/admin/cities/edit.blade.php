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
        <div class="city match-height">
            <div class="col-12">
                <div class="card">
                    {{-- <div class="card-header">
                        <h4 class="card-title">{{ __('admin.edit') }}</h4>
                    </div> --}}
                    <div class="card-content">
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.cities.update', ['id' => $city->id]) }}"
                                class="store form-horizontal" novalidate>
                                @csrf
                                @method('PUT')
                                <div class="form-body">
                                    <div class="city">
                                        <div class="row">
                                            
                                            <div class="col-12">
                                                <div class="col-12">
                                                    <ul class="nav nav-tabs mb-3">
                                                        @foreach (languages() as $lang)
                                                            <li class="nav-item">
                                                                <a class="nav-link @if($loop->first) active @endif"  data-toggle="pill" href="#first_{{$lang}}" aria-expanded="true">{{  __('admin.data') }} {{ $lang }}</a>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div> 


                                                <div class="tab-content">
                                                    @foreach (languages() as $lang)
                                                        <div role="tabpanel" class="tab-pane fade @if($loop->first) show active @endif " id="first_{{$lang}}" aria-labelledby="first_{{$lang}}" aria-expanded="true">
                                                            <div class="col-md-12 col-12">
                                                                <div class="form-group">
                                                                    <label for="first-name-column">{{__('admin.name')}} {{ $lang }}</label>
                                                                    <div class="controls">
                                                                        <input type="text" value="{{$city->getTranslations('name')[$lang]??''}}" name="name[{{$lang}}]" class="form-control" placeholder="{{__('admin.write') . __('admin.name')}} {{ $lang }}" @if($loop->first) required data-validation-required-message="{{__('admin.this_field_is_required')}}" @endif >
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>


                                                <div class="col-md-12 col-12">
                                                    <div class="form-group">
                                                        <label for="first-name-column">{{ __('admin.region') }}</label>
                                                        <div class="controls">
                                                            <select name="region_id" class="select2 form-control" required
                                                                data-validation-required-message="{{ __('admin.this_field_is_required') }}">
                                                                <option value>{{ __('admin.choose_the_region') }}</option>
                                                                @foreach ($regions as $region)
                                                                    <option
                                                                        {{ $region->id == $city->region_id ? 'selected' : '' }}
                                                                        value="{{ $region->id }}">{{ $region->name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 col-12">
                                                    <div class="form-group">
                                                        <label for="delivery_fee">{{ __('admin.city_delivery_fee') }}</label>
                                                        <div class="controls">
                                                            <input type="number" step="0.01" min="0" name="delivery_fee" id="delivery_fee" value="{{ old('delivery_fee', $city->delivery_fee) }}" class="form-control" placeholder="{{ __('admin.city_delivery_fee') }}">
                                                            <small class="form-text text-muted">{{ __('admin.city_delivery_fee_hint') }}</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-group">
                                                        <label for="city-boundary-map">{{ __('admin.boundary') }}</label>
                                                        <div id="city-boundary-map" style="height: 300px; border: 1px solid #ccc;"></div>
                                                        @php
                                                            $boundaryPoints = $city->boundaryPoints();
                                                        @endphp
                                                        @if (count($boundaryPoints))
                                                            <div class="boundary-coordinates mt-2">
                                                                <h6 class="mb-1">{{ __('admin.boundary_coordinates') }}</h6>
                                                                <ul class="list-unstyled mb-0">
                                                                    @foreach ($boundaryPoints as $boundaryIndex => $boundaryPoint)
                                                                        <li>
                                                                            {{ __('admin.boundary_point') }} {{ $boundaryIndex + 1 }}:<br>
                                                                            {{ __('admin.latitude') }}: {{ number_format($boundaryPoint[0], 6, '.', '') }}<br>
                                                                            {{ __('admin.longitude') }}: {{ number_format($boundaryPoint[1], 6, '.', '') }}
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                        @endif
                                                        <input type="hidden" name="boundary" id="boundary" value="{{ old('boundary', $city->boundary) }}">
                                                        <small class="form-text text-muted">{{ __('admin.draw_delivery_boundary_hint') }}</small>
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
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
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
        var regionCenters = @json($regions->mapWithKeys(function ($r) { return [$r->id => ['lat' => $r->latitude, 'lng' => $r->longitude]]; }));

        let cityBoundaryMap = L.map('city-boundary-map').setView([24.7136, 46.6753], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(cityBoundaryMap);
        let boundaryDrawnItems = new L.FeatureGroup();
        cityBoundaryMap.addLayer(boundaryDrawnItems);
        let boundaryDrawControl = new L.Control.Draw({
            draw: {
                polygon: true,
                marker: false,
                polyline: false,
                rectangle: false,
                circle: false,
                circlemarker: false
            },
            edit: {
                featureGroup: boundaryDrawnItems
            }
        });
        cityBoundaryMap.addControl(boundaryDrawControl);
        L.Control.geocoder({
                defaultMarkGeocode: false
            })
            .on('markgeocode', function(e) {
                var latlng = e.geocode.center;
                cityBoundaryMap.setView(latlng, 16);
            })
            .addTo(cityBoundaryMap);

        function centerCityMapOnRegion(regionId) {
            var center = regionCenters[regionId];
            if (center && center.lat !== null && center.lat !== undefined && center.lng !== null && center.lng !== undefined) {
                cityBoundaryMap.setView([center.lat, center.lng], 10);
            }
        }

        $('select[name="region_id"]').on('change', function() {
            centerCityMapOnRegion($(this).val());
        });

        @if (!empty($city->boundaryPoints()))
            var existingBoundary = @json($city->boundaryPoints());
            var existingLayer = L.polygon(existingBoundary).addTo(boundaryDrawnItems);
            cityBoundaryMap.fitBounds(existingLayer.getBounds());
        @elseif ($city->region_id)
            centerCityMapOnRegion({{ $city->region_id }});
        @endif

        function serializeBoundary(layer) {
            let rings = layer.getLatLngs();
            let ring = rings[0] || [];
            let coords = ring.map(function(p) {
                return [p.lat, p.lng];
            });
            document.getElementById('boundary').value = JSON.stringify(coords);
        }

        cityBoundaryMap.on(L.Draw.Event.CREATED, function(e) {
            boundaryDrawnItems.clearLayers();
            let layer = e.layer;
            boundaryDrawnItems.addLayer(layer);
            serializeBoundary(layer);
        });
        cityBoundaryMap.on('draw:edited', function(e) {
            let layers = e.layers;
            layers.eachLayer(function(layer) {
                serializeBoundary(layer);
            });
        });
    </script>
@endsection
