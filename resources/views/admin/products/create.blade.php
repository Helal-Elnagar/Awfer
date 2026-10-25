@extends('admin.layout.master')
@section('css')
    <link rel="stylesheet" type="text/css"
        href="{{ asset('admin/app-assets/css-rtl/plugins/forms/validation/form-validation.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin/app-assets/vendors/css/extensions/sweetalert2.min.css') }}">
    <style>
        .btn {
            margin-top: -2px;
        }

        .btn-block {
            width: fit-content;
        }
    </style>
@endsection

@section('content')
    @if (session()->has('errors'))
        <div class="alert alert-danger">
            <ul>
                @foreach (session()->get('errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section id="multiple-column-form">
        <div class="row match-height">
            <div class="col-12">
                <div class="card">
                    <div class="card-content">
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.products.store') }}" class="store form-horizontal"
                                enctype="multipart/form-data" novalidate>
                                @csrf
                                <div class="form-body">
                                    <div class="row">
                                        <!-- Images Upload -->
                                        <div class="col-12">
                                            <div class="imgMontg col-12 text-center">
                                                <div class="dropBox">
                                                    <div class="textCenter">
                                                        <div class="imagesUploadBlock">
                                                            <label class="uploadImg">
                                                                <span><i class="feather icon-image"></i></span>
                                                                <input type="file" accept="image/*" name="images[]"
                                                                    class="imageUploader" multiple>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Product Name -->
                                        <div class="col-md-6 col-12">
                                            <div class="form-group">
                                                <label>{{ __('admin.product_name') }} (AR)<span
                                                        class="text-danger">*</span></label>
                                                <div class="controls">
                                                    <input type="text" name="name[ar]" class="form-control"
                                                        placeholder="{{ __('admin.product_name') }} (AR)" required
                                                        data-validation-required-message="{{ __('admin.this_field_is_required') }}">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6 col-12">
                                            <div class="form-group">
                                                <label>{{ __('admin.product_name') }} (EN)</label>
                                                <div class="controls">
                                                    <input type="text" name="name[en]" class="form-control"
                                                        placeholder="{{ __('admin.product_name') }} (EN)">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Product Description -->
                                        <div class="col-md-6 col-12">
                                            <div class="form-group">
                                                <label>{{ __('admin.product_description') }} (AR)</label>
                                                <div class="controls">
                                                    <textarea name="description[ar]" class="form-control" placeholder="{{ __('admin.product_description') }} (AR)"
                                                        rows="6"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6 col-12">
                                            <div class="form-group">
                                                <label>{{ __('admin.product_description') }} (EN)</label>
                                                <div class="controls">
                                                    <textarea name="description[en]" class="form-control" placeholder="{{ __('admin.product_description') }} (EN)"
                                                        rows="6"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Brand -->
                                        <div class="col-md-6 col-12">
                                            <div class="form-group">
                                                <label for="brand-column">{{ __('admin.brands') }} <span
                                                        class="text-danger">*</span></label>
                                                <div class="controls">
                                                    <select name="brand_id" class="select2 form-control" required
                                                        data-validation-required-message="{{ __('admin.this_field_is_required') }}">
                                                        <option value="">{{ __('admin.brands') }}</option>
                                                        @foreach ($brands as $brand)
                                                            <option value="{{ $brand->id }}">{{ $brand->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Parent Category -->
                                        <div class="col-md-6 col-12">
                                            <div class="form-group">
                                                <label for="parent-category-column">{{ __('admin.parent_category') }} <span
                                                        class="text-danger">*</span></label>
                                                <div class="controls">
                                                    <select id="parent_category_id" name="parent_category_id"
                                                        class="select2 form-control" required
                                                        data-validation-required-message="{{ __('admin.this_field_is_required') }}">
                                                        <option value="">{{ __('admin.select_parent_category') }}
                                                        </option>
                                                        @foreach ($categories as $cat)
                                                            @if ($cat->parent_id === null)
                                                                <option value="{{ $cat->id }}">{{ $cat->name }}
                                                                </option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>


                                        <!-- Base Price -->
                                        <div class="col-md-6 col-12">
                                            <div class="form-group">
                                                <label for="base-price-column">{{ __('admin.base_price') }} <span
                                                        class="text-danger">*</span></label>
                                                <div class="controls">
                                                    <input type="number" step="0.01" name="base_price"
                                                        class="form-control" placeholder="{{ __('admin.base_price') }}"
                                                        required
                                                        data-validation-required-message="{{ __('admin.this_field_is_required') }}">
                                                </div>
                                            </div>
                                        </div>

                                        

                                        <!-- Final Price (Selling Price) -->
                                        <div class="col-md-6 col-12">
                                            <div class="form-group">
                                                <label for="final-price-column">{{ __('admin.final_price') }} <span
                                                        class="text-danger">*</span></label>
                                                <div class="controls">
                                                    <input type="number" step="0.01" min="0"
                                                        name="final_price" id="final_price" class="form-control"
                                                        placeholder="{{ __('admin.final_price') }}" required
                                                        data-validation-required-message="{{ __('admin.this_field_is_required') }}">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Hidden Discount Percentage (auto-calculated) -->
                                        <input type="hidden" name="discount_percentage" id="discount_percentage"
                                            value="0">

                                        <!-- Quantity -->
                                        <div class="col-md-6 col-12">
                                            <div class="form-group">
                                                <label for="quantity-column">{{ __('admin.quantity') }} <span
                                                        class="text-danger">*</span></label>
                                                <div class="controls">
                                                    <input type="number" min="0" name="quantity"
                                                        class="form-control" placeholder="{{ __('admin.quantity') }}"
                                                        required
                                                        data-validation-required-message="{{ __('admin.this_field_is_required') }}">
                                                </div>
                                            </div>
                                        </div>



                                        <!-- Status -->
                                        <div class="col-md-6 col-12">
                                            <div class="form-group">
                                                <label for="is-active-column">{{ __('admin.status') }}</label>
                                                <div class="controls">
                                                    <select name="is_active" class="select2 form-control">
                                                        <option value="1" selected>{{ __('admin.active') }}</option>
                                                        <option value="0">{{ __('admin.inactive') }}</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Submit Buttons -->
                                        <div class="col-12 d-flex justify-content-center mt-3">
                                            <button type="submit"
                                                class="btn btn-primary mr-1 mb-1 submit_button">{{ __('admin.add') }}</button>
                                            <a href="{{ url()->previous() }}" type="reset"
                                                class="btn btn-outline-warning mr-1 mb-1">{{ __('admin.back') }}</a>
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
    <script src="{{ asset('admin/app-assets/vendors/js/forms/validation/jqBootstrapValidation.js') }}"></script>
    <script src="{{ asset('admin/app-assets/js/scripts/forms/validation/form-validation.js') }}"></script>
    <script src="{{ asset('admin/app-assets/vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('admin/app-assets/js/scripts/extensions/sweet-alerts.js') }}"></script>

    @include('admin.shared.addImage')
    @include('admin.shared.submitAddForm')
    @include('admin.shared.categoryDependentJs')

    <script>
        function calculateDiscount() {
            const basePrice = parseFloat(document.querySelector('input[name="base_price"]').value) || 0;
            const finalPrice = parseFloat(document.getElementById('final_price').value) || 0;
            const discountInput = document.getElementById('discount_percentage');

            if (basePrice > 0) {
                const discount = ((basePrice - finalPrice) / basePrice) * 100;
                discountInput.value = Math.max(0, Math.round(discount * 100) / 100);
            } else {
                discountInput.value = 0;
            }
        }

        document.querySelector('input[name="base_price"]').addEventListener('input', calculateDiscount);
        document.getElementById('final_price').addEventListener('input', calculateDiscount);
    </script>
@endsection
