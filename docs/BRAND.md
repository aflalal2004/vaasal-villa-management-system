# Vaasal Villa — Logo, Brand & Website Animation Guidelines

Requirement IDs: **WEB-18** (website animation & logo creation) and spec §9 UI/UX (animation guidelines, website animations, logo usage).

## Official logo (L1.png) — use this everywhere

The official artwork is `L1.png` (gold emblem with key-hole door, VAASAL VILLA wordmark), copied unchanged to `public/assets/brand/L1.png`.
Optimised copies are generated from it and are the only files the app references:

| File | Use |
|---|---|
| `vaasal-logo-{96,192,320,640}.{webp,png}` | Full logo (emblem + wordmark): website header/footer, sign-in, letterheads, invoices, emails, receipts |
| `vaasal-emblem-{32,64,128,180,256}.{webp,png}` | Emblem only (wordmark cropped, not redrawn): sidebar, POS bar, favicon, touch icon |

Rules: never stretch or recolour (the components render a fixed 1:1 box with `object-fit: contain`); on dark surfaces place it on the white
rounded tile (`<x-logo-mark>` / `<x-brand-logo :tile="true">`); keep at least 8 px clear space. The earlier SVG arch mark below is retired from the UI.

## 1. The logo

*Vaasal* (வாசல்) means doorway or threshold. The mark is an **arched doorway** that frames a **rising sun** over **sea waves**, standing on a **brass threshold line**. It says "your door to the southern coast" without using a stock palm or wave icon.

| Element | Geometry (64 × 64 grid) | Colour |
|---|---|---|
| Tile | `rect 64×64, rx 15` | Lagoon `#0E6B63` |
| Arch | `M21 49V29a11 11 0 0 1 22 0v20`, stroke 3.6, round caps | White `#FFFFFF` |
| Sun | circle (32, 31) r 4.2 | Sand-brass `#E9C98A` |
| Sea | `M24.5 41.5q1.9-1.7 3.8 0t3.8 0 3.8 0 3.8 0`, stroke 2.4 | White |
| Threshold | `M14 50h36`, stroke 3.6 | Sand-brass `#E9C98A` |

Wordmark: **"Vaasal Villa"** set in *Source Serif 4* Semibold. Descriptor: **"JAFFNA · SRI LANKA"** set in *IBM Plex Sans* Medium, uppercase, letter-spacing 0.18em, in brass `#A67C2E`.

### Files (`public/assets/brand/`)

| File | Use |
|---|---|
| `vaasal-mark.svg` | Primary mark: app icon, favicon, social avatar, sidebar |
| `vaasal-mark-mono.svg` | One colour (`currentColor`): embossing, key-card print, watermarks, fax/thermal print |
| `vaasal-logo-horizontal.svg` | Mark + wordmark on light backgrounds (letterheads, OTA listings) |
| `vaasal-logo-horizontal-reverse.svg` | Mark + wordmark on dark backgrounds or photos (with a scrim) |
| `public/favicon.svg` | Browser favicon (a copy of the primary mark) |

In Blade, always use the component. Never paste the SVG inline.

```blade
<x-logo-mark :size="40" />                      {{-- full colour --}}
<x-logo-mark :size="24" mono class="muted" />   {{-- one colour, inherits text colour --}}
<x-logo-mark :size="42" class="logo-anim" />    {{-- website header/footer: animated intro --}}
```

### Integration points

| Surface | Where | Size |
|---|---|---|
| Website header and footer | `layouts/site.blade.php` | 42 px, animated |
| Admin and partner sidebar | `layouts/admin.blade.php`, `layouts/operator.blade.php` | 36 px |
| POS top bar | `layouts/pos.blade.php` | 32 px |
| Sign-in art panel | `layouts/auth.blade.php` | 44 px |
| Invoices, folios, confirmations, statements | `print/partials/letterhead.blade.php` | 48 px |
| Error pages (403/404/419/500) | `errors/layout.blade.php` | 64 px |
| Favicon, `theme-color` | `favicon.svg`, `<meta name="theme-color" content="#0E6B63">` | — |
| Emails | Use a hosted **PNG** export of the mark, because many mail clients block SVG. | 48 px |

## 2. Clear space & minimum size

- **Clear space** is the height of the arch opening (¼ of the mark), kept free on every side. Nothing may enter it: no text, no edges, no other logos.
- **Minimum sizes:**
  - mark: **16 px** on screen or **6 mm** in print;
  - horizontal lockup: **120 px** wide on screen or **35 mm** in print.
- Below 24 px, drop the descriptor line and use the mark alone.

## 3. Colour variants

| Background | Use |
|---|---|
| White or light ground (`#F3F5F2`) | Full-colour mark + horizontal logo |
| Lagoon or dark ground (`#0B1513`) | Full-colour mark (the tile carries its own ground) + reverse logo |
| Photography | Reverse logo, only over a scrim of at least 45 % (`--scrim`) |
| Single-colour print, embossing, laser-etched key cards | Mono mark in black, white, or brass foil |

