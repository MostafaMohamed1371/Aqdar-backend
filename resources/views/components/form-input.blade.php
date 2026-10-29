@props([
    'name',
    'label',
    'type' => 'text',
    'required' => false,
    'placeholder' => '',
    'autofocus' => false,
    'value' => null,
])

@php
    $hasError = $errors->has($name);
    $inputValue = $value ?? old($name);
@endphp

<div {{ $attributes->class(['field']) }}>
    <label for="{{ $name }}">
        {{ $label }}
        @if ($required)
            <span style="color:var(--bad)">*</span>
        @endif
    </label>

    <div class="inp{{ $hasError ? ' is-bad' : '' }}">
        <input
            id="{{ $name }}"
            type="{{ $type }}"
            name="{{ $name }}"
            value="{{ $inputValue }}"
            placeholder="{{ $placeholder }}"
            @if ($required) required @endif
            @if ($autofocus) autofocus @endif
        />
    </div>

    @error($name)
        <p class="hint" style="color:var(--bad)">{{ $message }}</p>
    @enderror
</div>
