@extends('admin.layout.master')
{{-- extra css files --}}
@section('css')
    <link rel="stylesheet" type="text/css" href="{{asset('admin/app-assets/css-rtl/plugins/forms/validation/form-validation.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('admin/app-assets/vendors/css/extensions/sweetalert2.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('admin/app-assets/vendors/css/pickers/pickadate/pickadate.css')}}">
@endsection
{{-- extra css files --}}

@section('content')
<!-- // Basic multiple Column Form section start -->
<section id="multiple-column-form">
    <div class="row match-height">
        <div class="col-12">
            <div class="card">
                {{-- <div class="card-header">
                    <h4 class="card-title">{{__('admin.add')}}</h4>
                </div> --}}
                <div class="card-content">
                    <div class="card-body">
                        <form  method="POST" action="{{route('admin.coupons.store')}}" class="store form-horizontal" novalidate>
                            @csrf
                            <div class="form-body">
                                <div class="row">

                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="coupon-name">{{__('admin.coupon_name')}}</label>
                                            <div class="controls">
                                                <input type="text" name="coupon_name" class="form-control" placeholder="{{__('admin.enter_coupon_name')}}" required data-validation-required-message="{{__('admin.this_field_is_required')}}" >
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="coupon-number">{{__('admin.coupon_number')}}</label>
                                            <div class="controls">
                                                <input type="text" name="coupon_num" class="form-control" placeholder="{{__('admin.enter_coupon_number')}}" required data-validation-required-message="{{__('admin.this_field_is_required')}}" >
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="usage-time">{{ __('admin.number_of_use') }}</label>
                                            <div class="controls">
                                                <input type="number" min="1" name="usage_time" class="form-control" placeholder="{{ __('admin.enter_number_of_use') }}" value="{{ old('usage_time', 1) }}" required data-validation-required-message="{{ __('admin.this_field_is_required') }}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="audience_type">{{ __('admin.coupon_audience_type') }}</label>
                                            <div class="controls">
                                                <select name="audience_type" id="audience_type" class="form-control coupon-audience-type" required data-validation-required-message="{{__('admin.this_field_is_required')}}">
                                                    <option value="all">{{ __('admin.audience_all_clients') }}</option>
                                                    <option value="specific">{{ __('admin.audience_specific_clients') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-12 col-12 audience-clients-box" style="display: none;">
                                        <div class="form-group">
                                            <label for="client_ids">{{ __('admin.specific_clients') }}</label>
                                            <div class="controls">
                                                <select name="client_ids[]" id="client_ids" class="form-control select2" multiple>
                                                    @foreach($clients as $client)
                                                        <option value="{{ $client->id }}">{{ $client->name }} ({{ $client->phone }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="coupon-type">{{ __('admin.discount_type') }}</label>
                                            <div class="controls">
                                                <select name="type" id="coupon-type" class="form-control coupon-type-select" required data-validation-required-message="{{__('admin.this_field_is_required')}}">
                                                    <option value="ratio">{{ __('admin.percentage') }}</option>
                                                    <option value="number">{{ __('admin.fixed_number') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>


                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="first-name-column">{{__('admin.discount_value')}}</label>
                                            <div class="controls">
                                                <input type="number"  name="discount" class="discount form-control" placeholder="{{__('admin.type_the_value_of_the_discount')}}" required data-validation-required-message="{{__('admin.this_field_is_required')}}" >
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="first-name-column">{{__('admin.larger_value_for_discount')}}</label>
                                            <div class="controls">
                                                <input readonly type="number" name="max_discount" class="max_discount form-control" placeholder="{{__('admin.write_the_greatest_value_for_the_discount')}}" >
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="first-name-column">{{ __('admin.start_date') }}</label>
                                            <div class="controls">
                                                <input type="date" name="start_date" class="form-control" required
                                                    data-validation-required-message="{{ __('admin.this_field_is_required') }}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="expire-date">{{__('admin.expiry_date')}}</label>
                                            <div class="controls">
                                                <input type="date" name="expire_date" class="form-control">
                                                <small class="form-text text-muted">{{__('admin.leave_empty_for_no_expiry')}}</small>
                                            </div>
                                        </div>
                                    </div>



                                    <div class="col-12 d-flex justify-content-center mt-3">
                                        <button type="submit" class="btn btn-primary mr-1 mb-1 submit_button">{{__('admin.add')}}</button>
                                        <a href="{{ url()->previous() }}" type="reset" class="btn btn-outline-warning mr-1 mb-1">{{__('admin.back')}}</a>
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
    <script src="{{asset('admin/app-assets/vendors/js/forms/validation/jqBootstrapValidation.js')}}"></script>
    <script src="{{asset('admin/app-assets/js/scripts/forms/validation/form-validation.js')}}"></script>
    <script src="{{asset('admin/app-assets/vendors/js/extensions/sweetalert2.all.min.js')}}"></script>
    <script src="{{asset('admin/app-assets/js/scripts/extensions/sweet-alerts.js')}}"></script>
    <script src="{{asset('admin/app-assets/vendors/js/pickers/pickadate/picker.js')}}"></script>
    <script src="{{asset('admin/app-assets/vendors/js/pickers/pickadate/picker.date.js')}}"></script>
    <script src="{{asset('admin/app-assets/js/scripts/pickers/dateTime/pick-a-datetime.js')}}"></script>

    <script>
        function toggleMaxDiscountByType() {
            const type = $('#coupon-type').val();
            if (type === 'ratio') {
                $('.max_discount').prop('readonly', false);
            } else {
                $('.max_discount').prop('readonly', true).val('');
            }
        }

        function toggleAudienceClientsBox() {
            const type = $('#audience_type').val();
            if (type === 'specific') {
                $('.audience-clients-box').show();
            } else {
                $('.audience-clients-box').hide();
                $('#client_ids').val(null).trigger('change');
            }
        }

        $(document).on('change', '#audience_type', toggleAudienceClientsBox);
        $(document).on('change', '#coupon-type', toggleMaxDiscountByType);
        $(document).ready(toggleAudienceClientsBox);
        $(document).ready(toggleMaxDiscountByType);
    </script>
    <script>
        $(document).on('keyup','.discount', function () {
            if ($('input[name="type"]').val() == 'number') {
                $('.max_discount').val($(this).val());
            }else{
                if ($(this).val() > 100) {
                    $(this).val(null)
                    toastr.error('{{__('admin.Percentage_must_not_bigger_than_100')}}')
                }
                $('.max_discount').val(null);
            }
        });

    </script>

    {{-- show selected image script --}}
        @include('admin.shared.addImage')
    {{-- show selected image script --}}

    {{-- submit add form script --}}
        @include('admin.shared.submitAddForm')
    {{-- submit add form script --}}

@endsection
