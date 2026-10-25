<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $order->order_number }}</title>
  
</head><style>
    @page {
        margin: 20px;
    }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        direction: rtl;
        text-align: right;
        margin: 0;
        padding: 0;
        background-color: #fff;
        font-size: 12px;
    }

    .invoice-container {
        padding: 20px;
        background-color: #fff;
        page-break-inside: avoid;
    }

    /* ── Header ── */
    .header {
        border-bottom: 3px solid #1a3a5c;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }

    .header-table {
        width: 100%;
        border-collapse: collapse;
        border: none;
        margin-bottom: 10px;
    }

    .header-table td {
        vertical-align: top;
        border: none;
        padding: 0;
    }

    /* Right cell (RTL = first <td>): Company info */
    .cell-brand {
        text-align: right;
        width: 75%;
    }

    .company-logo {
        max-height: 55px;
        max-width: 160px;
        margin-bottom: 6px;
    }

    .company-name {
        font-size: 17px;
        font-weight: bold;
        color: #1a3a5c;
        margin: 0 0 3px 0;
    }

    .company-detail {
        font-size: 10.5px;
        color: #555;
        margin: 1px 0;
    }

    .company-detail strong {
        color: #333;
    }

    /* Left cell (RTL = second <td>): QR Code */
    .cell-qr {
        text-align: left;
        width: 25%;
    }

    .qr-code {
        width: 105px;
        height: 105px;
        display: block;
    }

    /* Centered invoice title row */
    .header-title {
        text-align: center;
        padding-top: 8px;
        border-top: 1px solid #dde3ea;
    }

    .header-title h1 {
        font-size: 18px;
        color: #1a3a5c;
        margin: 0 0 2px 0;
        letter-spacing: 0.5px;
    }

    .header-title .invoice-meta {
        font-size: 11.5px;
        color: #666;
        margin: 0;
    }

    .admin-badge {
        background-color: #1a3a5c;
        color: #fff;
        padding: 1px 8px;
        border-radius: 8px;
        font-size: 9.5px;
        margin-top: 4px;
        display: inline-block;
    }

    .info-section {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        margin-bottom: 15px;
    }

    .info-box {
        flex: 1 1 45%;
        margin-bottom: 15px;
        padding: 10px;
        border: 1px solid #dee2e6;
        border-radius: 6px;
    }

    .info-box h3 {
        font-size: 14px;
        color: #007bff;
        margin-bottom: 10px;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 5px;
    }

    .info-item {
        margin-bottom: 6px;
        font-size: 12px;
    }

    .info-label {
        font-weight: bold;
        color: #343a40;
    }

    .info-value {
        color: #495057;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        page-break-inside: avoid;
    }

    .items-table th,
    .items-table td {
        border: 1px solid #dee2e6;
        padding: 8px;
        font-size: 12px;
        text-align: right;
    }

    .items-table th {
        background-color: #007bff;
        color: white;
    }

    .totals-section {
        margin-top: 20px;
        padding-top: 10px;
        border-top: 2px solid #dee2e6;
        page-break-inside: avoid;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        margin-bottom: 8px;
    }

    .total-row.final {
        font-weight: bold;
        font-size: 14px;
        color: #007bff;
        border-top: 1px solid #007bff;
        padding-top: 6px;
    }

    .footer {
        margin-top: 30px;
        text-align: center;
        color: #6c757d;
        font-size: 11px;
        border-top: 1px solid #dee2e6;
        padding-top: 10px;
        page-break-inside: avoid;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 10px;
        font-weight: bold;
        font-size: 11px;
        color: #fff;
    }

    .status-completed { background-color: #28a745; }
    .status-processing { background-color: #ffc107; color: #000; }
    .status-cancelled { background-color: #dc3545; }

    /* Avoid table or section breaking mid-page */
    tr, td, th, .info-box, .totals-section, .footer {
        page-break-inside: avoid;
    }
</style>

<body>
<div class="invoice-container">
    <!-- Header -->
    <div class="header">
        <table class="header-table">
            <tr>
                {{-- RTL: first <td> = right side --}}
                <td class="cell-brand">
                    @if(!empty($logoUrl))
                        <img src="{{ $logoUrl }}" alt="Logo" class="company-logo">
                    @endif
                    <div class="company-name">{{ $companyName ?? 'Awfer' }}</div>
                    @if(!empty($provider) && !empty($provider->commercial_register_no))
                        <div class="company-detail"><strong>السجل التجاري:</strong> {{ $provider->commercial_register_no }}</div>
                    @endif
                    @if(!empty($taxNumber))
                        <div class="company-detail"><strong>الرقم الضريبي:</strong> {{ $taxNumber }}</div>
                    @endif
                    @if(!empty($provider))
                        <div class="company-detail" style="color:#1a3a5c;">{{ $provider->commercial_name }}</div>
                    @endif
                </td>
                {{-- RTL: second <td> = left side --}}
                <td class="cell-qr">
                    @if(!empty($qrDataUri))
                        <img src="{{ $qrDataUri }}" alt="QR" class="qr-code">
                    @endif
                </td>
            </tr>
        </table>

        <!-- Centered: Invoice Title -->
        <div class="header-title">
            <h1>فاتورة الطلب</h1>
            <p class="invoice-meta">رقم الفاتورة: {{ $order->order_number }} &mdash; {{ $order->created_at->format('Y-m-d H:i') }}</p>
            <span class="admin-badge">إدارة النظام</span>
        </div>
    </div>

    <!-- Customer Info -->
    <div class="info-section">
        <div class="info-box">
            <h3>معلومات العميل</h3>
            <div class="info-item"><span class="info-label">الاسم:</span> <span class="info-value">{{ $customer->name }}</span></div>
            <div class="info-item"><span class="info-label">رقم الهاتف:</span> <span class="info-value">{{ $customer->country_code }}{{ $customer->phone }}</span></div>
            <div class="info-item"><span class="info-label">البريد الإلكتروني:</span> <span class="info-value">{{ $customer->email ?? 'غير محدد' }}</span></div>
            <div class="info-item"><span class="info-label">نوع المستخدم:</span> <span class="info-value">{{ $customer->type === 'client' ? 'عميل' : 'مزود' }}</span></div>
        </div>
    </div>

    <!-- Order Info -->
    <div class="info-section">
        <div class="info-box">
            <h3>معلومات الطلب</h3>
            <div class="info-item"><span class="info-label">رقم الطلب:</span> <span class="info-value">{{ $order->order_number }}</span></div>
            @php
                $statusKey = $order->status;
                $statusEnum = \App\Enums\OrderStatus::tryFrom($statusKey);
                $statusLabel = $statusEnum?->label();
                if (!$statusLabel) {
                    $statusLabel = __('admin.' . $statusKey);
                    if ($statusLabel === 'admin.' . $statusKey) {
                        $statusLabel = __('admin.' . str_replace('_','-',$statusKey));
                    }
                }
                if (str_starts_with($statusLabel, 'admin.')) {
                    $statusLabel = $statusKey;
                }
                $statusColorName = $statusEnum?->color() ?? null;
                $bgColor = match ($statusColorName) {
                    'primary' => '#007bff',
                    'success' => '#28a745',
                    'warning' => '#ffc107',
                    'danger'  => '#dc3545',
                    'info'    => '#17a2b8',
                    'secondary' => '#6c757d',
                    default   => '#6c757d',
                };
                $textColor = $statusColorName === 'warning' ? '#000' : '#fff';
            @endphp
            <div class="info-item"><span class="info-label">حالة الطلب:</span> <span class="status-badge" style="background-color: {{ $bgColor }}; color: {{ $textColor }};">{{ $statusLabel }}</span></div>
            <div class="info-item"><span class="info-label">طريقة الدفع:</span> <span class="info-value">{{ $payment_method->name ?? 'غير محدد' }}</span></div>
            <div class="info-item"><span class="info-label">حالة الدفع:</span> <span class="info-value">{{ __('admin.' . $order->payment_status) }}</span></div>
        </div>

        <!-- Address Info -->
        <div class="info-box">
            <h3>معلومات العنوان</h3>
            @if($address)
                <div class="info-item"><span class="info-label">رقم الهاتف:</span> <span class="info-value">{{ $customer->phone ?? 'غير محدد' }}</span></div>
                <div class="info-item"><span class="info-label">المدينة:</span> <span class="info-value">{{ $address->city->name ?? 'غير محدد' }}</span></div>
                <div class="info-item"><span class="info-label">اسم المستلم:</span> <span class="info-value">{{ $customer->name ?? ($order->reciver_name ?? 'غير محدد') }}</span></div>
            @else
                <div class="info-item"><span class="info-value">لا يوجد عنوان محدد</span></div>
            @endif
        </div>
    </div>

    <!-- Order Items -->
    <h3>المنتجات</h3>
    <table class="items-table">
        <thead>
            <tr>
                <th>الاسم</th>
                <th>الكمية</th>
                <th>السعر النهائي</th>
                <th>قيمة الخصم</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    @php
                        $finalUnit = $item->quantity > 0 ? ((float) $item->total) / (int) $item->quantity : (float) $item->total;
                        $discountVal = (float) ($item->discount_amount ?? max(0, ($item->price * $item->quantity) - $item->total));
                    @endphp
                    <td>{{ number_format($finalUnit, 2) }} ريال</td>
                    <td>{{ $discountVal > 0 ? ('-' . number_format($discountVal, 2) . ' ريال') : '0.00 ريال' }}</td>
                    <td>{{ number_format($item->total, 2) }} ريال</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals-section">
        @php
            $totals                 = $totals ?? [];
            $productsTotal          = (float) ($totals['products_total'] ?? 0);
            $subtotal               = (float) ($totals['subtotal'] ?? $order->subtotal);
            $discount               = (float) ($totals['discount'] ?? 0);
            $couponAmount           = (float) ($totals['coupon_amount'] ?? 0);
            $couponCode             = $totals['coupon_code'] ?? null;
            $couponValue            = $totals['coupon_value'] ?? null;
            $walletDeduction        = (float) ($totals['wallet_deduction'] ?? 0);
            $loyaltyDeduction       = (float) ($totals['loyalty_deduction'] ?? 0);
            $deliveryFee            = (float) ($totals['delivery_fee'] ?? 0);
            $vatPercent             = (float) ($totals['vat_percent'] ?? 15);
            $vatAmount              = (float) ($totals['vat_amount'] ?? 0);
            $totalWithoutVat        = (float) ($totals['total_without_vat'] ?? 0);
            $totalWithVat           = (float) ($totals['total_with_vat'] ?? 0);
            $finalTotal             = (float) ($totals['final_total'] ?? $order->total);

            // الخصم المباشر على المنتجات (غير الكوبون)
            $directDiscount = max(0, round($discount - $couponAmount, 2));
        @endphp

        {{-- إجمالي المنتجات بدون ضريبة --}}
        <div class="total-row">
            <span>إجمالي المنتجات بدون ضريبة:</span>
            <span>{{ number_format($productsTotal, 2) }} ريال</span>
        </div>

        {{-- خصم المنتجات --}}
        @if($directDiscount > 0)
        <div class="total-row" style="color:#b45309;">
            <span>خصم على الطلب:</span>
            <span>-{{ number_format($directDiscount, 2) }} ريال</span>
        </div>
        @endif

        {{-- خصم الكوبون --}}
        @if($couponAmount > 0)
        <div class="total-row" style="color:#b45309;">
            <span>
                كوبون الخصم{{ $couponCode ? ' (' . $couponCode . ')' : '' }}
                @if($couponValue) ({{ number_format((float)$couponValue, 2) }}%) @endif:
            </span>
            <span>-{{ number_format($couponAmount, 2) }} ريال</span>
        </div>
        @endif

        {{-- خصم المحفظة --}}
        @if($walletDeduction > 0)
        <div class="total-row" style="color:#b45309;">
            <span>خصم المحفظة:</span>
            <span>-{{ number_format($walletDeduction, 2) }} ريال</span>
        </div>
        @endif

        {{-- خصم النقاط --}}
        @if($loyaltyDeduction > 0)
        <div class="total-row" style="color:#b45309;">
            <span>خصم نقاط الولاء:</span>
            <span>-{{ number_format($loyaltyDeduction, 2) }} ريال</span>
        </div>
        @endif

        {{-- رسوم التوصيل --}}
        <div class="total-row">
            <span>رسوم التوصيل:</span>
            <span>{{ number_format($deliveryFee, 2) }} ريال</span>
        </div>

        {{-- الإجمالي بدون ضريبة --}}
        <div class="total-row">
            <span>الإجمالي بدون ضريبة:</span>
            <span>{{ number_format($totalWithoutVat, 2) }} ريال</span>
        </div>

        {{-- الضريبة --}}
        <div class="total-row">
            <span>ضريبة القيمة المضافة ({{ number_format($vatPercent, 2) }}%):</span>
            <span>{{ number_format($vatAmount, 2) }} ريال</span>
        </div>

        {{-- الإجمالي النهائي --}}
        <div class="total-row final">
            <span>الإجمالي النهائي:</span>
            <span>{{ number_format($finalTotal, 2) }} ريال</span>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>تم إنشاء هذه الفاتورة في {{ now()->format('Y-m-d H:i:s') }}</p>
        <p>فاتورة إدارية - نظام إدارة الطلبات</p>
    </div>
</div>
</body>
</html> 
