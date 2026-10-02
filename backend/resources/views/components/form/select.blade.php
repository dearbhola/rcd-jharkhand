@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'placeholder' => '—', 'help' => null, 'col' => 'col-md-6'])
@php($selected = (string) old($name, $value))
<div class="{{ $col }}">
    <label class="form-label {{ $required ? 'required' : '' }}" for="f_{{ $name }}">{{ $label }}</label>
    <select id="f_{{ $name }}" name="{{ $name }}" @required($required) {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
        @if ($placeholder !== false)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($selected === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
