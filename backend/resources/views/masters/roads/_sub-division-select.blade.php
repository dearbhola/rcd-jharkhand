{{-- Sub-division select filtered by the division select (#f_division_id). --}}
@php($selectedSub = (string) old('sub_division_id', $value ?? ''))
<div class="{{ $col ?? 'col-md-6' }}">
    <label class="form-label" for="f_sub_division_id">Sub-division</label>
    <select id="f_sub_division_id" name="sub_division_id" data-depends-on="#f_division_id" class="form-select @error('sub_division_id') is-invalid @enderror">
        <option value="">—</option>
        @foreach ($subDivisions as $sub)
            <option value="{{ $sub->id }}" data-parent="{{ $sub->division_id }}" @selected($selectedSub === (string) $sub->id)>{{ $sub->name }}</option>
        @endforeach
    </select>
    @error('sub_division_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
