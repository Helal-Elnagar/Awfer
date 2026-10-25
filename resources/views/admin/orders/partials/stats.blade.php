<!-- Quick Stats -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-info text-white py-3">
        <h5 class="card-title mb-0 text-white">
            <i class="feather icon-bar-chart-2 mr-2 text-white"></i>
            {{ __('admin.quick_stats') }}
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="row no-gutters">
            <!-- Total Amount -->
            <div class="col-md-3 border-right">
                <div class="p-4 text-center">
                    <div class="stat-icon mb-3">
                        <i class="feather icon-dollar-sign text-success" style="font-size: 2.5rem;"></i>
                    </div>
                    <h5 class="mb-2 font-weight-bold text-success">
                        {{ number_format($order->total ?? $order->final_total, 2) }}
                    </h5>
                    <small class="text-muted font-weight-medium">{{ __('admin.total_amount') }}</small>
                </div>
            </div>

            <!-- Payment Status -->
            <div class="col-md-3 border-right">
                <div class="p-4 text-center">
                    <div class="stat-icon mb-3">
                        @php
                            $paymentStatusColors = [
                                'pending' => 'warning',
                                'paid' => 'success',
                                'failed' => 'danger',
                                'pending_verification' => 'info',
                                'refunded' => 'secondary',
                            ];
                            $paymentColor = $paymentStatusColors[$order->payment_status] ?? 'secondary';

                            $paymentIcons = [
                                'pending' => 'clock',
                                'paid' => 'check-circle',
                                'failed' => 'x-circle',
                                'pending_verification' => 'help-circle',
                                'refunded' => 'rotate-ccw',
                            ];
                            $paymentIcon = $paymentIcons[$order->payment_status] ?? 'circle';
                        @endphp
                        <i class="feather icon-{{ $paymentIcon }} text-{{ $paymentColor }}"
                            style="font-size: 2.5rem;"></i>
                    </div>
                    <h6 class="mb-2 font-weight-bold text-{{ $paymentColor }}">
                        {{ __('admin.' . $order->payment_status) }}
                    </h6>
                    <small class="text-muted font-weight-medium">{{ __('admin.payment_status') }}</small>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="col-md-3 border-right">
                <div class="p-4 text-center">
                    <div class="stat-icon mb-3">
                        @if ($order->payment_method === 'wallet')
                            <i class="feather icon-credit-card text-info" style="font-size: 2.5rem;"></i>
                        @elseif($order->payment_method === 'bank_transfer')
                            <i class="feather icon-send text-primary" style="font-size: 2.5rem;"></i>
                        @else
                            <i class="feather icon-credit-card text-secondary" style="font-size: 2.5rem;"></i>
                        @endif
                    </div>
                    <h6 class="mb-2 font-weight-bold">
                        {{ $paymentMethod ? $paymentMethod->name : 'Unknown' }}
                    </h6>
                    <small class="text-muted font-weight-medium">{{ __('admin.payment_method') }}</small>
                </div>
            </div>

            <!-- Total Items -->
            <div class="col-md-3">
                <div class="p-4 text-center">
                    <div class="stat-icon mb-3">
                        <i class="feather icon-package text-info" style="font-size: 2.5rem;"></i>
                    </div>
                    <h6 class="mb-2 font-weight-bold">
                        {{ $order->items ? $order->items->count() : 0 }}
                    </h6>
                    <small class="text-muted font-weight-medium">{{ __('admin.total_items') }}</small>
                </div>
            </div>
        </div>
    </div>

    @if ($order->payment_method_id === 5 && $order->bankTransfer && $order->current_status == 'pending_payment')
        <div class="card-footer bg-light border-top">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <i class="feather icon-alert-triangle text-warning mr-2"></i>
                        <span class="text-warning font-weight-bold">
                            {{ __('admin.bank_transfer_pending_verification') }}
                        </span>
                    </div>
                    <small class="text-muted">
                        {{ __('admin.please_verify_transfer_to_process_order') }}
                    </small>
                </div>
                <div class="col-md-4 text-right">
                    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#verifyTransferModal">
                        <i class="feather icon-eye mr-1"></i>
                        {{ __('admin.view_transfer_details') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Address & Delivery Time Info -->
    <div class="card-footer bg-light border-top">
        <div class="row">
            <div class="col-md-6 border-right">
                <div class="d-flex align-items-start">
                    <i class="feather icon-map-pin text-primary mt-1 mr-2" style="font-size: 1.5rem;"></i>
                    <div>
                        <h6 class="mb-1 font-weight-bold text-primary">{{ __('admin.customer_address') }}</h6>
                        @if ($order->address)
                            <div class="text-dark small">
                                <div><strong>{{ __('admin.address_name') }}:</strong>
                                    {{ $order->address->address_name ?? '-' }}</div>
                                <div><strong>{{ __('admin.neighborhood') }}:</strong>
                                    {{ $order->address->district->name ?? ($order->address->district_name ?? '-') }}
                                </div>
                                @if($order->address->building_number || $order->address->floor_number)
                                <div>
                                    @if($order->address->building_number)
                                    <strong>{{ __('admin.building_number') }}:</strong>
                                    {{ $order->address->building_number }}
                                    @endif
                                    @if($order->address->floor_number)
                                    | <strong>{{ __('admin.floor_number') }}:</strong>
                                    {{ $order->address->floor_number }}
                                    @endif
                                </div>
                                @endif
                                <div><strong>{{ __('admin.address') }}:</strong>
                                    {{ $order->address->city->name ?? '' }},
                                    {{ $order->address->district->name ?? '' }}, {{ $order->address->address }}</div>
                                <div class="mt-1">
                                    <a href="https://maps.google.com/?q={{ $order->address->latitude }},{{ $order->address->longitude }}"
                                        target="_blank" class="btn btn-xs btn-outline-primary">
                                        <i class="feather icon-map-pin"></i> {{ __('admin.view_on_map') }}
                                    </a>
                                </div>
                            </div>
                        @elseif($order->reciver_name)
                            <div class="text-dark small">
                                <div><strong>{{ __('admin.recipient_name') }}:</strong> {{ $order->reciver_name }}
                                </div>
                                <div><strong>{{ __('admin.phone') }}:</strong> {{ $order->reciver_phone }}</div>
                                <div><strong>{{ __('admin.gift_address') }}:</strong>
                                    {{ $order->giftCity->name ?? '' }}, {{ $order->giftDistrict->name ?? '' }},
                                    {{ $order->gift_address_name }}</div>
                                @if ($order->gift_latitude && $order->gift_longitude)
                                    <div class="mt-1">
                                        <a href="https://maps.google.com/?q={{ $order->gift_latitude }},{{ $order->gift_longitude }}"
                                            target="_blank" class="btn btn-xs btn-outline-primary">
                                            <i class="feather icon-map-pin"></i> {{ __('admin.view_on_map') }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex align-items-start">
                    <i class="feather icon-clock text-warning mt-1 mr-2" style="font-size: 1.5rem;"></i>
                    <div>
                        <h6 class="mb-1 font-weight-bold text-warning">{{ __('admin.delivery_time') }}</h6>
                        <div class="text-dark">
                            @if ($order->schedule_date || $order->schedule_time)
                                <div>
                                    <span class="badge badge-warning">
                                        <i class="feather icon-calendar mr-1"></i> {{ $order->schedule_date ?? '-' }}
                                    </span>
                                    <span class="badge badge-warning ml-2">
                                        <i class="feather icon-clock mr-1"></i> {{ $order->schedule_time ?? '-' }}
                                    </span>
                                </div>
                            @elseif ($order->deliveryPeriod)
                                <span class="badge badge-warning">{{ $order->deliveryPeriod->description }}</span>
                                @if ($order->time)
                                    <span class="ml-2">({{ $order->time }})</span>
                                @endif
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
