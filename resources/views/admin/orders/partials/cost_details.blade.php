@php
    $cost = $order->cost_details_arabic ?? [];
    $subtotal = (float) ($cost['subtotal'] ?? 0);
    $productsTotal = (float) ($cost['products_total_without_vat'] ?? 0);
    $couponAmount = (float) ($cost['coupon_amount'] ?? 0);
    $couponCode = $cost['coupon_code'] ?? null;
    $couponType = $cost['coupon_type'] ?? null;
    $couponValue = $cost['coupon_value'] ?? null;
    $discount = (float) ($cost['discount'] ?? 0);
    $loyaltyPoints = (int) ($cost['loyalty_points_used'] ?? 0);
    $loyaltyValue = (float) ($cost['loyalty_points_value'] ?? 0);
    $walletDeduction = (float) ($cost['wallet_deduction'] ?? 0);
    $deliveryFee = (float) ($cost['delivery_fee'] ?? 0);
    $vatAmount = (float) ($cost['vat_amount'] ?? 0);
    $vatPercent = (float) ($cost['vat_percent'] ?? 0);
    $vatDisplay = $vatPercent > 1 ? $vatPercent : round($vatPercent * 100, 2);
    $total = (float) ($cost['total'] ?? 0);
    $earnedPoints = (int) ($cost['earned_points'] ?? 0);

    // الخصم المباشر = discount_amount - coupon - loyalty - wallet
    $productDiscount = max(0, round($discount - $couponAmount - $loyaltyValue - $walletDeduction, 2));
@endphp

