@props(['id', 'title', 'wide' => false])
<div class="modal" id="{{ $id }}" hidden>
    <div class="modal-box {{ $wide ? 'wide' : '' }}" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
        <div class="modal-head">
            <h2 id="{{ $id }}-title">{{ $title }}</h2>
            <button type="button" class="icon-btn" data-modal-close aria-label="Close"><x-icon name="x" /></button>
        </div>
        {{ $slot }}
    </div>
</div>
