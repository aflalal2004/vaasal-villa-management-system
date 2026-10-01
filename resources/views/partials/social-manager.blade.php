{{-- Social links manager (shared by Admin → Website → Social media and POS → Social). --}}
<div class="grid" style="grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:16px;align-items:start" data-stack-md>
    <div class="card">
        <div class="card-head"><h2>Links</h2><span class="small muted">{{ $links->where('is_active', true)->count() }} active</span></div>
        @if ($links->isEmpty())
            <x-empty title="No social links yet" icon="share">Add Facebook, Instagram, WhatsApp, YouTube or TikTok on the right.</x-empty>
        @else
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th style="width:44px"></th><th>Platform &amp; URL</th><th>Shown on</th><th style="width:70px">Order</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($links as $l)
                    <tr>
                        <td><span class="social-chip social-{{ $l->platform }}" title="{{ $l->name() }}"><x-icon :name="$l->icon()" /></span></td>
                        <td>
                            <form id="sl-{{ $l->id }}" method="post" action="{{ route($routePrefix.'.update', $l) }}" class="stack" style="gap:6px">
                                @csrf @method('put')
                                <div class="row" style="gap:6px;flex-wrap:wrap">
                                    <select name="platform" aria-label="Platform" style="max-width:150px">@foreach ($platforms as $k => $v)<option value="{{ $k }}" @selected($l->platform === $k)>{{ $v }}</option>@endforeach</select>
                                    <input name="label" value="{{ $l->label }}" placeholder="Label (optional)" aria-label="Label" maxlength="60" style="max-width:170px">
                                </div>
                                <input type="url" name="url" value="{{ $l->url }}" required aria-label="URL">
                                <input type="hidden" name="is_active" value="{{ $l->is_active ? 1 : 0 }}">
                            </form>
                        </td>
                        <td><select name="scope" form="sl-{{ $l->id }}" aria-label="Shown on">@foreach ($scopes as $k => $v)<option value="{{ $k }}" @selected($l->scope === $k)>{{ $v }}</option>@endforeach</select></td>
                        <td><input type="number" name="sort_order" form="sl-{{ $l->id }}" value="{{ $l->sort_order }}" min="0" max="999" aria-label="Sort order" style="width:64px"></td>
                        <td>
                            <form method="post" action="{{ route($routePrefix.'.toggle', $l) }}">@csrf
                                <button class="badge tone-{{ $l->is_active ? 'success' : 'neutral' }}" type="submit" style="border:0;cursor:pointer" title="Click to {{ $l->is_active ? 'disable' : 'enable' }}">{{ $l->is_active ? 'Active' : 'Inactive' }}</button>
                            </form>
                        </td>
                        <td class="nowrap">
                            <button class="btn btn-sm" type="submit" form="sl-{{ $l->id }}"><x-icon name="check" /> Save</button>
                            <a class="btn btn-sm btn-ghost" href="{{ $l->url }}" target="_blank" rel="noopener" aria-label="Open {{ $l->name() }}"><x-icon name="external" /></a>
                            <form method="post" action="{{ route($routePrefix.'.destroy', $l) }}" style="display:inline" data-confirm="Remove the {{ $l->name() }} link?" data-danger>@csrf @method('delete')
                                <button class="btn btn-sm btn-ghost" type="submit" aria-label="Delete"><x-icon name="trash" /></button></form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    <div class="stack">
        <form method="post" action="{{ route($routePrefix.'.store') }}" class="card">
            @csrf
            <div class="card-head"><h2>Add a link</h2></div>
            <div class="card-body form-grid">
                <x-select name="platform" label="Platform" :options="$platforms" col="f-12" required />
                <x-input name="url" type="url" label="URL" col="f-12" required placeholder="https://" help="Must be an https link on the platform's own domain." />
                <x-input name="label" label="Label (optional)" col="f-12" />
                <x-select name="scope" label="Show on" :options="$scopes" col="f-12" required />
            </div>
            <div class="card-foot"><button class="btn btn-primary" type="submit"><x-icon name="plus" /> Add link</button></div>
        </form>
        <div class="card">
            <div class="card-head"><h2>Preview</h2></div>
            <div class="card-body">
                <p class="small muted">How active links appear in the website footer and on receipts.</p>
                <div class="social-preview">
                    @forelse ($links->where('is_active', true) as $l)
                        <a class="social-chip social-{{ $l->platform }}" href="{{ $l->url }}" target="_blank" rel="noopener" aria-label="{{ $l->name() }}"><x-icon :name="$l->icon()" /></a>
                    @empty
                        <span class="small muted">No active links.</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
