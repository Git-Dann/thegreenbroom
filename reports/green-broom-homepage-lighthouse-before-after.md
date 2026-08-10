# The Green Broom Homepage - Performance & Quality Report

**Page reviewed:** https://thegreenbroom.uk/

**Test type:** Lighthouse mobile simulation on a first page load using a Moto G Power profile and Slow 4G network throttling, followed by independent mobile and desktop checks.

**Report date:** 10 August 2026

## Executive Summary

The homepage was reworked to load its primary image earlier and at an appropriate mobile size, remove unnecessary visitor-side code, and improve the page structure for search engines and assistive technology.

The largest improvement was the time taken to show the main visual content. It fell from **20.3 seconds to 1.2 seconds** in the Lighthouse mobile test.

## Before and After

| Measure | Before | After | Outcome |
| --- | ---: | ---: | --- |
| Performance score | 70 | 100 | Excellent improvement |
| Accessibility score | 89 | 100 | All detected issues resolved |
| Best Practices score | Not recorded in baseline | 100 | All detected issues resolved |
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
- Corrected an image sizing rule that was stretching the "Eco Cleaning" image on mobile. It now preserves its natural proportions at every screen width.
- Removed an empty duplicate footer landmark so screen-reader users encounter one clear site footer.

## Final Audit Results

The following checks were completed after the final fixes:

- **Final mobile Lighthouse:** 100 Performance, 100 Accessibility, 100 Best Practices, and 100 SEO. First Contentful Paint was 0.9 seconds, Largest Contentful Paint 1.2 seconds, Total Blocking Time 0 ms, and Cumulative Layout Shift 0.
- **Final desktop Lighthouse:** 100 in all four categories. First Contentful Paint was 0.2 seconds and Largest Contentful Paint 0.4 seconds.
- **Independent accessibility scan:** 35 automated checks passed with zero violations. Some colour-contrast checks are marked as needing manual review because the hero text sits over photography; the final Lighthouse contrast audit passed at 100.
- **Public route review:** The homepage's internal links and assets were checked, and all public page, post, and cleaner-profile URLs listed in the XML sitemap returned HTTP 200.
- **Security and cache controls:** HTTPS, HSTS, clickjacking protection, content-type protection, a referrer policy, permissions policy, and content-security-policy restrictions are active. Login, registration, and account/dashboard pages correctly return no-store cache headers.
- **Code validation:** Every PHP file in the live theme and supporting plugin passed server-side syntax validation.

## Notes and Future Options

1. **Media cache lifetime:** FastHosts currently gives uploaded media an approximately one-hour cache lifetime. Extending it for versioned image files would be a sensible future hosting optimisation, but it is not limiting the final Lighthouse score.
2. **Live-user workflows:** This audit did not submit a real booking, payment, registration, or support message. Those should remain part of a controlled pre-launch operational check so no test records or payment activity are created unnecessarily.
3. **Performance variation:** Network conditions can move timings slightly between runs. The final mobile and desktop Lighthouse reports were both clean, with no run warnings.

## Recommended Follow-up

Use this report with the before-and-after Lighthouse screenshots for the client review. A final normal-browser check of the critical customer journeys is recommended before any Stripe launch, but no further homepage performance or accessibility work is currently required.