<div class="card mb-3 shadow-sm">
    {{-- Header --}}
    <div class="card-header d-flex align-items-center justify-content-between py-2"
        style="background:linear-gradient(135deg,#f8f9fa,#e9ecef); border-bottom:2px solid #dee2e6;">
        <h5 class="card-title mb-0 font-weight-bold">
            <i class="feather icon-file-text text-primary mr-2"></i>
            {{ __('admin.cost_details') }}
        </h5>
        <span class="badge badge-primary px-3 py-2" style="font-size:.95rem;">
            {{ number_format($total, 2) }} {{ __('admin.sar') }}
        </span>
    </div>

    <div class="card-body p-0">
        <table class="table table-hover mb-0" style="font-size:.93rem;">
            <tbody>

                {{-- ══ سعر المنتجات الأساسي ══ --}}
                <tr>
                    <td class="pl-3" style="width:65%;">
                        <div class="d-flex align-items-center">
                            <i class="feather icon-shopping-bag text-primary mr-2" style="font-size:1.1rem;"></i>
                            <div>
                                <span class="font-weight-bold">{{ __('admin.products_total_without_vat') }}</span>
                                <small class="text-muted d-block">
                                    سعر المنتجات بعد خصم المنتج وقبل الضريبة
                                    @if ($cost['items_count'] ?? 0)
                                        · <span class="badge badge-light border">{{ $cost['items_count'] }} قطعة</span>
                                    @endif
                                </small>
                            </div>
                        </div>
                    </td>
                    <td class="text-right pr-3" style="font-size:1.05rem;">
                        <strong>{{ number_format($productsTotal, 2) }}</strong>
                        <small class="text-muted"> {{ __('admin.sar') }}</small>
                    </td>
                </tr>

                {{-- ══ خصم سعر المنتج ══ --}}
                @if ($productDiscount > 0)
                    <tr style="background:#fffbeb;">
                        <td class="pl-3">
                            <div class="d-flex align-items-center">
                                <i class="feather icon-tag text-orange mr-2"
                                    style="font-size:1.1rem; color:#f59e0b;"></i>
                                <div>
                                    <span class="font-weight-bold" style="color:#b45309;">خصم على الطلب</span>
                                    <small class="text-muted d-block">
                                        ✂️ إجمالي الخصومات المطبّقة على الطلب
                                        @if ($couponCode)
                                            (شامل كوبون: <strong>{{ $couponCode }}</strong>)
                                        @endif
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td class="text-right pr-3" style="font-size:1.05rem; color:#b45309;">
                            <strong>- {{ number_format($productDiscount, 2) }}</strong>
                            <small class="text-muted"> {{ __('admin.sar') }}</small>
                        </td>
                    </tr>
                @endif

                {{-- ══ خصم الكوبون ══ --}}
                @if ($couponAmount > 0)
                    <tr style="background:#fffbeb;">
                        <td class="pl-3">
                            <div class="d-flex align-items-center">
                                <i class="feather icon-scissors mr-2" style="font-size:1.1rem; color:#f59e0b;"></i>
                                <div>
                                    <span class="font-weight-bold"
                                        style="color:#b45309;">{{ __('admin.coupon_amount') }}</span>
                                    <small class="text-muted d-block">
                                        🎟 كوبون خصم أدخله العميل عند الطلب
                                        <br>
                                        @if ($couponCode)
                                            <span class="badge badge-warning">كود: {{ $couponCode }}</span>
                                        @endif
                                        @if ($couponType === 'ratio' && $couponValue)
                                            <span class="badge badge-light border mr-1">نسبة
                                                {{ $couponValue }}%</span>
                                        @elseif($couponType === 'fixed')
                                            <span class="badge badge-light border mr-1">مبلغ ثابت</span>
                                        @endif
                                        <span class="badge"
                                            style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d;">
                                            مصدره: كوبون خصم
                                        </span>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td class="text-right pr-3" style="font-size:1.05rem; color:#b45309;">
                            <strong>- {{ number_format($couponAmount, 2) }}</strong>
                            <small class="text-muted"> {{ __('admin.sar') }}</small>
                        </td>
                    </tr>
                @endif

                {{-- ══ نقاط الولاء ══ --}}
                @if ($loyaltyPoints > 0)
                    <tr style="background:#fffbeb;">
                        <td class="pl-3">
                            <div class="d-flex align-items-center">
                                <i class="feather icon-award mr-2" style="font-size:1.1rem; color:#f59e0b;"></i>
                                <div>
                                    <span class="font-weight-bold"
                                        style="color:#b45309;">{{ __('admin.loyalty_points_used') }}</span>
                                    <small class="text-muted d-block">
                                        ⭐ نقاط ولاء استخدمها العميل لتخفيض قيمة الطلب
                                        <br>
                                        <span class="badge badge-secondary">{{ $loyaltyPoints }} نقطة</span>
                                        <span class="badge"
                                            style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d;">
                                            مصدره: رصيد نقاط الولاء
                                        </span>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td class="text-right pr-3" style="font-size:1.05rem; color:#b45309;">
                            <strong>- {{ number_format($loyaltyValue, 2) }}</strong>
                            <small class="text-muted"> {{ __('admin.sar') }}</small>
                        </td>
                    </tr>
                @endif

                {{-- ══ خصم المحفظة ══ --}}
                @if ($walletDeduction > 0)
                    <tr style="background:#fffbeb;">
                        <td class="pl-3">
                            <div class="d-flex align-items-center">
                                <i class="feather icon-credit-card mr-2" style="font-size:1.1rem; color:#f59e0b;"></i>
                                <div>
                                    <span class="font-weight-bold"
                                        style="color:#b45309;">{{ __('admin.wallet_deduction') }}</span>
                                    <small class="text-muted d-block">
                                        👛 مبلغ خُصم من رصيد المحفظة الإلكترونية للعميل
                                        <br>
                                        <span class="badge"
                                            style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d;">
                                            مصدره: محفظة العميل
                                        </span>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td class="text-right pr-3" style="font-size:1.05rem; color:#b45309;">
                            <strong>- {{ number_format($walletDeduction, 2) }}</strong>
                            <small class="text-muted"> {{ __('admin.sar') }}</small>
                        </td>
                    </tr>
                @endif

                {{-- فاصل --}}
                <tr>
                    <td colspan="2" class="p-0">
                        <hr class="my-0">
                    </td>
                </tr>

                {{-- ══ رسوم التوصيل ══ --}}
                <tr>
                    <td class="pl-3">
                        <div class="d-flex align-items-center">
                            <i class="feather icon-truck text-info mr-2" style="font-size:1.1rem;"></i>
                            <div>
                                <span class="font-weight-bold">{{ __('admin.delivery_fee') }}</span>
                                <small class="text-muted d-block">رسوم توصيل الطلب للعميل</small>
                            </div>
                        </div>
                    </td>
                    <td class="text-right pr-3 text-info" style="font-size:1.05rem;">
                        <strong>+ {{ number_format($deliveryFee, 2) }}</strong>
                        <small class="text-muted"> {{ __('admin.sar') }}</small>
                    </td>
                </tr>

                {{-- ══ الضريبة ══ --}}
                <tr>
                    <td class="pl-3">
                        <div class="d-flex align-items-center">
                            <i class="feather icon-percent text-secondary mr-2" style="font-size:1.1rem;"></i>
                            <div>
                                <span class="font-weight-bold">{{ __('admin.vat_amount') }}</span>
                                <small class="text-muted d-block">
                                    ضريبة القيمة المضافة
                                    @if ($vatDisplay > 0)
                                        <span class="badge badge-light border">{{ $vatDisplay }}%</span>
                                    @endif
                                </small>
                            </div>
                        </div>
                    </td>
                    <td class="text-right pr-3 text-secondary" style="font-size:1.05rem;">
                        <strong>+ {{ number_format($vatAmount, 2) }}</strong>
                        <small class="text-muted"> {{ __('admin.sar') }}</small>
                    </td>
                </tr>

                {{-- فاصل سميك --}}
                <tr>
                    <td colspan="2" class="p-0">
                        <hr class="my-0" style="border-width:2px; border-color:#2d3748;">
                    </td>
                </tr>

                {{-- ══ الإجمالي النهائي ══ --}}
                <tr style="background:#2d3748;">
                    <td class="pl-3 font-weight-bold" style="font-size:1.05rem; color:#e2e8f0;">
                        <i class="feather icon-check-circle mr-2" style="color:#68d391;"></i>
                        {{ __('admin.final_total') }}
                    </td>
                    <td class="text-right pr-3 font-weight-bold" style="font-size:1.25rem; color:#68d391;">
                        {{ number_format($total, 2) }}
                        <small style="color:#a0aec0; font-size:.8rem;"> {{ __('admin.sar') }}</small>
                    </td>
                </tr>

                {{-- ══ النقاط المكتسبة ══ --}}
                <tr style="background:#f0fff4; border-top: 2px dashed #68d391;">
                    <td class="pl-3">
                        <div class="d-flex align-items-center">
                            <i class="feather icon-gift text-success mr-2" style="font-size:1.1rem;"></i>
                            <div>
                                <span class="font-weight-bold text-success">{{ __('admin.loyalty_points_earned') }}</span>
                                <small class="text-muted d-block">نقاط أُضيفت لرصيد العميل بعد إتمام الطلب</small>
                            </div>
                        </div>
                    </td>
                    <td class="text-right pr-3 font-weight-bold" style="font-size:1.1rem;">
                        @if($earnedPoints > 0)
                            <span class="text-success">+ {{ $earnedPoints }}</span>
                            <small class="text-muted"> نقطة</small>
                        @else
                            <span class="text-muted">—</span>
                            <small class="text-muted d-block" style="font-size:.75rem;">لم تُكتسب نقاط</small>
                        @endif
                    </td>
                </tr>

                {{-- ══ النقاط المستخدمة ══ --}}
                @if ($loyaltyPoints > 0)
                    <tr style="background:#fef3c7; border-top: 1px dashed #f59e0b;">
                        <td class="pl-3">
                            <div class="d-flex align-items-center">
                                <i class="feather icon-award text-warning mr-2" style="font-size:1.1rem;"></i>
                                <div>
                                    <span class="font-weight-bold text-warning">{{ __('admin.loyalty_points_used') }}</span>
                                    <small class="text-muted d-block">نقاط استُخدمت لتخفيض قيمة الطلب</small>
                                </div>
                            </div>
                        </td>
                        <td class="text-right pr-3 text-warning font-weight-bold" style="font-size:1.05rem;">
                            − {{ $loyaltyPoints }} <small class="text-muted">نقطة
                                ({{ number_format($loyaltyValue, 2) }} ر.س)</small>
                        </td>
                    </tr>
                @endif

            </tbody>
        </table>

        {{-- ══ ملخص الحساب ══ --}}
        <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between flex-wrap"
            style="background:#f7fafc; font-size:.85rem; color:#4a5568; gap:.3rem;">
            <span class="font-weight-bold"><i class="feather icon-info mr-1 text-primary"></i>ملخص الحساب:</span>
            <span class="d-flex align-items-center flex-wrap" style="gap:.2rem;">
                <span title="سعر المنتجات بدون ضريبة" class="badge badge-light border px-2 py-1">
                    {{ number_format($productsTotal, 2) }}
                </span>
                @if ($productDiscount > 0)
                    <span class="text-warning font-weight-bold">−</span>
                    <span title="خصم على الطلب" class="badge px-2 py-1"
                        style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d;">
                        {{ number_format($productDiscount, 2) }} <small>(خصم طلب)</small>
                    </span>
                @endif
                @if ($couponAmount > 0)
                    <span class="text-warning font-weight-bold">−</span>
                    <span title="خصم الكوبون" class="badge px-2 py-1"
                        style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d;">
                        {{ number_format($couponAmount, 2) }} <small>(كوبون)</small>
                    </span>
                @endif
                @if ($loyaltyValue > 0)
                    <span class="text-warning font-weight-bold">−</span>
                    <span title="نقاط الولاء" class="badge px-2 py-1"
                        style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d;">
                        {{ number_format($loyaltyValue, 2) }} <small>(نقاط)</small>
                    </span>
                @endif
                @if ($walletDeduction > 0)
                    <span class="text-warning font-weight-bold">−</span>
                    <span title="خصم المحفظة" class="badge px-2 py-1"
                        style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d;">
                        {{ number_format($walletDeduction, 2) }} <small>(محفظة)</small>
                    </span>
                @endif
                <span class="text-info font-weight-bold">+</span>
                <span title="رسوم التوصيل" class="badge badge-info px-2 py-1">
                    {{ number_format($deliveryFee, 2) }} <small>(توصيل)</small>
                </span>
                <span class="text-secondary font-weight-bold">+</span>
                <span title="الضريبة" class="badge badge-secondary px-2 py-1">
                    {{ number_format($vatAmount, 2) }} <small>(ضريبة)</small>
                </span>
                <span class="font-weight-bold mx-1">=</span>
                <span class="badge badge-dark px-3 py-1" style="font-size:.95rem;">
                    {{ number_format($total, 2) }} {{ __('admin.sar') }}
                </span>
            </span>
        </div>
    </div>
</div>
