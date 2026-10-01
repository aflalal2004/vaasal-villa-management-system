<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SocialLink;
use App\Modules\Core\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Social media links. One table serves two screens:
 *  - Admin → Website → Social media (website.manage): every link;
 *  - POS → Social (pos.menu): the restaurant's links (scope restaurant / both).
 * The website header/footer and POS receipts read SocialLink::for() — nothing is hardcoded.
 */
class SocialLinkController extends Controller
{
    public function index(Request $request)
    {
        $pos = $this->isPos($request);
        $links = SocialLink::when($pos, fn ($q) => $q->whereIn('scope', ['restaurant', 'both']))->orderBy('sort_order')->orderBy('id')->get();
        return view($pos ? 'pos.social' : 'admin.cms.social', [
            'links' => $links,
            'platforms' => collect(SocialLink::PLATFORMS)->map(fn ($p) => $p[0])->all(),
            'scopes' => $pos ? array_intersect_key(SocialLink::SCOPES, ['restaurant' => 1, 'both' => 1]) : SocialLink::SCOPES,
            'routePrefix' => $pos ? 'pos.social' : 'admin.social',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $link = SocialLink::create($data + ['sort_order' => (int) SocialLink::max('sort_order') + 1]);
        AuditService::log('website', 'social_link_added', $link, $link->name().' '.$link->url);
        return back()->with('success', $link->name().' link added.');
    }

    public function update(Request $request, SocialLink $link)
    {
        $this->guardScope($request, $link);
        $link->update($this->validated($request, $link));
        AuditService::log('website', 'social_link_updated', $link, $link->name().' '.$link->url);
        return back()->with('success', $link->name().' link saved.');
    }

    public function toggle(Request $request, SocialLink $link)
    {
        $this->guardScope($request, $link);
        $link->update(['is_active' => ! $link->is_active]);
        AuditService::log('website', 'social_link_toggled', $link, $link->name().' '.($link->is_active ? 'enabled' : 'disabled'));
        return back()->with('success', $link->name().' '.($link->is_active ? 'enabled' : 'disabled').'.');
    }

    public function destroy(Request $request, SocialLink $link)
    {
        $this->guardScope($request, $link);
        $link->delete();
        AuditService::log('website', 'social_link_deleted', null, $link->name().' '.$link->url);
        return back()->with('success', $link->name().' link removed.');
    }

    private function validated(Request $request, ?SocialLink $link = null): array
    {
        $scopes = $this->isPos($request) ? ['restaurant', 'both'] : array_keys(SocialLink::SCOPES);
        $data = $request->validate([
            'platform' => ['required', Rule::in(array_keys(SocialLink::PLATFORMS))],
            'label' => ['nullable', 'string', 'max:60'],
            'url' => ['required', 'url:https', 'max:255'],
            'scope' => ['required', Rule::in($scopes)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        // The URL must belong to the chosen platform, so a footer icon never points somewhere unexpected.
        $host = strtolower(preg_replace('/^www\./', '', (string) parse_url($data['url'], PHP_URL_HOST)));
        $allowed = SocialLink::PLATFORMS[$data['platform']][2];
        if (! collect($allowed)->contains(fn ($h) => $host === $h || str_ends_with($host, '.'.$h))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['url' => 'This URL is not a '.SocialLink::PLATFORMS[$data['platform']][0].' address ('.implode(', ', $allowed).').']);
        }
        $data['is_active'] = $request->boolean('is_active', $link?->is_active ?? true);
        $data['sort_order'] ??= $link?->sort_order ?? 0;
        return $data;
    }

    private function isPos(Request $request): bool
    {
        return $request->routeIs('pos.*');
    }

    /** POS users may only change restaurant-facing links. */
    private function guardScope(Request $request, SocialLink $link): void
    {
        abort_if($this->isPos($request) && ! in_array($link->scope, ['restaurant', 'both'], true), 403);
    }
}
