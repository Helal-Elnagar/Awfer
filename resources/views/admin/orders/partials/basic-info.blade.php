<!-- Basic Order Information & Customer Information -->
<div class="row">
    <!-- Basic Order Information -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-info text-white py-3">
                <h5 class="card-title mb-0 text-white">
                    <i class="feather icon-info mr-2 text-white"></i>
                    {{ __('admin.basic_information') }}
                </h5>
            </div>
            <div class="card-body p-3">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th width="40%">{{ __('admin.order_number') }}:</th>
                        <td><span class="badge badge-info">{{ $order->order_number ?? $order->order_num }}</span></td>
                    </tr>
                    <tr>
                        <th>{{ __('admin.status') }}:</th>
                        <td>
                            @php
                                $statusColor = \App\Enums\OrderStatus::tryFrom($order->status)?->color() ?? 'secondary';
                            @endphp
                            <span class="badge badge-{{ $statusColor }}">
                                {{ \App\Enums\OrderStatus::tryFrom($order->status)?->label() ?? $order->status }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>{{ __('admin.order_date') }}:</th>
                        <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    @if ($order->time)
                        <tr>
                            <th>{{ __('admin.order_time') }}:</th>
                            <td>{{ $order->time }}</td>
                        </tr>
                    @endif
                    @if ($order->deliveryPeriod)
                        <tr>
                            <th>{{ __('admin.delivery_period') }}:</th>
                            <td>{{ $order->deliveryPeriod->name }}</td>
                        </tr>
                    @endif
                    @if ($paymentMethod)
                        <tr>
                            <th>{{ __('admin.payment_method') }}:</th>
                            <td>{{ $paymentMethod->name }}</td>
                        </tr>
                    @endif
                    <tr>
                        <th>{{ __('admin.payment_status') }}:</th>
                        <td>
                            @php
                                $psColors = ['pending' => 'warning', 'success' => 'success', 'paid' => 'success', 'failed' => 'danger', 'pending_verification' => 'info', 'refunded' => 'secondary'];
                                $psColor = $psColors[$order->payment_status] ?? 'secondary';
                            @endphp
                            <span class="badge badge-{{ $psColor }}">{{ $order->payment_status }}</span>
                        </td>
                    </tr>
                    @if ($order->payment_date)
                        <tr>
                            <th>{{ __('admin.payment_date') }}:</th>
                            <td>{{ $order->payment_date->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endif
                    @if ($order->invoice_number)
                        <tr>
                            <th>{{ __('admin.invoice_number') }}:</th>
                            <td>{{ $order->invoice_number }}</td>
                        </tr>
                    @endif
                    {{-- <tr>
                        <th>{{ __('admin.booking_type') }}:</th>
                        <td>
                            @if ($order->booking_type)
                                <span class="badge badge-secondary">{{ __('admin.' . $order->booking_type) }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr> --}}
                    {{-- <tr>
                        <th>{{ __('admin.city') }}:</th>
                        <td>
                            @if ($order->user->city)
                                <span class="badge badge-secondary">{{  $order->user->city->name }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr> --}}
                    {{-- <tr>
                        <th>{{ __('admin.delivery_type') }}:</th>
                        <td>
                            @if ($order->delivery_type)
                                <span class="badge badge-secondary">{{ __('admin.' . $order->delivery_type) }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr> --}}
                    @if ($order->scheduled_at)
                        <tr>
                            <th>{{ __('admin.scheduled_at') }}:</th>
                            <td>{{ $order->scheduled_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endif

                    @if ($order->schedule_date)
                        <tr>
                            <th>{{ __('admin.delivery_date') }}:</th>
                            <td>{{ $order->schedule_date }}</td>
                        </tr>
                    @endif

                    @if ($order->schedule_time)
                        <tr>
                            <th>{{ __('admin.delivery_time') }}:</th>
                            <td>{{ $order->schedule_time }}</td>
                        </tr>
                    @endif
                    @if ($order->deliveryUser)
                        <tr>
                            <th>{{ __('admin.delivery_driver') }}:</th>
                            <td>{{ $order->deliveryUser->name }}</td>
                        </tr>
                    @endif
                    @if ($order->branch)
                        <tr>
                            <th>{{ __('admin.branch') }}:</th>
                            <td>{{ $order->branch->name ?? __('admin.not_available') }}</td>
                        </tr>
                    @endif
                    @if ($order->coupon)
                        <tr>
                            <th>{{ __('admin.coupon') }}:</th>
                            <td>{{ $order->coupon->coupon_name ?? $order->coupon->name ?? $order->coupon->coupon_num }}</td>
                        </tr>
                    @elseif (!empty($order->discount_code))
                        <tr>
                            <th>{{ __('admin.coupon') }}:</th>
                            <td>{{ $order->discount_code }}</td>
                        </tr>
                    @endif
                    @if ($order->current_status === 'cancelled' && $order->cancelReason)
                        <tr>
                            <th>{{ __('admin.cancel_reason') }}:</th>
                            <td>
                                @php
                                    $reasonData = json_decode($order->cancelReason->reason, true);
                                    $reasonText = $reasonData[app()->getLocale()] ?? ($reasonData['en'] ?? 'Unknown');
                                @endphp
                                <div class="alert alert-danger py-2 px-3 mb-0">
                                    <i class="feather icon-x-circle mr-2"></i>
                                    <span class="font-italic">"{{ $reasonText }}"</span>
                                </div>
                            </td>
                        </tr>
                    @endif
                    @if ($order->address)
                        <tr>
                            <th>{{ __('admin.address_name') }}:</th>
                            <td>{{ $order->address->address_name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('admin.neighborhood') }}:</th>
                            <td>{{ $order->address->district->name ?? ($order->address->district_name ?? '-') }}</td>
                        </tr>
                        @if($order->address->building_number)
                        <tr>
                            <th>{{ __('admin.building_number') }}:</th>
                            <td>{{ $order->address->building_number }}</td>
                        </tr>
                        @endif
                        @if($order->address->floor_number)
                        <tr>
                            <th>{{ __('admin.floor_number') }}:</th>
                            <td>{{ $order->address->floor_number }}</td>
                        </tr>
                        @endif
                        <tr>
                            <th>{{ __('admin.description') }}:</th>
                            <td>{{ $order->address->description ?? $order->address->note ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('admin.address') }}:</th>
                            <td>
                                {{ $order->address->city->name ?? '' }},
                                {{ $order->address->district->name ?? '' }},
                                {{ $order->address->address }}
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('admin.location') }}:</th>
                            <td>
                                <a href="https://maps.google.com/?q={{ $order->address->latitude }},{{ $order->address->longitude }}"
                                    target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="feather icon-map-pin"></i> {{ __('admin.view_on_map') }}
                                </a>
                            </td>
                        </tr>
                    @elseif($order->reciver_name)
                        <tr>
                            <th>{{ __('admin.gift_address') }}:</th>
                            <td>
                                {{ $order->giftCity->name ?? '' }},
                                {{ $order->giftDistrict->name ?? '' }},
                                {{ $order->gift_address_name }}
                            </td>
                        </tr>
                    @endif
                    @if ($order->delivery_date)
                        <tr>
                            <th>{{ __('admin.delivery_date') }}:</th>
                            <td>{{ $order->delivery_date }}</td>
                        </tr>
                    @endif
                    @if (!empty($order->notes))
                        <tr>
                            <th>{{ __('admin.notes') }}:</th>
                            <td>{{ $order->notes }}</td>
                        </tr>
                    @endif

                    @if ($order->deliveryUser && $order->deliveryUser->rates->count() > 0)
                        <tr>
                            <th>{{ __('admin.delivery_rating') }}:</th>
                            <td>
                                @php
                                    $avgRate = $order->deliveryUser->rates->avg('rate');
                                @endphp
                                <div class="text-warning">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="feather icon-star {{ $i <= $avgRate ? 'fill-current' : '' }}"></i>
                                    @endfor
                                    <span class="text-muted ml-1">({{ number_format($avgRate, 1) }})</span>
                                </div>
                            </td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <!-- Customer Information -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-info text-white py-3">
                <h5 class="card-title mb-0 text-white">
                    <i class="feather icon-user mr-2 text-white"></i>
                    {{ __('admin.customer_information') }}
                </h5>
            </div>
            <div class="card-body p-3">
                @if ($order->user)
                    <table class="table table-borderless mb-0">
                        <tr>
                            <th width="40%">{{ __('admin.name') }}:</th>
                            <td>{{ $order->user->name }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('admin.phone') }}:</th>
                            <td>{{ $order->user->phone }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('admin.email') }}:</th>
                            <td>{{ $order->user->email ?? '-' }}</td>
                        </tr>
                        {{-- @if ($order->user->wallet_balance)
                    <tr>
                        <th>{{ __('admin.wallet_balance') }}:</th>
                        <td>{{ number_format($order->user->wallet_balance, 2) }} {{ __('admin.sar') }}</td>
                    </tr>
                    @endif --}}
                        @if ($order->user->lat && $order->user->lng)
                            <tr>
                                <th>{{ __('admin.user_location') }}:</th>
                                <td>
                                    <a href="https://maps.google.com/?q={{ $order->user->lat }},{{ $order->user->lng }}"
                                        target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="feather icon-map-pin"></i> {{ __('admin.view_on_map') }}
                                    </a>
                                </td>
                            </tr>
                        @endif
                    </table>
                @else
                    <p class="text-muted">{{ __('admin.no_customer_info') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
