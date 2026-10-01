# Vaasal Villa — QA audit and Tamil training package: final summary

**Scope:** end-to-end QA of the Hotel PMS + Restaurant POS on the local XAMPP installation, plus a Tamil user manual and a Tamil training-video package.
**Date:** 29–30 September 2026 (Asia/Colombo).
**Credentials:** none are printed anywhere in this package. The demo password is in `README.md` only.

## Results

| Layer | Cases | PASS | BLOCKED | FAIL |
|---|---:|---:|---:|---:|
| QA harness `tests/QA/QaAuditTest.php` (new) | 155 | 153 | 2 | 0 |
| Live HTTP checks against Apache | 20 | 19 | 1 | 0 |
| Browser walkthrough (headless Chrome, real UI) | 82 | 82 | 0 | 0 |
| **Total in TEST_CASES** | **257** | **254** | **3** | **0** |
| Existing regression suite (`php artisan test`) | 51 | 51 (1,668 assertions) | – | 0 |

The three BLOCKED cases are live OTA (no Channex credentials), physical RFID/lock hardware (simulator only) and the hero video (not configured).

## Defects fixed during the audit

| ID | Severity | Fix | File(s) |
|---|---|---|---|
| D-01 | High | Timezone hard-coded to UTC ignored `APP_TIMEZONE=Asia/Colombo`; "today" was wrong from 00:00 to 05:30 and PHP ran 5 h 30 behind MariaDB | `config/app.php` |
| D-02 | High | Restaurant staff could open the hotel Reports page | `routes/admin.php` |
| D-03 | High | The POS showed a raw "Unauthenticated" message; it now shows a clear message, redirects to sign-in and returns to the same page | `public/assets/js/app.js`, `layouts/partials/head.blade.php`, `LoginController.php` |
| D-04 | Medium | A payment could be taken on an empty check | `PosBillingService.php` |
| D-05 | Low | The Restaurant sales page defaulted to a report the role might not be allowed to see (403) | `SalesReportController.php` |
| D-06 | Low | The night-audit stay-over count included existing tasks | `HousekeepingService.php` |
| D-07 | Low | Website weather used emoji instead of SVG icons | `WeatherService.php`, `site.js`, `site.css` |
| D-08 | High (env) | Intermittent HTTP 500s under concurrent requests (XAMPP thread-safe Apache + `.env` loading). Mitigation: `php artisan config:cache`; no code change | – |

## Open issues (not changed — see QA report §9)

- **O-01 High:** the Laravel scheduler is not registered in Windows Task Scheduler, so the automatic night audit (02:00), website-hold expiry and key-card expiry never run.
- **O-02 High:** run `config:cache` on the server, and `config:clear` before running tests.
- **Medium:**
  - O-03: the night audit at 02:00 posts the coming night early.
  - O-04: no audit-log entry or business-date lock per night-audit run.
  - O-05: the booking-form summary ignores a rate override.
  - O-06: the second-outlet shift form is hidden.
- **Low:**
  - O-07: weather failures are cached for 20 minutes.
  - O-08: the stay-over task stays open after checkout.
  - O-09: the folio balance is clipped at the right edge.
  - O-10: KPI values wrap onto two lines.
  - O-11: business-rule exceptions are logged at ERROR level.
  - O-12: print pages have no favicon (404).
  - O-13: stale KDS tickets stay on screen.
  - O-14: the Channel manager value is blank in Settings.
- **Info:**
  - O-15: `APP_DEBUG=true`.
  - O-16: records written before D-01 carry UTC times.
  - O-17: the hero video is not configured.

## Examples A–E (real system amounts)

