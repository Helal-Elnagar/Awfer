@if ($maxReached)
    <div class="alert alert-warning" id="max-delivery-periods-warning">
        {{ __('admin.you_cannot_add_more_delivery_periods', ['count' => config('awt.delivery_periods_max_count', 6)]) }}
    </div>
@endif

<script>
    if ({{ $maxReached ? 'true' : 'false' }}) {
        $('.table_buttons a[href*="delivery-periods/create"]').hide();
        if ($('#max-delivery-periods-warning').length === 0) {
            $('.table_content_append').before(
                '<div class="alert alert-warning" id="max-delivery-periods-warning">{{ __("admin.you_cannot_add_more_delivery_periods", ["count" => config("awt.delivery_periods_max_count", 6)]) }}</div>'
            );
        }
    } else {
        $('.table_buttons a[href*="delivery-periods/create"]').show();
        $('#max-delivery-periods-warning').remove();
    }
</script>

<div class="position-relative">
    <table class="table" id="tab">
        <thead>
            <tr>
                <th>
                    <label class="container-checkbox">
                        <input type="checkbox" id="checkedAll">
                        <span class="checkmark"></span>
                    </label>
                </th>
                <th>{{ __('admin.number') }}</th>
                <th>{{ __('admin.name') }}</th>
                <th>{{ __('admin.description') }}</th>
                <th>{{ __('admin.control') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($periods as $period)
                <tr class="delete_row">
                    <td class="text-center">
                        <label class="container-checkbox">
                            <input type="checkbox" class="checkSingle" id="{{ $period->id }}">
                            <span class="checkmark"></span>
                        </label>
                    </td>
                    <td>{{ $period->id }}</td>
                    <td>{{ $period->name }}</td>
                    <td>{{ $period->description ?? '-' }}</td>
                    <td class="product-action">
                        <span class="action-edit text-primary">
                            <a href="{{ route('admin.delivery_periods.edit', $period->id) }}" class="btn btn-primary btn-sm p-1" title="{{ __('admin.edit') }}">
                                <i class="feather icon-edit"></i>
                            </a>
                        </span>
                        <span class="delete-row btn btn-danger btn-sm p-1"
                            data-url="{{ url('admin/delivery-periods/' . $period->id) }}"
                            title="{{ __('admin.delete') }}">
                            <i class="feather icon-trash"></i>
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($periods->count() == 0)
        <div class="d-flex flex-column w-100 align-center mt-4">
            <img src="{{ asset('admin/app-assets/images/pages/404.png') }}" alt="">
            <span class="mt-2" style="font-family: cairo">{{ __('admin.there_are_no_matches_matching') }}</span>
        </div>
    @endif
</div>

@if ($periods->count() > 0 && $periods instanceof \Illuminate\Pagination\AbstractPaginator)
    <div class="d-flex justify-content-center mt-3">
        {{ $periods->links() }}
    </div>
@endif
