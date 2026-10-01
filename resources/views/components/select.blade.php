@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'help' => null, 'col' => 'f-6', 'id' => null])
@php
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $dot = str_replace(['[', ']'], ['.', ''], $name);
    $current = (string) old($dot, $value);
@endphp
<div class="field {{ $col }}">
    @if ($label)<label for="{{ $id }}">{{ $label }} @if($required)<span class="req">*</span>@endif</label>@endif
    <select name="{{ $name }}" id="{{ $id }}" @required($required) {{ $attributes->class(['is-invalid' => $errors->has($dot)]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected($current !== '' && (string) $key === $current)>{{ $text }}</option>
        @endforeach
    </select>
    @if ($help)<span class="help">{{ $help }}</span>@endif
    @error($dot)<span class="error">{{ $message }}</span>@enderror
</div>
