<!-- Order Header -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-primary text-white py-4">
        <div class="d-flex flex-wrap justify-content-between w-100">

            <!-- Order Info -->
            <div>
                <h4 class="card-title mb-2 text-white">
                    <i class="feather icon-file-text mr-2"></i>
                    {{ __('admin.order_details') }}
                    <span class="text-warning font-weight-bold">{{ $order->order_number }}</span>
                </h4>

                <small class="text-white-75 d-block mb-3">
                    {{ __('admin.created_at') }}: {{ $order->created_at->format('d/m/y h:i') }}
                </small>

                <a href="{{ route('admin.reports.payment-report.download-invoice', $order->id) }}"
                   class="btn btn-light btn-sm">
                    <i class="feather icon-download mr-1"></i>
                    {{ __('admin.download_invoice') }}
                </a>
            </div>

            <!-- Status Dropdown -->
            <div class="text-left mt-2 mt-md-0">

                @php
                    $statusColors = [
                        'pending' => 'warning',
                        'new' => 'secondary',
                        'preparing' => 'info',
                        'processing' => 'info',
                        'out-for-delivery' => 'primary',
                        'confirmed' => 'primary',
                        'delivered' => 'success',
                        'problem' => 'danger',
                        'cancelled' => 'danger',
                        'request_rejected' => 'danger',
                    ];

                    $statusIcons = [
                        'pending' => 'clock',
                        'new' => 'user-plus',
                        'preparing' => 'loader',
                        'processing' => 'refresh-cw',
                        'out-for-delivery' => 'truck',
                        'confirmed' => 'check',
                        'delivered' => 'check-circle-2',
                        'problem' => 'alert-triangle',
                        'cancelled' => 'x-circle',
                        'request_rejected' => 'slash',
                    ];

                    $color = $statusColors[$order->status] ?? 'secondary';
                    $icon  = $statusIcons[$order->status] ?? 'circle';
                @endphp

                <div class="d-flex align-items-center">

                    <!-- Change Status Dropdown -->
                    <div class="dropdown mr-2">
                        <button class="btn btn-outline-light dropdown-toggle"
                                id="statusdropdown"
                                data-toggle="dropdown">
                            <i class="feather icon-{{ $icon }} mr-1"></i>
                            {{ __('admin.' . $order->status) }}
                        </button>

                        <div class="dropdown-menu">

                            @php
                                $availableStatuses = [
                                    'pending' => __('admin.pending'),
                                    'new' => __('admin.new'),
                                    'preparing' => __('admin.preparing'),
                                    'processing' => __('admin.processing'),
                                    'out-for-delivery' => __('admin.out-for-delivery'),
                                    'confirmed' => __('admin.confirmed'),
                                    'delivered' => __('admin.delivered'),
                                    'problem' => __('admin.problem'),
                                    'cancelled' => __('admin.cancelled'),
                                ];

                                $admin = auth('admin')->user();

                                if ($admin && $admin->role_id == 2) {
                                    $availableStatuses = [
                                        'new' => __('admin.new'),
                                        'processing' => __('admin.processing'),
                                        'out-for-delivery' => __('admin.out-for-delivery'),
                                        'confirmed' => __('admin.confirmed'),
                                        'delivered' => __('admin.delivered'),
                                        'cancelled' => __('admin.cancelled'),
                                    ];
                                }
                            @endphp

                            @foreach($availableStatuses as $status => $label)
                                @if($status !== $order->status)
                                    <a class="dropdown-item change-order-status"
                                       href="#"
                                       data-order-id="{{ $order->id }}"
                                       data-status="{{ $status }}">
                                        <i class="feather icon-{{ $statusIcons[$status] ?? 'circle' }} mr-2"></i>
                                        {{ $label }}
                                    </a>
                                @endif
                            @endforeach

                        </div>
                    </div>

                    <!-- Current Status Badge -->
                    <span class="badge badge-{{ $color }} px-3 py-2">
                        <i class="feather icon-{{ $icon }} mr-1"></i>
                        {{ __('admin.' . $order->status) }}
                    </span>

                </div>

                @if($order->status === 'cancelled' && $order->cancelreason)
                    <div class="mt-2">
                        <small class="text-white-75">{{ __('admin.cancel_reason') }}:</small>
                        <small class="text-white-50 font-italic d-block">
                            "{{ $order->cancelreason->reason }}"
                        </small>
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>


{{-- ========================= JS ========================= --}}
@section('js')
<script>
$(function () {

    // Handle Status Change
    $(document).on('click', '.change-order-status', function () {
        const status = $(this).data('status');
        const orderId = $(this).data('order-id');

        if (status === 'new') {
            if ('{{ $order->delivery_id }}') {
                updateStatus(orderId, status);
            } else {
                $('#assignDeliveryModal').modal('show')
                                         .data('order-id', orderId);
            }
            return;
        }

        updateStatus(orderId, status);
    });

    // Update status via AJAX
    function updateStatus(orderId, status) {
        $.post(`/admin/orders/${orderId}/change-status`, {
            status,
            _token: '{{ csrf_token() }}'
        })
        .done(() => location.reload())
        .fail(() => alert('حدث خطأ أثناء تغيير الحالة'));
    }

    // Assign delivery + change status to "new"
    $('#assignDeliveryBtn').on('click', function () {
        const orderId = $('#assignDeliveryModal').data('order-id');
        const deliveryUserId = $('#delivery_user_id').val();

        if (!deliveryUserId) {
            alert('يرجى اختيار مندوب التوصيل');
            return;
        }

        $.post(`/admin/orders/${orderId}/assign-delivery`, {
            delivery_user_id: deliveryUserId,
            status: 'new',
            _token: '{{ csrf_token() }}'
        })
        .done(() => location.reload())
        .fail(() => alert('حدث خطأ أثناء تعيين مندوب التوصيل'));
    });

});
</script>


<!-- Assign Delivery Modal -->
<div class="modal fade" id="assignDeliveryModal">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">تعيين مندوب التوصيل</h5>
                <button class="close" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body">
                <label>اختر مندوب التوصيل:</label>
                <select id="delivery_user_id" class="form-control">
                    <option value="">-- اختر مندوب --</option>

                    @forelse($deliveryUsers as $user)
                        <option value="{{ $user->id }}">
                            {{ $user->name }}
                        </option>
                    @empty
                        <option disabled>لا يوجد مندوبين متاحين</option>
                    @endforelse
                </select>
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary" id="assignDeliveryBtn">تعيين وتغيير الحالة</button>
                <button class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
            </div>

        </div>
    </div>
</div>
@endsection
