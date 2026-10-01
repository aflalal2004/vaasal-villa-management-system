<script>(function(){var b=document.body,k='vv-sidebar-'+(b.dataset.sidebarDefault==='min'?'fs':'std'),v=null;try{v=localStorage.getItem(k);}catch(e){}
  if(v==='min'||(v===null&&b.dataset.sidebarDefault==='min'))document.documentElement.classList.add('sidebar-min');})();</script>
{{-- Console sidebar shared by the Hotel PMS (admin) and the Restaurant POS. $nav from a Navigation class; $sub = console name. --}}
<aside class="sidebar" aria-label="Main navigation">
    <div class="brand">
        <a href="{{ $home }}" class="brand-link" aria-label="Vaasal Villa home">
            <x-logo-mark :size="38" />
            <span class="brand-text"><span class="brand-name">Vaasal Villa</span><span class="brand-sub">{{ $sub }}</span></span>
        </a>
        <button class="icon-btn sidebar-collapse" type="button" data-sidebar-collapse aria-label="Collapse menu" title="Collapse / expand menu"><x-icon name="menu" /></button>
    </div>
    <nav class="nav">
        @foreach ($nav as $group => $items)
            <div class="nav-group">{{ $group }}</div>
            @foreach ($items as $item)
                <a href="{{ $item['url'] }}" @class(['active' => $item['active']]) @if($item['active']) aria-current="page" @endif title="{{ $item['label'] }}">
                    <x-icon :name="$item['icon']" /> <span class="nav-label">{{ $item['label'] }}</span>
                    @if ($item['route'] === 'admin.conflicts.index' && ($c = \App\Models\BookingConflict::where('status', 'open')->count()))<span class="count">{{ $c }}</span>@endif
                </a>
            @endforeach
        @endforeach
    </nav>
    <form method="post" action="{{ route('logout') }}" class="nav-logout">@csrf
        <button type="submit" title="Log out"><x-icon name="logout" /> <span class="nav-label">Log out</span></button>
    </form>
</aside>
