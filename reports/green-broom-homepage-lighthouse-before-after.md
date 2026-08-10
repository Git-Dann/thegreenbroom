# The Green Broom Homepage - Performance & Quality Report

**Page reviewed:** https://thegreenbroom.uk/

**Test type:** Lighthouse mobile simulation on a first page load using a Moto G Power profile and Slow 4G network throttling.

**Report date:** 10 August 2026

## Executive Summary

The homepage was reworked to load its primary image earlier and at an appropriate mobile size, remove unnecessary visitor-side code, and improve the page structure for search engines and assistive technology.

The largest improvement was the time taken to show the main visual content. It fell from **20.3 seconds to 1.2 seconds** in the Lighthouse mobile test.

## Before and After

| Measure | Before | After | Outcome |
| --- | ---: | ---: | --- |
| Performance score | 70 | 100 | Excellent improvement |
| Accessibility score | 89 | 100 | All detected issues resolved |
| Best Practices score | Not recorded in baseline | 96 | Strong result; see notes below |
| SEO score | 92 | 100 | All detected issues resolved |
| First Contentful Paint | 2.8 s | 0.8 s | Content appears much sooner |
| Largest Contentful Paint | 20.3 s | 1.2 s | Main page content loads dramatically faster |
| Total Blocking Time | 0 ms | 50 ms | Still within the green Lighthouse range |
| Cumulative Layout Shift | 0 | 0 | No unexpected movement on the page |
| Speed Index | 2.8 s | 0.8 s | Page becomes visually complete sooner |

## Improvements Delivered

- Replaced the homepage hero's CSS-only background with a responsive, high-priority image. This lets the browser discover and start loading the main image immediately.
- Served a much smaller image source to phone-sized screens instead of the original high-resolution file.
- Added explicit image dimensions and deferred below-the-fold images to prevent layout movement and reduce initial page work.
- Removed unnecessary homepage downloads, including the directory plugin's jQuery assets, external font requests, Dashicons for visitors, and WordPress emoji compatibility code.
- Added a proper main content landmark for screen-reader navigation.
- Added a clear accessible name to the mobile navigation control.
- Improved text contrast where Lighthouse identified a risk.
- Added a meta description so search engines have a clear summary of the homepage.
- Preserved the existing design, including the full mobile wordmark, hero image, calls to action, and navigation behaviour.

## Remaining Lighthouse Notes

These are minor advisory items and do not affect the 100 performance, accessibility, or SEO scores.

1. **Media cache lifetime (estimated 92 KiB):** FastHosts currently sends uploaded media with a one-hour cache lifetime. This is a hosting/server setting rather than a theme issue. Extending it for versioned image files would be a sensible future hosting optimisation.
2. **Image delivery (estimated 45 KiB):** Lighthouse can suggest further compression for the hero image. The homepage already serves a responsive 768-pixel image on mobile, so the remaining potential saving is small and not affecting the performance score.
3. **Best Practices 96 - image aspect ratio:** The hero image is intentionally cropped with `object-fit: cover` to fill different screen shapes without distortion. Lighthouse flags the difference between its intrinsic and displayed aspect ratios, but the image is not stretched and the visual result is correct.
4. **Back/forward cache message:** One browser-side run reported a back/forward cache note while DevTools was open. A separate automated live Lighthouse run passed that check. This does not affect normal first-load performance.

## Validation Completed

- Live homepage returned HTTP 200.
- All changed PHP files passed server-side syntax validation.
- Public page markup was checked for the responsive hero, meta description, main landmark, labelled navigation button, and removal of the old visitor-side dependencies.
- Live mobile screenshot visually checked for wordmark, navigation control, hero copy, buttons, and responsive layout.
- Fresh live Lighthouse run confirmed 100 Performance, 100 Accessibility, and 100 SEO.

## Recommended Follow-up

Run a final Lighthouse test from another normal browser session or network connection before sharing the result externally. Scores can vary slightly with network conditions, but the key loading metrics are now firmly in the green range.
