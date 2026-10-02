@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'help' => null, 'col' => 'col-md-6', 'errorKey' => null])
@php($key = $errorKey ?? $name)
<div class="{{ $col }}">
    <label class="form-label {{ $required ? 'required' : '' }}" for="f_{{ $name }}">{{ $label }}</label>
    <input id="f_{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required)
        {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($key)]) }}>
    @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