## 4. Misuse (do not)

- Stretch, skew, rotate or re-draw the mark, or change stroke weights.
- Recolour the tile. Lagoon is fixed. The mono version is the only alternative.
- Put the full-colour logo on busy photos without a scrim, or on the brass colour.
- Add drop shadows, glows, gradients or outlines.
- Set the wordmark in any typeface other than Source Serif 4, or move it relative to the mark.
- Animate the logo anywhere except the website header and footer intro described in §5.

## 5. Animation principles

The motion follows the property: calm water, slow light. It should feel **unhurried, purposeful and quiet**.

1. **Purpose first.** Motion must explain something: a change of state, where content came from, or what is loading. Remove any animation that does not do one of these.
2. **Durations.** Use 150–250 ms for UI feedback (hover, press, toggles), 300–400 ms for panels and modals, and 600–900 ms for reveal-on-scroll. Only ambient hero motion may be longer.
3. **Easing.** Use `ease-out` or `cubic-bezier(.2,.8,.2,1)` for things entering, and `ease-in` for things leaving. Nothing should bounce or overshoot.
4. **Cheap properties only.** Animate `transform`, `opacity` and SVG `stroke-dashoffset`. Never animate width, height, top/left or box-shadow on large areas.
5. **Content is visible at rest.** Pages must never depend on JavaScript to become readable:
   - reveal effects only hide content after JS confirms that `IntersectionObserver` works (the `.js-reveal` class);
   - counters render their final value in the HTML.
6. **Respect reduced motion.** Under `prefers-reduced-motion: reduce`:
   - all animations and transitions are disabled;
   - sliders stop auto-advancing;
   - counters show their final value;
   - reveals are shown immediately.

   Both `site.css` and `app.css` enforce this globally.
7. **One thing at a time.** Stagger siblings by 100 ms at most (`.d1`–`.d3`). Never run more than one ambient animation in the viewport.
8. **The back office stays still.** Staff screens (admin, POS, KDS) use only feedback transitions under 250 ms. Speed matters more than delight there.

## 6. Website animation catalogue

| Animation | Where | Spec | Implementation |
|---|---|---|---|
| Logo intro | Header and footer logo | Threshold sweeps (0.6 s), arch draws (0.9 s), sun rises (0.7 s), sea settles (0.6 s); about 1.4 s in total, once per page load. On hover the sun lifts 1.5 px. | `.logo-anim` in `site.css`, `pathLength="100"` in `components/logo-mark` |
| Page loading bar | Top of every page | 3 px brass bar, 0→100 % over 1 s, then fades | `.page-loader` |
| Hero Ken Burns crossfade | Home hero | 1.4 s crossfade and 9 s scale 1.08→1, advancing every 7 s | `.hero-slides`, `site.js` |
| Transparent-to-solid header | All pages | Switches to a solid background after 40 px of scroll | `.site-header.solid` |
| Scroll reveal | Section blocks | Fade in and rise 26 px over 0.8 s, staggered 0.1–0.3 s, threshold 12 % | `.reveal`, `.d1`–`.d3` |
| Animated counters | "At a glance" stats | Count up with ease-out over about 1.6 s when 60 % visible | `[data-count]` |
| Card hover | Villa and offer cards | Image scales 1.06 over 0.8 s; card lifts 6 px | `.villa-card` |
| Lightbox | Gallery and villa photos | Fade 200 ms; keyboard ←/→/Esc; focus returns to the trigger | `[data-lightbox]` |
| Testimonials | Home | Crossfade every 8 s; pauses on reduced motion | `site.js` |
| YouTube facade | Video sections | Thumbnail plus play button; the iframe loads only on click | `[data-yt]` |
| Floating WhatsApp | All pages | Scales to 1.08 on hover | `.wa-float` |
| Theme switch | Header toggle | Colour transition of 200 ms on surfaces | `data-theme-toggle` |

## 7. Integration requirements (for any new page or vendor)

- New public pages extend `layouts.site`, so they inherit the logo, loader, reveal, theme and reduced-motion handling automatically.
- Mark animated sections with `class="reveal"`. Do not add custom observers.
- Third-party embeds (maps, video, booking widgets) must be lazy-loaded or placed behind a facade. They must not add their own entrance animations.
- Hero imagery must be 1600–2400 px wide and compressed to under 350 KB (WebP or AVIF preferred), and a still first frame must be acceptable.
- OTA and social profiles use `vaasal-mark.svg`, exported as a 1024 px PNG, as the avatar, and `vaasal-logo-horizontal` for banners.
- Any new motion must be added to the §6 catalogue with its duration, and must degrade under reduced motion.
