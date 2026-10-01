@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'help' => null, 'col' => 'f-6', 'id' => null])
@php
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $dot = str_replace(['[', ']'], ['.', ''], $name);
    $val = $type === 'password' ? null : old($dot, $value);
    if ($val instanceof \Carbon\CarbonInterface) {
        $val = $type === 'datetime-local' ? $val->format('Y-m-d\TH:i') : ($type === 'time' ? $val->format('H:i') : $val->format('Y-m-d'));
    }
@endphp
<div class="field {{ $col }}">
    @if ($label)<label for="{{ $id }}">{{ $label }} @if($required)<span class="req">*</span>@endif</label>@endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $val }}" @required($required)
        {{ $attributes->class(['is-invalid' => $errors->has($dot)]) }}>
    @if ($help)<span class="help">{{ $help }}</span>@endif
    @error($dot)<span class="error">{{ $message }}</span>@enderror
</div>
