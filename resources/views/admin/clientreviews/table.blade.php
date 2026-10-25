<div class="position-relative">
    {{-- table loader  --}}
    {{-- <div class="table_loader" >
        {{__('admin.loading')}}
    </div> --}}
    {{-- table loader  --}}
    
    {{-- table content --}}
    <table class="table " id="tab">
        <thead>
            <tr>
                <th>
                    <label class="container-checkbox">
                        <input type="checkbox" value="value1" name="name1" id="checkedAll">
                        <span class="checkmark"></span>
                    </label>
                </th>
                <th>{{__('admin.image')}}</th>
                <th>{{__('admin.name')}}</th>
              
                <th>{{__('admin.control')}}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($clientreviews as $clientreview)
                <tr class="delete_row">
                    <td class="text-center">
                        <label class="container-checkbox">
                        <input type="checkbox" class="checkSingle" id="{{ $clientreview->id }}">
                        <span class="checkmark"></span>
                        </label>
                    </td>
                    <td><img src="{{$clientreview->image}}" width="30px" height="30px" alt=""></td>
                    <td>{{ $clientreview->name }}</td>
             
                    
                    <td class="product-action"> 
                        <span class="action-edit text-primary"><a href="{{ route('admin.clientreviews.edit', ['id' => $clientreview->id]) }}" class="btn btn-primary btn-sm"><i class="feather icon-edit"></i>{{ __('admin.edit') }}</a></span>
                        <span class="delete-row btn btn-danger btn-sm" data-url="{{ url('admin/clientreviews/' . $clientreview->id) }}"><i class="feather icon-trash"></i>{{ __('admin.delete') }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{-- table content --}}
    {{-- no data found div --}}
    @if ($clientreviews->count() == 0)
        <div class="d-flex flex-column w-100 align-center mt-4">
            <img src="{{asset('admin/app-assets/images/pages/404.png')}}" alt="">
            <span class="mt-2" style="font-family: cairo">{{__('admin.there_are_no_matches_matching')}}</span>
        </div>
    @endif
    {{-- no data found div --}}

</div>
{{-- pagination  links div --}}
@if ($clientreviews->count() > 0 && $clientreviews instanceof \Illuminate\Pagination\AbstractPaginator )
    <div class="d-flex justify-content-center mt-3">
        {{$clientreviews->links()}}
    </div>
@endif
{{-- pagination  links div --}}

