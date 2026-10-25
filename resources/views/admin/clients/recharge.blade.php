@extends('admin.layout.master')

@section('css')
    <link rel="stylesheet" type="text/css" href="{{asset('admin/app-assets/css-rtl/plugins/forms/validation/form-validation.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('admin/app-assets/vendors/css/extensions/sweetalert2.min.css')}}">
@endsection

@section('content')
<section id="multiple-column-form">
    <div class="row match-height">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ __('admin.recharge_wallet') }}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.clients.processRecharge') }}" class="store form-horizontal" novalidate>
                            @csrf
                            <div class="form-body">
                                <div class="row">
                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="user_id">{{ __('admin.select_client') }}</label>
                                            <div class="controls">
                                                <select name="user_id" id="user_id" class="select2 form-control" required data-validation-required-message="{{ __('admin.this_field_is_required') }}">
                                                    <option value="">{{ __('admin.select_client') }}</option>
                                                    @foreach($clients as $client)
                                                        <option value="{{ $client->id }}">{{ $client->name }} ({{ $client->phone }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="amount">{{ __('admin.recharge_amount') }}</label>
                                            <div class="controls">
                                                <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control" placeholder="{{ __('admin.recharge_amount') }}" required data-validation-required-message="{{ __('admin.this_field_is_required') }}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 d-flex justify-content-center mt-3">
                                        <button type="submit" class="btn btn-primary mr-1 mb-1 submit_button">{{ __('admin.send') }}</button>
                                        <a href="{{ url()->previous() }}" type="reset" class="btn btn-outline-warning mr-1 mb-1">{{ __('admin.back') }}</a>
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
    <script src="{{ asset('admin/app-assets/vendors/js/forms/validation/jqBootstrapValidation.js') }}"></script>
    <script src="{{ asset('admin/app-assets/js/scripts/forms/validation/form-validation.js') }}"></script>
    <script src="{{ asset('admin/app-assets/vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('admin/app-assets/js/scripts/extensions/sweet-alerts.js') }}"></script>

    @include('admin.shared.submitAddForm')
@endsection
