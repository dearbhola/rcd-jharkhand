@props(['name', 'label', 'value' => null, 'required' => false, 'rows' => 3, 'col' => 'col-12', 'help' => null])
<div class="{{ $col }}">
    <label class="form-label {{ $required ? 'required' : '' }}" for="f_{{ $name }}">{{ $label }}</label>
    <textarea id="f_{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
        {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
