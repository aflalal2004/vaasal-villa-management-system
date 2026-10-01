@props(['name', 'label', 'checked' => false, 'col' => 'f-6', 'value' => 1])
@php $dot = str_replace(['[', ']'], ['.', ''], $name); @endphp
<div class="field {{ $col }}" style="align-content:end">
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="check"><input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($dot, $checked)) {{ $attributes }}> {{ $label }}</label>
</div>
