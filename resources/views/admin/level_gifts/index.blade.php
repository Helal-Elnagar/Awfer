@extends('admin.layout.master')

@section('content')
<div class="container levels-form py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 m-0">{{ __('admin.levels') }}</h1>
        <button type="submit" form="levelsForm" class="btn btn-success">{{ __('admin.save_changes') }}</button>
    </div>

    <form id="levelsForm" action="{{ route('admin.level_gifts.update') }}" method="POST">
        @csrf
        @method('PUT')
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row fw-bold text-muted mb-2">
                    <div class="col-12 col-md-4 mb-2">{{ __('admin.percentage') }}</div>
                    <div class="col-12 col-md-4 mb-2">{{ __('admin.points_required') }}</div>
                    <div class="col-12 col-md-4 mb-2">{{ __('admin.level_name') }}</div>
                </div>

                @php $previousPoints = null; @endphp
                @foreach($levels as $level)
                <div class="row align-items-center g-2 mb-2 level-row">
                    <div class="col-12 col-md-4">
                        <input type="number"
                               name="levels[{{ $level->id }}][percentage]"
                               value="{{ $level->percentage }}"
                               class="form-control"
                               min="0" max="100" step="0.01"
                               placeholder="0 - 100">
                    </div>
                    <div class="col-12 col-md-4">
                        <input type="number"
                               name="levels[{{ $level->id }}][points_required]"
                               value="{{ $level->points_required }}"
                               class="form-control points-input"
                               min="0" step="1"
                               inputmode="numeric"
                               placeholder="100, 200, 300">
                        <small class="text-danger d-none" data-error="ascending">القيمة يجب أن تكون أكبر من السابقة</small>
                    </div>
                    <div class="col-12 col-md-4">
                        <input type="text"
                               name="levels[{{ $level->id }}][name]"
                               value="{{ $level->name }}"
                               class="form-control"
                               placeholder="lvl1">
                    </div>
                </div>
                @php $previousPoints = $level->points_required; @endphp
                @endforeach
            </div>
        </div>
    </form>
</div>

@push('styles')
<style>
    .levels-form .form-control{border-radius:8px}
    .levels-form .level-row .form-control:focus{box-shadow:0 0 0 .2rem rgba(40,167,69,.25)}
    .levels-form .card{border-radius:12px}
    [dir="rtl"] .levels-form .row .col-12{text-align:right}
</style>
@endpush

@push('scripts')
<script>
    (function(){
        const rows = Array.from(document.querySelectorAll('.level-row'));
        const submitBtn = document.querySelector('button[form="levelsForm"]');
        function validate(){
            let ok = true; let prev = null;
            rows.forEach(row => {
                const pointsEl = row.querySelector('.points-input');
                const errEl = row.querySelector('[data-error="ascending"]');
                const val = Number(pointsEl.value);
                if (prev !== null && !Number.isNaN(val) && val <= prev){
                    ok = false; errEl.classList.remove('d-none');
                } else {
                    errEl.classList.add('d-none');
                }
                prev = Number.isNaN(val) ? prev : val;
            });
            submitBtn.disabled = !ok;
        }
        rows.forEach(row => row.addEventListener('input', validate));
        validate();
    })();
</script>
@endpush
@endsection
