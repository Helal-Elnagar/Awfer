@extends('admin.layout.master')
@section('css')
<link rel="stylesheet" type="text/css" href="{{ asset('admin/app-assets/css-rtl/plugins/forms/validation/form-validation.css') }}">
@endsection

@section('content')
<form method="POST" action="{{ route('admin.gifts.store') }}" class="store form-horizontal" novalidate>
    @csrf
    <section id="multiple-column-form">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-content">
                        <div class="card-body">
                            <div class="form-body">
                                <div class="row">

                                    {{-- Name --}}
                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="name">{{ __('admin.name') }}</label>
                                            <input type="text" id="name" class="form-control"
                                                placeholder="{{ __('admin.name') }}" name="name" required>
                                            @error('name')
                                                <span class="alert alert-danger"><small>{{ $message }}</small></span>
                                            @enderror
                                        </div>
                                    </div>

                                    {{-- Level Selection --}}
                                    <div class="col-md-6 col-12">
                                        <div class="form-group">
                                            <label for="level_id">{{ __('admin.level') }}</label>
                                            <select name="level_id" id="level_id" class="form-control select2" required>
                                                <option value="">{{ __('admin.choose') }}</option>
                                                @foreach($levels as $level)
                                                    <option value="{{ $level->id }}">{{ $level->name }} ({{ $level->points_required }} pts)</option>
                                                @endforeach
                                            </select>
                                            @error('level_id')
                                                <span class="alert alert-danger"><small>{{ $message }}</small></span>
                                            @enderror
                                        </div>
                                    </div>

                                    {{-- Submit Buttons --}}
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary mr-1 mb-1 waves-effect waves-light">
                                            {{ __('admin.add') }}
                                        </button>
                                        <a href="{{ route('admin.gifts.index') }}" class="btn btn-outline-warning mr-1 mb-1 waves-effect waves-light">
                                            {{ __('admin.back') }}
                                        </a>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</form>
@endsection

@section('js')
<script src="{{ asset('admin/app-assets/vendors/js/forms/validation/jquery.validate.min.js') }}"></script>
<script src="{{ asset('admin/app-assets/js/scripts/forms/validation/form-validation.js') }}"></script>
<script src="{{ asset('admin/app-assets/vendors/js/forms/select/select2.full.min.js') }}"></script>
<script src="{{ asset('admin/app-assets/js/scripts/forms/select/form-select2.js') }}"></script>

<script>
$(document).ready(function() {
    $('#level_id').select2({
        placeholder: "{{ __('admin.choose') }}",
        allowClear: true,
        width: '100%',
        dir: "{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}"
    });
});
</script>
@endsection
