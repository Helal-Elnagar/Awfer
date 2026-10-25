@extends('admin.layout.master')
@section('css')
    <link rel="stylesheet" type="text/css" href="{{asset('admin/app-assets/vendors/css/charts/apexcharts.css')}}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <style>
        #orders-map {
            height: 460px;
            border-radius: 12px;
        }
        .leaflet-popup-content .order-map-card {
            min-width: 220px;
            font-family: inherit;
            direction: ltr;
        }
        .leaflet-popup-content .order-map-card h6 {
            margin-bottom: 6px;
            font-weight: 700;
        }
        .leaflet-popup-content .order-map-card .meta {
            margin: 0;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
@endsection
@section('content')
{{--        <div class="row">--}}
{{--            <div class="col-lg-6 col-md-12 col-sm-12">--}}
{{--                <div class="card bg-analytics text-white">--}}
{{--                    <div class="card-content">--}}
{{--                        <div class="card-body text-center">--}}
{{--                            <img src="{{asset('admin/app-assets/images/elements/decore-left.png')}}" class="img-left" alt="card-img-left">--}}
{{--                            <img src="{{asset('admin/app-assets/images/elements/decore-right.png')}}" class="img-right" alt="card-img-right">--}}
{{--                            <div class="text-center">--}}
{{--                                <h1 class="mb-2 text-white">{{__('admin.welcome')}} {{auth('admin')->user()->name}}</h1>--}}
{{--                                <p class="m-auto w-75">{{  date('d-m-Y', strtotime(\Carbon\Carbon::now())) }} </p>--}}
{{--                                <p class="m-auto w-75">{{  date('h:i A', strtotime(\Carbon\Carbon::now())) }} </p>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--            <div class="col-lg-6 col-md-12 weatherWidgetInner">--}}
{{--                <a class="weatherwidget-io" href="https://forecast7.com/{{lang()}}/24d7146d68/riyadh/" data-label_1="{{__('admin.riyadh')}}" data-label_2="{{__('admin.weather')}}" data-font="en-us"  data-icons="Climacons" data-theme="original" data-basecolor="rgb(16 22 58)" ></a>--}}
{{--            </div>--}}
{{--        </div>--}}


        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">{{__('admin.Orders_Map')}}</h4>
                    </div>
                    <div class="card-body">
                        <div id="orders-map"></div>
                    </div>
                </div>
            </div>
        </div>

                <div class="row align-center">
            @foreach($menus as $key => $menu)
                @php $color = $colores[array_rand($colores)] @endphp
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card text-center">
                        <div class="card-content">
                            <div class="card-body">
                                <div class="avatar bg-rgba-{{$color}} p-50 m-0 mb-1">
                                    <div class="avatar-content">
                                        <i class="feather {!! $menu['icon'] !!} text-{!! $color !!} font-medium-5"></i>
                                    </div>
                                </div>
                                <h2 class="text-bold-700">{{$menu['count']}}</h2>
                                <p class="mb-0 line-ellipsis" style="color: #6e6a6a">{{$menu['name']}}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
      
@endsection
@section('js')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        !function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src='https://weatherwidget.io/js/widget.min.js';fjs.parentNode.insertBefore(js,fjs);}}(document,'script','weatherwidget-io-js');
    </script>

    <script src="{{asset('admin/app-assets/vendors/js/charts/apexcharts.min.js')}}"></script>
    <script src="{{asset('admin/charts_functions.js')}}"></script>
    <script>
        var revenueChartoptions = {
            chart: {
            height: 270,
            toolbar: { show: false },
            type: 'line',
            },
            stroke: {
            curve: 'smooth',
            dashArray: [0, 8],
            width: [4, 2],
            },
            grid: {
            borderColor: '#e7e7e7',
            },
            legend: {
            show: false,
            },
            colors: ['#f29292', '#b9c3cd'],

            fill: {
            type: 'gradient',
            gradient: {
                shade: 'dark',
                inverseColors: false,
                gradientToColors: ['#7367F0', '#b9c3cd'],
                shadeIntensity: 1,
                type: 'horizontal',
                opacityFrom: 1,
                opacityTo: 1,
                stops: [0, 100, 100, 100]
            },
            },
            markers: {
            size: 0,
            hover: {
                size: 5
            }
            },
            xaxis: {
            labels: {
                style: {
                colors: '#b9c3cd',
                }
            },
            axisTicks: {
                show: false,
            },
            categories: ['1', '2', '3', '4', '5', '6', '7', '8' ,'9','10','11','12'],
            axisBorder: {
                show: false,
            },
            tickPlacement: 'on',
            },
            yaxis: {
            tickAmount: 5,
            labels: {
                style: {
                color: '#b9c3cd',
                },
                formatter: function (val) {
                return val > 999 ? (val / 1000).toFixed(1) + 'k' : val;
                }
            }
            },
            tooltip: {
            x: { show: false }
            },
            series: [{
            name: "{{__('admin.cities')}}",
            data: @json($countryArray)
            }
            ,
            {
            name: "{{__('admin.countries')}}",
            data: @json($cityArray)
            }
            ],

        }

        var revenueChart = new ApexCharts(
            document.querySelector("#revenue-chart"),
            revenueChartoptions
        ).render();
        //columns chart
        var options = {
          series: [{
          name: 'Net Profit',
          data: [44, 55, 57, 56, 61, 58, 63, 60, 66]
        }, {
          name: 'Revenue',
          data: [76, 85, 101, 98, 87, 105, 91, 114, 94]
        }, {
          name: 'Free Cash Flow',
          data: [35, 41, 36, 26, 45, 48, 52, 53, 41]
        }],
          chart: {
          type: 'bar',
          height: 350
        },
        plotOptions: {
          bar: {
            horizontal: false,
            columnWidth: '55%',
            endingShape: 'rounded'
          },
        },
        dataLabels: {
          enabled: false
        },
        stroke: {
          show: true,
          width: 2,
          colors: ['transparent']
        },
        xaxis: {
          categories: ['Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
        },
        yaxis: {
          title: {
            text: '$ (thousands)'
          }
        },
        fill: {
          opacity: 1
        },
        tooltip: {
          y: {
            formatter: function (val) {
              return "$ " + val + " thousands"
            }
          }
        }
        };

        var chart = new ApexCharts(document.querySelector("#columns-chart"), options);
        chart.render();
    </script>

    <script>
        (function () {
            const orderGroups = @json($orderLocations ?? []);
            const mapElement = document.getElementById('orders-map');

            if (!mapElement || !Array.isArray(orderGroups) || orderGroups.length === 0) {
                if (mapElement) {
                    mapElement.innerHTML = '<div class="text-muted p-2">No order locations found.</div>';
                }
                return;
            }

            const map = L.map('orders-map');
            const markers = [];

            const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            });
            tiles.addTo(map);

            const escapeHtml = (value) => {
                if (value === null || value === undefined) return '';
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            const statusColors = {
                'pending': '#f59e0b', 'new': '#3b82f6', 'confirmed': '#6366f1',
                'processing': '#8b5cf6', 'delivered': '#10b981', 'cancelled': '#ef4444',
                'problem': '#f97316', 'out-for-delivery': '#06b6d4'
            };

            const buildPopupContent = (orders) => {
                if (orders.length === 1) {
                    const o = orders[0];
                    const totalText = Number(o.total || 0).toFixed(2);
                    const color = statusColors[o.status] || '#6c757d';
                    return `
                        <div class="order-map-card">
                            <h6>طلب #${escapeHtml(o.order_number || o.id)}</h6>
                            <p class="meta"><strong>العميل:</strong> ${escapeHtml(o.customer_name || '-')}</p>
                            <p class="meta"><strong>الهاتف:</strong> ${escapeHtml(o.customer_phone || '-')}</p>
                            <p class="meta"><strong>الحالة:</strong> <span style="color:${color}; font-weight:bold;">${escapeHtml(o.status || '-')}</span></p>
                            <p class="meta"><strong>الإجمالي:</strong> ${escapeHtml(totalText)} ر.س</p>
                            <a href="${escapeHtml(o.show_url)}" class="btn btn-sm btn-primary mt-1" style="font-size:11px;">عرض التفاصيل</a>
                        </div>`;
                }

                // Multiple orders at same location
                let rows = orders.map(o => {
                    const color = statusColors[o.status] || '#6c757d';
                    return `<tr>
                        <td><a href="${escapeHtml(o.show_url)}" style="font-weight:bold;">#${escapeHtml(o.order_number || o.id)}</a></td>
                        <td>${escapeHtml(o.customer_name || '-')}</td>
                        <td style="color:${color};">${escapeHtml(o.status || '-')}</td>
                        <td>${Number(o.total||0).toFixed(2)}</td>
                    </tr>`;
                }).join('');

                return `
                    <div class="order-map-card" style="min-width:320px;">
                        <h6>${orders.length} طلبات في هذا الموقع</h6>
                        <table style="width:100%; font-size:11px; border-collapse:collapse;">
                            <thead><tr style="background:#f3f4f6;">
                                <th style="padding:3px 5px;">رقم الطلب</th>
                                <th style="padding:3px 5px;">العميل</th>
                                <th style="padding:3px 5px;">الحالة</th>
                                <th style="padding:3px 5px;">الإجمالي</th>
                            </tr></thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>`;
            };

            orderGroups.forEach((group) => {
                const orders = Array.isArray(group) ? group : [group];
                if (!orders.length) return;
                const first = orders[0];
                if (first.latitude === null || first.longitude === null) return;

                // Show count badge on marker if multiple orders
                let markerIcon = undefined;
                if (orders.length > 1) {
                    markerIcon = L.divIcon({
                        className: '',
                        html: `<div style="background:#ef4444;color:#fff;border-radius:50%;width:28px;height:28px;
                                display:flex;align-items:center;justify-content:center;font-size:12px;
                                font-weight:bold;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);">
                                ${orders.length}</div>`,
                        iconSize: [28, 28],
                        iconAnchor: [14, 14],
                    });
                }

                const marker = markerIcon
                    ? L.marker([first.latitude, first.longitude], { icon: markerIcon }).addTo(map)
                    : L.marker([first.latitude, first.longitude]).addTo(map);

                marker.bindPopup(buildPopupContent(orders), { closeButton: true, autoClose: false, maxWidth: 380 });

                marker.on('mouseover', function () { this.openPopup(); });
                marker.on('mouseout', function () { if (orders.length === 1) this.closePopup(); });
                marker.on('click', function () {
                    this.openPopup();
                    // Navigate directly only for single orders
                    if (orders.length === 1 && first.show_url) {
                        window.location.href = first.show_url;
                    }
                });

                markers.push(marker);
            });

            if (markers.length === 0) {
                map.setView([24.7136, 46.6753], 6);
                return;
            }

            const group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.15));
        })();
    </script>
@endsection
