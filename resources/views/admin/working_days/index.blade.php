@extends('admin.layout.master')

@section('css')
    <link rel="stylesheet" type="text/css" href="{{ asset('admin/app-assets/vendors/css/extensions/toastr.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin/app-assets/css-rtl/plugins/extensions/toastr.css') }}">
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="card-title">{{ __('admin.working_days') }}</h4>
        </div>
        <div class="card-body">
            <form id="working-days-form" action="{{ route('admin.working-days.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ __('admin.day') }}</th>
                                <th>{{ __('admin.is_working') }}</th>
                                <th>{{ __('admin.morning_from') }}</th>
                                <th>{{ __('admin.morning_to') }}</th>
                                <th>{{ __('admin.evening_from') }}</th>
                                <th>{{ __('admin.evening_to') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($workingDays as $workingDay)
                                <tr>
                                    <td>
                                        {{ __('admin.' . $workingDay->day) }}
                                        <input type="hidden" name="days[{{ $workingDay->day }}][day]" value="{{ $workingDay->day }}">
                                    </td>
                                    <td>
                                        <div class="custom-control custom-switch custom-switch-success">
                                            <input
                                                type="checkbox"
                                                class="custom-control-input js-working-toggle"
                                                id="is_working_{{ $workingDay->day }}"
                                                name="days[{{ $workingDay->day }}][is_working]"
                                                value="1"
                                                {{ $workingDay->is_working ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="is_working_{{ $workingDay->day }}">
                                                <span class="switch-icon-left"><i class="feather icon-check"></i></span>
                                                <span class="switch-icon-right"><i class="feather icon-x"></i></span>
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <input
                                            type="time"
                                            class="form-control js-time-input"
                                            name="days[{{ $workingDay->day }}][morning_from]"
                                            value="{{ $workingDay->morning_from ? substr((string) $workingDay->morning_from, 0, 5) : '' }}"
                                            {{ $workingDay->is_working ? '' : 'disabled' }}>
                                    </td>
                                    <td>
                                        <input
                                            type="time"
                                            class="form-control js-time-input"
                                            name="days[{{ $workingDay->day }}][morning_to]"
                                            value="{{ $workingDay->morning_to ? substr((string) $workingDay->morning_to, 0, 5) : '' }}"
                                            {{ $workingDay->is_working ? '' : 'disabled' }}>
                                    </td>
                                    <td>
                                        <input
                                            type="time"
                                            class="form-control js-time-input"
                                            name="days[{{ $workingDay->day }}][evening_from]"
                                            value="{{ $workingDay->evening_from ? substr((string) $workingDay->evening_from, 0, 5) : '' }}"
                                            {{ $workingDay->is_working ? '' : 'disabled' }}>
                                    </td>
                                    <td>
                                        <input
                                            type="time"
                                            class="form-control js-time-input"
                                            name="days[{{ $workingDay->day }}][evening_to]"
                                            value="{{ $workingDay->evening_to ? substr((string) $workingDay->evening_to, 0, 5) : '' }}"
                                            {{ $workingDay->is_working ? '' : 'disabled' }}>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-center mt-2">
                    <button type="submit" class="btn btn-primary submit_button">{{ __('admin.saving_changes') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('admin/app-assets/vendors/js/extensions/toastr.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            $(document).on('change', '.js-working-toggle', function () {
                const row = $(this).closest('tr');
                const enabled = $(this).is(':checked');
                row.find('.js-time-input').prop('disabled', !enabled);
            });

            $(document).on('submit', '#working-days-form', function (e) {
                e.preventDefault();

                const $form = $(this);
                const url = $form.attr('action');
                const submitButton = $form.find('.submit_button');
                const formData = new FormData(this);

                $.ajax({
                    url: url,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function () {
                        submitButton
                            .html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>')
                            .attr('disabled', true);
                    },
                    success: function (response) {
                        submitButton.html('{{ __('admin.saving_changes') }}').attr('disabled', false);
                        toastr.success(response.message || '{{ __('admin.update_successfullay') }}');
                    },
                    error: function (xhr) {
                        submitButton.html('{{ __('admin.saving_changes') }}').attr('disabled', false);

                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            $.each(xhr.responseJSON.errors, function (key, value) {
                                toastr.error(Array.isArray(value) ? value[0] : value);
                            });
                            return;
                        }

                        toastr.error('{{ __('admin.error') }}');
                    }
                });
            });
        });
    </script>
@endsection
