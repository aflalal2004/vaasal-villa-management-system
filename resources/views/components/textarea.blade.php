@props(['name', 'label' => null, 'value' => null, 'required' => false, 'help' => null, 'col' => 'f-12', 'rows' => 3, 'id' => null])
@php
    $id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $dot = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div class="field {{ $col }}">
    @if ($label)<label for="{{ $id }}">{{ $label }} @if($required)<span class="req">*</span>@endif</label>@endif
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" @required($required) {{ $attributes->class(['is-invalid' => $errors->has($dot)]) }}>{{ old($dot, $value) }}</textarea>
    @if ($help)<span class="help">{{ $help }}</span>@endif
    @error($dot)<span class="error">{{ $message }}</span>@enderror
</div>
