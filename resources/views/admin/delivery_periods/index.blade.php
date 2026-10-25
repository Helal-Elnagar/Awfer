@extends('admin.layout.master')

@section('css')
    <link rel="stylesheet" type="text/css" href="{{asset('admin/app-assets/vendors/css/extensions/sweetalert2.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('admin/index_page.css')}}">
@endsection

@section('content')

@php
    $addButtonRoute = !$maxReached ? route('admin.delivery_periods.create') : null;
@endphp

<x-admin.table
    datefilter="false"
    order="true"
    :addbutton="$addButtonRoute"
    deletebutton="{{ route('admin.delivery_periods.deleteAll') }}"
    :searchArray="[
        'name' => [
            'input_type' => 'text',
            'input_name' => __('admin.name'),
        ],
        'description' => [
            'input_type' => 'text',
            'input_name' => __('admin.description'),
        ],
    ]"
>
    <x-slot name="tableContent">
        <div class="table_content_append card">
            {{-- table content will append here --}}
        </div>
    </x-slot>
</x-admin.table>

@endsection

@section('js')
    <script src="{{asset('admin/app-assets/vendors/js/extensions/sweetalert2.all.min.js')}}"></script>
    <script src="{{asset('admin/app-assets/js/scripts/extensions/sweet-alerts.js')}}"></script>
    @include('admin.shared.deleteAll')
    @include('admin.shared.deleteOne')
    @include('admin.shared.filter_js', ['index_route' => url('admin/delivery-periods')])
@endsection
