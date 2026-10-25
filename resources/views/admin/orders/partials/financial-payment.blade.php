<!-- Financial Breakdown -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-info text-white py-3">
                <h5 class="card-title mb-0 text-white">
                    <i class="feather icon-dollar-sign mr-2 text-white"></i>
                    {{ __('admin.financial_breakdown') }}
                </h5>
            </div>
            <div class="card-body p-3">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th width="50%">{{ __('admin.subtotal') }}:</th>
                        <td class="text-right">{{ number_format($order->subtotal ?? 0, 2) }} {{ __('admin.sar') }}</td>
                    </tr>
                  
                  
        
                  <tr>

<th>{{ __('admin.delivery_fee') }}:</th>

<td class="text-right">

@if(isset($settings['delivery_fee']))

{{ number_format((float) $settings['delivery_fee'], 2) }} {{ __('admin.sar') }}

@else

{{ number_format((float) ($order->delivery_fee ?? 0), 2) }} {{ __('admin.sar') }}

@endif

</td>

</tr>
                    <tr class="text-success">
                        <th>{{ __('admin.discount') }}:</th>
                        <td class="text-right">-{{ number_format($order->discount_amount ?? $order->coupon_amount ?? 0, 2) }} {{ __('admin.sar') }}</td>
                    </tr>
                    <tr class="text-success">
                        <th>{{ __('admin.coupon') }}:</th>
                        <td class="text-right">
                            @if($order->coupon)
                                {{-- Prefer readable coupon name, then number/code --}}
                                {{ $order->coupon->coupon_name ?? $order->coupon->name ?? $order->coupon->coupon_num ?? ('#' . $order->coupon->id) }}
                            @elseif(!empty($order->discount_code ?? $order->coupon_code))
                                {{ $order->discount_code ?? $order->coupon_code }}
                            @else
                                --
                            @endif
                        </td>

                    </tr>
                      <tr class="text-success">
                        <th>{{ __('admin.vat_amount') }}:</th>
                        <td class="text-right">{{ number_format($order->vat_amount ?? 0, 2) }} {{ __('admin.sar') }}</td>
                    </tr>
                    {{-- <tr class="text-success">
                        <th>{{ __('admin.loyalty_points_earned') }}:</th>
                        <td class="text-right">{{ number_format($order->loyalty_points_earned, 2) }} {{ __('admin.sar') }}</td>
                    </tr>
                    <tr class="text-success">
                        <th>{{ __('admin.loyalty_points_used') }}:</th>
                        <td class="text-right">-{{ number_format($order->loyalty_deduction, 2) }} {{ __('admin.sar') }}</td>
                    </tr> --}}
                     <tr class="text-success">
                        <th>{{ __('admin.wallet_deduction') }}:</th>
                        <td class="text-right">-{{ number_format($order->wallet_deduction, 2) }} {{ __('admin.sar') }}</td>
                    </tr>
                    <tr class="border-top">
                        <th><strong>{{ __('admin.total') }}:</strong></th>
                        <td class="text-right"><strong>{{ number_format($order->total ?? $order->final_total, 2) }} {{ __('admin.sar') }}</strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

            <div class="col-md-6 mb-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-info text-white py-3">
            <h5 class="card-title mb-0 text-white">
                <i class="feather icon-map-pin mr-2 text-white"></i>
                {{ __('admin.address_information') }}
            </h5>
        </div>
        <div class="card-body p-3">
            <table class="table table-borderless mb-0">
                <tr>
                    <th>{{ __('admin.address_name') }}:</th>
                    <td>{{ $order->address?->address_name ?? $order->gift_address_name ?? '-' }}</td>
                </tr>
                <tr>
                    <th>{{ __('admin.recipient_name') }}:</th>
                    <td>{{ $order->user?->name ?? '-' }}</td>
                </tr>
                <tr>
                    <th>{{ __('admin.primary_phone') ?? __('admin.phone') }}:</th>
                    <td>{{ $order->user?->phone ?? '-' }}</td>
                </tr>
                <tr>
                    <th>{{ __('admin.alternative_phone') ?? __('admin.phone') }}:</th>
                    <td>{{ $order->address?->phone ?? '-' }}</td>
                </tr>
                <tr>
                    <th>{{ __('admin.district') }}:</th>
                    <td>{{ $order->address?->district?->name ?? '-' }}</td>
                </tr>
                <tr>
                    <th>{{ __('admin.city') }}:</th>
                    <td>{{ $order->address?->city?->name ?? $order->city?->name ?? '-' }}</td>
                </tr>
                <tr>
                    <th>{{ __('admin.description') }}:</th>
                    <td>{{ $order->address?->description ?? $order->message ?? '-' }}</td>
                </tr>
                <tr>
                    <th>{{ __('admin.is_default') }}:</th>
                    <td>
                        @if($order->address?->is_default)
                            <span class="badge badge-success">{{ __('admin.default_address') }}</span>
                        @else
                            <span class="badge badge-secondary">{{ __('admin.not_default') }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>{{ __('admin.created_at') }}:</th>
                    <td>{{ $order->address?->created_at ?? '-' }}</td>
                </tr>
                @php
                    $lat = $order->address?->latitude ?? $order->gift_latitude ?? $order->address_latitude ?? null;
                    $lng = $order->address?->longitude ?? $order->gift_longitude ?? $order->address_longitude ?? null;
                @endphp
                @if($lat && $lng)
                <tr>
                    <th>{{ __('admin.location') }}:</th>
                    <td>
                        <a href="https://maps.google.com/?q={{ $lat }},{{ $lng }}"
                           target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="feather icon-map-pin"></i> {{ __('admin.view_on_map') }}
                        </a>
                    </td>
                </tr>
                @endif
            </table>
        </div>
    </div>


             </div>
    

</div>
