@extends('admin.layout.master')

@section('content')
<!-- // Basic multiple Column Form section start -->
<section id="multiple-column-form">
    <div class="row match-height">
        <div class="col-12">
            <div class="card">
                {{-- <div class="card-header">
                    <h4 class="card-title">{{__('admin.view') . ' ' . __('admin.gift')}}</h4>
                </div> --}}
                <div class="card-content">
                    <div class="card-body">
                        <form class="show form-horizontal">
                            <div class="form-body">
                                <div class="row">

                                    {{-- Name --}}
                                 <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="name">{{ __('admin.name') }}</label>
                                            <input type="text" id="name" class="form-control"
                                                placeholder="{{ __('admin.name') }}" name="name"
                                                value="{{ old('name', $gift->name) }}" required>
                                            @error('name')
                                            <span class="alert alert-danger">
                                                <small class="errorTxt">{{ $message }}</small>
                                            </span>
                                            @enderror
                                        </div>
                                    </div>

                                    {{-- Minimum Points --}}
                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="min_points">{{ __('admin.min_points') }}</label>
                                            <input type="number" id="min_points" class="form-control"
                                                   value="{{ $gift->min_points }}" disabled>
                                        </div>
                                    </div>

                                    {{-- Expires After Days --}}
                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="expires_after_days">{{ __('admin.expires_after_days') }}</label>
                                            <input type="number" id="expires_after_days" class="form-control"
                                                   value="{{ $gift->expires_after_days }}" disabled>
                                            <small class="text-muted">{{ __('admin.days') }}</small>
                                        </div>
                                    </div>

                                    {{-- Created Date --}}
                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="created_at">{{ __('admin.created_at') }}</label>
                                            <input type="text" id="created_at" class="form-control"
                                                   value="{{ $gift->created_at->format('d/m/Y H:i') }}" disabled>
                                        </div>
                                    </div>

                                    {{-- Updated Date --}}
                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="updated_at">{{ __('admin.updated_at') }}</label>
                                            <input type="text" id="updated_at" class="form-control"
                                                   value="{{ $gift->updated_at->format('d/m/Y H:i') }}" disabled>
                                        </div>
                                    </div>

                                    {{-- Coupon Section --}}
                                    <div class="col-12 mt-3">
                                        <h5 class="mb-3">{{ __('admin.coupon') }}</h5>
                                        @if($gift->coupon)
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <strong>{{ __('admin.coupon_num') }}:</strong><br>
                                                            {{ $gift->coupon->coupon_num }}
                                                        </div>
                                                        <div class="col-md-2">
                                                            <strong>{{ __('admin.type') }}:</strong><br>
                                                            {{ $gift->coupon->type }}
                                                        </div>
                                                        <div class="col-md-2">
                                                            <strong>{{ __('admin.discount') }}:</strong><br>
                                                            {{ $gift->coupon->discount }}{{ $gift->coupon->type == 'ratio' ? '%' : ' ' . __('admin.currency') }}
                                                        </div>
                                                        <div class="col-md-2">
                                                            <strong>{{ __('admin.max_discount') }}:</strong><br>
                                                            {{ $gift->coupon->max_discount ?? '-' }}
                                                        </div>
                                                        {{-- <div class="col-md-3">
                                                            <strong>{{ __('admin.status') }}:</strong><br>
                                                            <span class="badge badge-{{ $gift->coupon->status == 'available' ? 'success' : 'danger' }}">
                                                                {{ $gift->coupon->status }}
                                                            </span>
                                                        </div> --}}
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="alert alert-info">
                                                {{ __('admin.no_coupons_assigned') }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="col-12 mt-3">
                                        <a href="{{ route('admin.gifts.edit', $gift->id) }}" class="btn btn-primary mr-1 mb-1 waves-effect waves-light">{{ __('admin.edit') }}</a>
                                        <a href="{{ route('admin.gifts.index') }}" class="btn btn-outline-warning mr-1 mb-1 waves-effect waves-light">{{ __('admin.back') }}</a>
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
    <script>
        $('.show input').attr('disabled' , true)
        $('.show textarea').attr('disabled' , true)
        $('.show select').attr('disabled' , true)
    </script>
@endsection