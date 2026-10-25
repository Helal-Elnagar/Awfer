@extends('admin.layout.master')
{{-- extra css files --}}
@section('css')
    <link rel="stylesheet" type="text/css" href="{{asset('admin/app-assets/css-rtl/plugins/forms/validation/form-validation.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('admin/app-assets/vendors/css/extensions/sweetalert2.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('admin/app-assets/css-rtl/plugins/extensions/icons.css')}}">
        <link rel="stylesheet" href="{{ asset('assets/style/all.min.css') }}">

@endsection
{{-- extra css files --}}

@section('content')
<!-- // Basic multiple Column Form section start -->
<section id="multiple-column-form">
    <div class="row match-height">
        <div class="col-12">
            <div class="card">
                {{-- <div class="card-header">
                    <h4 class="card-title">{{__('admin.edit')}}</h4>
                </div> --}}
                <div class="card-content">
                    <div class="card-body">
                        <form  method="POST" action="{{route('admin.introservices.update' , ['id' => $service->id])}}" class="store form-horizontal" novalidate>
                            @csrf
                            @method('PUT')
                            <div class="form-body">
                                <div class="row">
                                   
                                    @foreach (languages() as $lang)
                                        <div class="col-6">
                                            <div class="form-group">
                                                <div class="controls">
                                                    <label for="account-name">{{__('site.title_'.$lang)}} </label>
                                                    <textarea class="form-control" name="title[{{$lang}}]" id="" cols="30" rows="10" placeholder="{{__('site.write') . __('site.title_'.$lang)}}">{{$service->getTranslations('title')[$lang]}}</textarea>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                         <!-- Icon Picker -->
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label for="icon">{{ __('site.icon') }}</label>
                                            <div class="input-group">
<input 
    type="text" 
    class="form-control" 
    name="icon" 
    id="icon-input" 
    placeholder="fa-light fa-star" 
    readonly 
    value="{{ old('icon', $service->icon ?? '') }}">
                                                <span class="input-group-append">
                                                    <button class="btn btn-secondary" type="button" id="icon-picker-btn"><i class="fa fa-icons"></i></button>
                                                </span>
                                            </div>
                                            <div id="icon-picker-modal" style="display:none; position:absolute; z-index:9999; background:#fff; border:1px light #ccc; max-height:300px; overflow:auto; width:350px; padding:10px;">
                                                <input type="text" id="icon-search" class="form-control mb-2" placeholder="Search icons...">
                                                <div id="icon-list" style="display:flex; flex-wrap:wrap; gap:10px;"></div>
                                            </div>
                                        </div>
                                    </div>

                                  
                                    <div class="col-12 d-flex justify-content-center mt-3">
                                        <button type="submit" class="btn btn-primary mr-1 mb-1 submit_button">{{__('admin.update')}}</button>
                                        <a href="{{ url()->previous() }}" type="reset" class="btn btn-outline-warning mr-1 mb-1">{{__('admin.back')}}</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
@section('js')
    <script src="{{asset('admin/app-assets/vendors/js/forms/validation/jqBootstrapValidation.js')}}"></script>
    <script src="{{asset('admin/app-assets/js/scripts/forms/validation/form-validation.js')}}"></script>
    <script src="{{asset('admin/app-assets/vendors/js/extensions/sweetalert2.all.min.js')}}"></script>
    <script src="{{asset('admin/app-assets/js/scripts/extensions/sweet-alerts.js')}}"></script>
    
      <script>
        // Minimal icon list for demo, replace with full FontAwesome icon set as needed
        const icons = [
            'fa-light fa-star', 'fa-light fa-heart', 'fa-light fa-user', 'fa-light fa-bell', 'fa-light fa-cog',
            'fa-light fa-check', 'fa-light fa-times', 'fa-light fa-plus', 'fa-light fa-minus', 'fa-light fa-edit',
            'fa-light fa-trash', 'fa-light fa-search', 'fa-light fa-envelope', 'fa-light fa-phone', 'fa-light fa-globe',
            'fa-light fa-home', 'fa-light fa-lock', 'fa-light fa-unlock', 'fa-light fa-camera', 'fa-light fa-image',
            'fa-light fa-calendar', 'fa-light fa-map', 'fa-light fa-car', 'fa-light fa-gift', 'fa-light fa-thumbs-up', 'fa-light fa-comments icon','fa-light fa-user-plus icon'
            
        ];
        const iconPickerBtn = document.getElementById('icon-picker-btn');
        const iconPickerModal = document.getElementById('icon-picker-modal');
        const iconList = document.getElementById('icon-list');
        const iconInput = document.getElementById('icon-input');
        const iconSearch = document.getElementById('icon-search');

        iconPickerBtn.addEventListener('click', function(e) {
            iconPickerModal.style.display = iconPickerModal.style.display === 'block' ? 'none' : 'block';
            iconSearch.value = '';
            renderIcons(icons);
        });
        document.addEventListener('click', function(e) {
            if (!iconPickerModal.contains(e.target) && e.target !== iconPickerBtn) {
                iconPickerModal.style.display = 'none';
            }
        });
        function renderIcons(list) {
            iconList.innerHTML = '';
            list.forEach(icon => {
                const i = document.createElement('i');
                i.className = icon + ' fa-2x';
                i.style.cursor = 'pointer';
                i.title = icon;
                i.onclick = function() {
                    iconInput.value = icon;
                    iconPickerModal.style.display = 'none';
                };
                iconList.appendChild(i);
            });
        }
        iconSearch.addEventListener('input', function() {
            const val = this.value.toLowerCase();
            renderIcons(icons.filter(icon => icon.includes(val)));
        });
    </script>

    {{-- show selected image script --}}
    @include('admin.shared.addImage')
    {{-- show selected image script --}}

    {{-- submit edit form script --}}
        @include('admin.shared.submitEditForm')
    {{-- submit edit form script --}}
    
@endsection