@extends('admin.layout.master')

@section('title', __('admin.daily_reports'))

@section('css')
    <style>
        .dr-page { font-family: 'Cairo', sans-serif; }
        .dr-page .dr-filter-row .form-group { margin-bottom: 0; }
        .dr-page .dr-kpi-card { border: 0; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.06); }
        .dr-page .dr-kpi-card .card-body { padding: 1rem 1.25rem; }
        .dr-page .dr-kpi-value { font-size: 1.6rem; font-weight: 700; line-height: 1.2; }
        .dr-page .dr-kpi-label { color: #6e6a6a; font-size: .85rem; }
        .dr-page .dr-kpi-sub { color: #b8c2cc; font-size: .78rem; }
        .dr-page .dr-kpi-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
        .dr-page .dr-chart-title { font-size: .95rem; font-weight: 600; }
        .dr-page .dr-table th, .dr-page .dr-table td { vertical-align: middle; white-space: nowrap; }
        .dr-page .dr-table td.dr-details-cell { white-space: normal; min-width: 220px; }
        .dr-page .dr-row-returned { background-color: #f0f1f2 !important; }
        .dr-page .dr-row-returned td { color: #8a929b; }
        .dr-page .dr-driver-card { border: 1px solid #ebe9f1; border-radius: 10px; padding: .9rem; margin-bottom: .75rem; }
        .dr-page .dr-driver-name { font-weight: 700; }
        .dr-page .dr-progress { height: 7px; border-radius: 6px; }
        .dr-page .dr-product-line { display: flex; justify-content: space-between; font-size: .82rem; border-bottom: 1px dashed #ebe9f1; padding: .2rem 0; }
        .dr-page .dr-product-line:last-child { border-bottom: 0; }
        .dr-page .dr-notes-list { margin-bottom: 0; padding-right: 1.1rem; }
        .dr-page .dr-loading { display: none; }
        .dr-page.dr-is-loading .dr-loading { display: block; }
        .dr-page.dr-is-loading .dr-report-body { opacity: .55; pointer-events: none; }
        .dr-page .dr-badge-period { background: #eef2ff; color: #4a5fc7; border-radius: 8px; padding: .2rem .55rem; font-size: .78rem; }
    </style>
@endsection

@section('content')
    <div class="dr-page" id="dailyReportPage">

        {{-- Filters --}}
        <div class="row">
            <div class="col-12">
                <div class="card dr-kpi-card">
                    <div class="card-header pb-0">
                        <h4 class="card-title dr-chart-title mb-0"><i class="feather icon-truck mr-1"></i>{{ __('admin.daily_reports') }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="row dr-filter-row">
                            <div class="col-md-4 form-group">
                                <label for="dr-date">{{ __('admin.daily_report_date') }}</label>
                                <input type="date" class="form-control" id="dr-date" value="{{ $default_date }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="dr-period">{{ __('admin.delivery_period') }}</label>
                                <select class="form-control" id="dr-period">
                                    <option value="">{{ __('admin.delivery_period_all') }}</option>
                                    @foreach ($delivery_periods as $period)
                                        <option value="{{ $period->id }}">{{ $period->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="dr-driver">{{ __('admin.delivery_driver') }}</label>
                                <select class="form-control" id="dr-driver">
                                    <option value="">{{ __('admin.delivery_driver_all') }}</option>
                                    @foreach ($deliveries as $delivery)
                                        <option value="{{ $delivery->id }}">{{ $delivery->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 d-flex justify-content-end">
                                <a class="btn bg-gradient-success mr-1 mb-1 waves-effect waves-light"
                                   href="#" id="btn-export-excel">
                                    <i class="fa fa-file-excel-o"></i> {{ __('admin.export_excel') }}
                                </a>
                                <a class="btn bg-gradient-danger mr-1 mb-1 waves-effect waves-light"
                                   href="#" id="btn-export-pdf">
                                    <i class="fa fa-file-pdf-o"></i> {{ __('admin.export_pdf') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dr-loading text-center my-2">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div class="dr-report-body">
            {{-- KPI cards --}}
            <div class="row" id="drKpis"></div>

            {{-- Charts --}}
            <div class="row">
                <div class="col-lg-6">
                    <div class="card dr-kpi-card">
                        <div class="card-header"><h5 class="dr-chart-title mb-0">{{ __('admin.amounts_by_neighborhood') }}</h5></div>
                        <div class="card-body"><div id="dr-chart-neighborhood"></div></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card dr-kpi-card">
                        <div class="card-header"><h5 class="dr-chart-title mb-0">{{ __('admin.payment_methods_chart') }}</h5></div>
                        <div class="card-body"><div id="dr-chart-payment"></div></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card dr-kpi-card">
                        <div class="card-header"><h5 class="dr-chart-title mb-0">{{ __('admin.loading_by_product') }}</h5></div>
                        <div class="card-body"><div id="dr-chart-products"></div></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card dr-kpi-card">
                        <div class="card-header"><h5 class="dr-chart-title mb-0">{{ __('admin.orders_by_period') }}</h5></div>
                        <div class="card-body"><div id="dr-chart-period"></div></div>
                    </div>
                </div>
            </div>

            {{-- Driver cards --}}
            <div class="row">
                <div class="col-12">
                    <div class="card dr-kpi-card">
                        <div class="card-header"><h5 class="dr-chart-title mb-0">{{ __('admin.delivery_driver') }}</h5></div>
                        <div class="card-body" id="drDrivers"></div>
                    </div>
                </div>
            </div>

            {{-- Orders table --}}
            <div class="row">
                <div class="col-12">
                    <div class="card dr-kpi-card">
                        <div class="card-header"><h5 class="dr-chart-title mb-0">{{ __('admin.total_orders') }}</h5></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table dr-table mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>{{ __('admin.order_number') }}</th>
                                            <th>{{ __('admin.neighborhood') }}</th>
                                            <th>{{ __('admin.period') }}</th>
                                            <th>{{ __('admin.details') }}</th>
                                            <th>{{ __('admin.payment_status') }}</th>
                                            <th>{{ __('admin.amount') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="drOrders"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Notes --}}
         
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('admin/app-assets/vendors/js/charts/apexcharts.min.js') }}"></script>
    <script>
        (function () {
            const pageEl = document.getElementById('dailyReportPage');
            const dateEl = document.getElementById('dr-date');
            const periodEl = document.getElementById('dr-period');
            const driverEl = document.getElementById('dr-driver');
            const kpisEl = document.getElementById('drKpis');
            const driversEl = document.getElementById('drDrivers');
            const ordersEl = document.getElementById('drOrders');
            const dataUrl = @json(route('admin.daily-reports.data'));
            const excelExportUrl = @json(route('admin.daily-reports.export.excel'));
            const pdfExportUrl = @json(route('admin.daily-reports.export.pdf'));
            const currency = @json($settings['default_currency'] ?? 'ر.س');
            const labels = {
                ordersCount: @json(__('admin.orders_count')),
                totalAmounts: @json(__('admin.total_amounts')),
                averageAmount: @json(__('admin.average_amount')),
                prepaid: @json(__('admin.prepaid')),
                cashOnDelivery: @json(__('admin.cash_on_delivery')),
                loadingPercentage: @json(__('admin.loading_percentage')),
                totalLoading: @json(__('admin.total_loading')),
                actualLoading: @json(__('admin.actual_loading')),
                returned: @json(__('admin.returned')),
                postponed: @json(__('admin.postponed')),
                cartons: @json(__('admin.cartons')),
                withoutPeriod: @json(__('admin.without_period')),
                noOrders: @json(__('admin.no_orders_for_selected_filters')),
                empty: @json(__('admin.no_data_available')),
            };

            const charts = {};

            const escapeHtml = (value) => {
                if (value === null || value === undefined) return '';
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            const money = (value) => Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const qs = () => {
                const params = new URLSearchParams();
                if (dateEl && dateEl.value) params.set('date', dateEl.value);
                if (periodEl && periodEl.value) params.set('period_id', periodEl.value);
                if (driverEl && driverEl.value) params.set('delivery_id', driverEl.value);
                return params.toString();
            };

            function destroyChart(key) {
                if (charts[key]) {
                    charts[key].destroy();
                    delete charts[key];
                }
            }

            function renderChart(key, options) {
                destroyChart(key);
                const el = document.querySelector(options.el);
                if (!el) return;
                const config = Object.assign({}, options.config);
                config.chart = Object.assign({ type: options.type, height: 320, toolbar: { show: false }, fontFamily: 'Cairo, sans-serif' }, config.chart || {});
                config.noData = { text: labels.empty };
                charts[key] = new ApexCharts(el, config);
                charts[key].render();
            }

            function renderKpis(summary) {
                const cards = [
                    {
                        label: labels.ordersCount,
                        value: summary.orders_count,
                        sub: labels.returned + ': ' + summary.returned_count + ' | ' + labels.postponed + ': ' + summary.postponed_count,
                        icon: 'icon-shopping-cart',
                        color: 'bg-rgba-primary text-primary',
                    },
                    {
                        label: labels.totalAmounts,
                        value: money(summary.total_amount),
                        sub: labels.averageAmount + ': ' + money(summary.average_amount) + ' ' + currency,
                        icon: 'icon-dollar-sign',
                        color: 'bg-rgba-success text-success',
                    },
                    {
                        label: labels.prepaid,
                        value: summary.prepaid_count,
                        sub: money(summary.prepaid_total) + ' ' + currency,
                        icon: 'icon-credit-card',
                        color: 'bg-rgba-info text-info',
                    },
                    {
                        label: labels.cashOnDelivery,
                        value: summary.cod_count,
                        sub: money(summary.cod_total) + ' ' + currency,
                        icon: 'icon-wallet',
                        color: 'bg-rgba-warning text-warning',
                    },
                    {
                        label: labels.loadingPercentage,
                        value: summary.loading_percentage + '%',
                        sub: labels.actualLoading + ' / ' + labels.totalLoading + ' (' + summary.actual_loading + ' / ' + summary.total_loading + ')',
                        icon: 'icon-truck',
                        color: 'bg-rgba-danger text-danger',
                    },
                ];

                kpisEl.innerHTML = cards.map(function (card, i) {
                    const cols = i === 4 ? 'col-lg-4' : 'col-lg-2 col-md-6';
                    return `
                        <div class="${cols}">
                            <div class="card dr-kpi-card mb-2">
                                <div class="card-body d-flex align-items-center">
                                    <div class="dr-kpi-icon ${card.color} mr-2 ml-2"><i class="feather ${card.icon}"></i></div>
                                    <div>
                                        <div class="dr-kpi-value">${escapeHtml(card.value)}</div>
                                        <div class="dr-kpi-label">${escapeHtml(card.label)}</div>
                                        <div class="dr-kpi-sub">${escapeHtml(card.sub)}</div>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                }).join('');
            }

            function renderCharts(chartData) {
                // Amounts by neighborhood
                renderChart('neighborhood', {
                    el: '#dr-chart-neighborhood',
                    type: 'bar',
                    config: {
                        plotOptions: { bar: { horizontal: false, columnWidth: '55%', endingShape: 'rounded' } },
                        dataLabels: { enabled: false },
                        stroke: { show: true, width: 2, colors: ['transparent'] },
                        colors: ['#7367F0'],
                        series: [{ name: labels.totalAmounts, data: chartData.by_neighborhood.map(function (n) { return n.total; }) }],
                        xaxis: { categories: chartData.by_neighborhood.map(function (n) { return n.name; }), labels: { style: { fontSize: '12px' } } },
                        yaxis: { labels: { formatter: function (v) { return money(v); } } },
                        tooltip: { y: { formatter: function (v) { return money(v) + ' ' + currency; } } },
                    },
                });

                // Payment methods doughnut
                renderChart('payment', {
                    el: '#dr-chart-payment',
                    type: 'donut',
                    config: {
                        series: chartData.payment.map(function (p) { return p.value; }),
                        labels: chartData.payment.map(function (p) { return p.label === 'prepaid' ? labels.prepaid : labels.cashOnDelivery; }),
                        colors: ['#28c76f', '#ff9f43'],
                        legend: { position: 'bottom' },
                        dataLabels: { enabled: true, formatter: function (val) { return val.toFixed(0) + '%'; } },
                    },
                });

                // Loading by product
                renderChart('products', {
                    el: '#dr-chart-products',
                    type: 'bar',
                    config: {
                        plotOptions: { bar: { horizontal: false, columnWidth: '55%', endingShape: 'rounded' } },
                        dataLabels: { enabled: false },
                        stroke: { show: true, width: 2, colors: ['transparent'] },
                        colors: ['#7367F0', '#28c76f'],
                        series: [
                            { name: labels.totalLoading, data: chartData.products.map(function (p) { return p.total_quantity; }) },
                            { name: labels.actualLoading, data: chartData.products.map(function (p) { return p.delivered_quantity; }) },
                        ],
                        xaxis: { categories: chartData.products.map(function (p) { return p.name; }), labels: { style: { fontSize: '11px' } } },
                        yaxis: { labels: { formatter: function (v) { return v + ' ' + labels.cartons; } } },
                    },
                });

                // Orders by period doughnut
                renderChart('period', {
                    el: '#dr-chart-period',
                    type: 'donut',
                    config: {
                        series: chartData.by_period.map(function (p) { return p.count; }),
                        labels: chartData.by_period.map(function (p) { return p.name || labels.withoutPeriod; }),
                        colors: ['#7367F0', '#00cfe8', '#ea5455', '#ff9f43', '#28c76f'],
                        legend: { position: 'bottom' },
                        dataLabels: { enabled: true, formatter: function (val) { return val.toFixed(0) + '%'; } },
                    },
                });
            }

            function renderDrivers(drivers) {
                if (!drivers.length) {
                    driversEl.innerHTML = '<div class="text-muted">' + escapeHtml(labels.empty) + '</div>';
                    return;
                }

                const rows = drivers.map(function (d) {
                    const products = (d.products || []).map(function (p) {
                        return '<div class="dr-product-line"><span>' + escapeHtml(p.name) + '</span>' +
                            '<span class="text-muted">' + labels.actualLoading + ': ' + p.delivered_quantity + ' / ' + labels.totalLoading + ': ' + p.total_quantity + '</span></div>';
                    }).join('');

                    return `
                        <div class="dr-driver-card">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div class="dr-driver-name"><i class="feather icon-user mr-1"></i>${escapeHtml(d.name)}</div>
                                <div class="text-muted small">${labels.loadingPercentage}: <strong>${d.loading_percentage}%</strong></div>
                            </div>
                            <div class="progress dr-progress mb-2">
                                <div class="progress-bar" role="progressbar" style="width: ${Math.min(100, d.loading_percentage)}%" aria-valuenow="${d.loading_percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="text-muted small mb-1">${labels.actualLoading}: <strong>${d.actual_loading}</strong> / ${labels.totalLoading}: <strong>${d.total_loading}</strong> ${labels.cartons}</div>
                            ${products}
                        </div>`;
                }).join('');

                driversEl.innerHTML = '<div class="row"><div class="col-md-6">' + rows + '</div></div>';
            }

            function renderOrders(orders) {
                if (!orders.length) {
                    ordersEl.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">' + escapeHtml(labels.noOrders) + '</td></tr>';
                    return;
                }

                ordersEl.innerHTML = orders.map(function (o) {
                    const returned = o.category === 'returned';
                    const rowClass = returned ? 'dr-row-returned' : '';
                    const periodBadge = o.period
                        ? '<span class="dr-badge-period">' + escapeHtml(o.period) + '</span>'
                        : '<span class="text-muted">' + escapeHtml(labels.withoutPeriod) + '</span>';
                    const details = (o.details || []).map(function (d) {
                        return escapeHtml(d.name) + ' × ' + d.quantity;
                    }).join(' + ');
                    const payment = o.is_cod
                        ? '<span class="badge badge-warning">' + escapeHtml(labels.cashOnDelivery) + '</span>'
                        : '<span class="badge badge-success">' + escapeHtml(labels.prepaid) + '</span>';

                    return `
                        <tr class="${rowClass}">
                            <td>${o.seq}</td>
                            <td>${escapeHtml(o.order_number)}</td>
                            <td>${escapeHtml(o.neighborhood)}</td>
                            <td>${periodBadge}</td>
                            <td class="dr-details-cell">${details}</td>
                            <td>${payment}</td>
                            <td>${money(o.amount)} ${escapeHtml(currency)}</td>
                        </tr>`;
                }).join('');
            }

            async function loadData() {
                pageEl.classList.add('dr-is-loading');

                try {
                    const response = await fetch(dataUrl + '?' + qs(), {
                        headers: { 'Accept': 'application/json' },
                    });

                    if (!response.ok) {
                        throw new Error('Request failed: ' + response.status);
                    }

                    const data = await response.json();

                    renderKpis(data.summary);
                    renderCharts(data.charts);
                    renderDrivers(data.drivers);
                    renderOrders(data.orders);
                } catch (error) {
                    kpisEl.innerHTML = '';
                    driversEl.innerHTML = '';
                    ordersEl.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">' + escapeHtml(labels.empty) + '</td></tr>';
                    console.error('Daily report error:', error);
                } finally {
                    pageEl.classList.remove('dr-is-loading');
                }
            }

            [dateEl, periodEl, driverEl].forEach(function (el) {
                if (el) el.addEventListener('change', loadData);
            });

            const exportBtn = (id, url) => {
                const btn = document.getElementById(id);
                if (!btn) return;
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    window.location.href = url + '?' + qs();
                });
            };

            exportBtn('btn-export-excel', excelExportUrl);
            exportBtn('btn-export-pdf', pdfExportUrl);

            loadData();
        })();
    </script>
@endsection