- **A — walk-in Nuwan Perera, G1, 29 Sep → 3 Oct:** 4 × 30,500 + 10% service + 8% tax = **LKR 144,936.00**; deposit 20,000 by card.
- **B — table T6, 4 covers:** 22,800 + 2,280 + 2,006.40 = **27,086.40**; cash 30,000, change **2,913.60**.
- **C — room service to G1:** 8,500 + 850 + 748 = **10,098.00** charged to the villa. Laundry 2,000 + 160 = **2,160.00**.
- **D — checkout of Oliver Jensen (G1):** invoice INV-26-000019, **84,371.76**, paid in full. The training example (60,000 + 8,500 + 2,000 net) would come to 83,538.00.
- **E — G1 housekeeping:** Dirty → Cleaning → Inspection → Ready (approved).

## Deliverables (`docs/qa-training/`)

| File | What it is |
|---|---|
| `VAASAL_VILLA_TAMIL_USER_MANUAL.pdf` | 106 pages, 25 chapters. Cover, TOC with page numbers, 82 figures ("படம் N · Figure N — …"), role matrix, daily checklists, QA summary, glossary |
| `VAASAL_VILLA_QA_TEST_REPORT.pdf` | 35 pages, English with a Tamil summary. Environment, method, feature status, role matrix, examples, defects, open issues, blocked items, security review, night-audit review, appendix of all 175 automated/live cases, screenshot index |
| `VAASAL_VILLA_TRAINING_VIDEO_SCRIPT.pdf` | 24 pages. 16-scene storyboard with thumbnails, timing, actions and Tamil narration; recording guide; FFmpeg commands; pre-publish checklist |
| `VAASAL_VILLA_TRAINING.srt` | 56 Tamil subtitles, UTF-8, 7:34 total, matching the storyboard |
| `video_frames/` | 43 slides at 1920×1080 plus `slides.txt` (FFmpeg concat list with durations) |
| `screenshots/` | 82 full-size PNG screenshots from the real system. Required names: `01_admin_dashboard.png` … `13_night_audit.png` |
| `TEST_CASES.csv` / `TEST_CASES.xlsx` | All 257 cases: ID, source, module, case, expected, actual, status |
| `data/` | Inventory (routes, DB, roles, users, environment), role matrix, live checks, screenshot log, browser console log, test summary |
| `tools/` | Scripts to re-run the walkthrough and rebuild the PDFs, SRT and frames. Source HTML is in `*.src.html` and `manual_p*.html` |
| `_build/` | Intermediate HTML and JPEG slices used for the PDFs |

**No MP4 was produced.** This environment has no ffmpeg or screen recorder. Follow §5–6 of the video-script PDF to build one.

## Regenerating

```bash
# automated QA (uses the test database; keep the config cache cleared)
php artisan config:clear
php artisan test tests/QA/QaAuditTest.php
# screenshots — the dev database changes; restore a backup first for a clean run
powershell -ExecutionPolicy Bypass -File docs/qa-training/tools/story2.ps1
# documents
php -d extension=zip docs/qa-training/tools/merge_cases.php
powershell -ExecutionPolicy Bypass -File docs/qa-training/tools/prep_images.ps1
php docs/qa-training/tools/video_build.php
powershell -ExecutionPolicy Bypass -File docs/qa-training/tools/make_frames.ps1
powershell -ExecutionPolicy Bypass -File docs/qa-training/tools/make_pdfs.ps1
```

The scripts contain absolute paths for this machine (`C:\xampp\htdocs\…`). Some also expect the QA scratch files. Adjust `$root` and `$S` if you move the project.

## State of the dev database after the audit

The dev database `vaasal_villa_hms28` was restored from a pre-QA dump before the walkthrough. It now contains the walkthrough actions:

- Oliver Jensen checked out.
- G1 cleaned.
- VV-26-000033 (Nuwan Perera) checked into G1, with room service, laundry and a night-audit night posted.
- The 28 Sep In-villa dining shift closed; a new Vaasal Kitchen shift opened.
- Two old T6 demo checks settled by card; T6 order ORD-26-000098 paid in cash.
- Night audit run for 29 Sep (3 no-shows).

The pre-QA dump was kept outside the project because it contains password hashes.
