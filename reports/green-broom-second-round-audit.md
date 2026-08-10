# The Green Broom - Second-Round Website Audit

**Website reviewed:** https://thegreenbroom.uk/

**Audit date:** 10 August 2026

**Purpose:** A second, broader quality check of the public website, customer and cleaner entry points, protected areas, documents, forms, speed, accessibility, and security safeguards.

## 1. Audit Summary

This audit checked the website beyond the homepage. It covered the main journeys a visitor would take, how protected areas behave when someone is not signed in, whether documents are available to the correct people, whether public links work, and whether the site remains quick and accessible across key pages.

One additional improvement was identified during the booking-code review and has been applied: the separate background booking route now refuses invalid or hidden cleaners, unavailable services, incorrect dates or times, and incomplete booking details before it can create a booking record.

**What this means:** The website has had a second, deeper check across the things that can affect real customers and cleaners. It has also been strengthened in one area where a deliberately altered form submission could previously have created poor-quality booking data.

## 2. Public Pages and Links

### What was checked

- All 34 pages, posts, cleaner profiles, and protected portal routes listed in the website sitemap.
- 103 internal page and asset links found across the public site.
- The correct WordPress sitemap and the website's robots instructions.

### Result

- All sitemap entries either loaded successfully or correctly sent a signed-out visitor to the login page where access is private.
- No broken customer-facing internal links were found.
- The website sitemap is live and correctly advertised to search engines.
- One WordPress technical service address returned a 403 response by design because remote publishing access is intentionally blocked. It is not a visitor-facing link or a fault.

**What this means:** Customers should not be sent to dead pages or broken documents while moving around the public website. Search engines also have a clear route to discover the site's pages.

## 3. Protected Areas and Documents

### What was checked

- Direct access to the manager dashboard, cleaner dashboard, inbox, settings, schedule, verification, and account areas while signed out.
- Public and portal-only guide documents.
- Direct access to secure document routes.

### Result

- Every private dashboard route tested redirected a signed-out visitor to the login page.
- Public documents opened successfully.
- Cleaner, customer, and insurance documents directed a signed-out visitor to login rather than exposing the file.
- The document route uses controlled access rather than exposing protected files directly.

**What this means:** Private areas and portal-only documents are not freely available to the public. Visitors only see the information that is appropriate for their account access.

## 4. Forms and Booking Safeguards

### What was checked

- The registration, booking, contact, and login forms for required fields and security checks.
- Booking and message actions submitted without a valid security token.
- Booking code paths used by both the customer form and the background booking route.

### Result

- Each public form checked includes a security token and required fields.
- A booking request without its security token was correctly rejected.
- A message request without its security token was correctly rejected.
- Rate limits are in place to reduce repeated automated submissions.
- The primary booking form already validates customer details, service selection, date, time, duration, cleaner availability, and duplicate time slots.
- The additional background booking route has now been hardened to apply the same standard of validation before creating any record.

**What this means:** The site is better protected from spam, accidental double bookings, and malformed booking requests. Customers receive clearer feedback when information is missing or invalid.

## 5. Speed and Mobile Quality

The following key pages were checked using mobile-style Lighthouse testing.

| Page | Speed | Accessibility | General Quality | Search Readiness |
| --- | ---: | ---: | ---: | ---: |
| Homepage | 100 | 100 | 100 | 100 |
| Cleaner listings | 99 | 100 | 100 | 92 |
| Booking | 100 | 100 | 100 | 92 |
| Registration | 100 | 100 | 100 | 92 |
| Contact | 100 | 100 | 100 | 92 |
| Eco products | 100 | 100 | 100 | 92 |
| UK Household Recycling article | 99 | 100 | 100 | 100 |

The homepage's main content appears in approximately 1.2 seconds in the test, and the page layout remains stable while it loads.

**What this means:** The most important pages are loading quickly and behaving well on mobile. Visitors should not be left waiting for the core content or watching the page jump around as it appears.

## 6. Accessibility

### What was checked

An automated accessibility scan was run across eight key public pages:

- Homepage
- Cleaner listings
- Booking
- Registration
- Contact
- Eco products
- UK Household Recycling article
- Login

### Result

The scan found no automated accessibility violations on any of the eight pages.

This follows the earlier improvements to navigation labels, mobile controls, form labels, text contrast, image descriptions, page structure, and keyboard-friendly controls.

**What this means:** The website is easier to use for a wider range of people, including visitors who use a keyboard, screen reader, or a smaller phone screen. Automated testing is not a replacement for a real-person screen-reader review, but it is a strong passing result.

## 7. Security and Reliability

### What was checked

- Login protection for private pages.
- Security headers sent by the live website.
- Anti-forgery protection on sensitive form actions.
- File upload and protected-document controls.
- Payment webhook signature checks in the code.
- PHP syntax across the entire theme and companion plugin.

### Result

- Private pages redirect to sign in when a visitor is not authorised.
- The live site is sending modern browser protections, including HTTPS-only rules, frame protection, referrer protection, and limits on browser features such as camera and microphone access.
- Sensitive actions use security tokens to prevent unwanted submissions.
- Secure cleaner verification files are stored separately and served through an authorised route.
- Stripe webhooks check their signature before accepting an update.
- All 49 PHP files in the theme and companion plugin passed syntax validation.

**What this means:** The website has several layers of protection around accounts, forms, uploaded documents, and payment updates. It is not relying on a single check to keep private information safe.

## 8. Checks Deliberately Kept Out of the Live Site

To avoid creating false records or charges, the following actions were not run against the live site:

- Completing a real payment.
- Creating a real customer or cleaner account.
- Submitting a real booking.
- Uploading a real verification document.
- Sending real customer messages or support enquiries.

These should be completed once in Stripe's safe test mode and, where useful, in a separate test account before real payments are enabled.

**What this means:** The audit has checked the protections and page behaviour without creating unwanted emails, booking records, personal-data uploads, or financial transactions.

## 9. Overall Conclusion

The second-round audit found no public-page, mobile, accessibility, document-access, or account-access blocker. The public website is fast, key user journeys are protected, the main pages and documents behave as expected, and the small booking-route hardening improvement has been released.

The final recommended launch check is a controlled Stripe test payment, followed by a short manual review using a real keyboard and screen reader. Those checks are best performed as separate, deliberate exercises because they involve real account behaviour and payment configuration.

**What this means:** The Green Broom website is in a strong, well-tested position for normal use. The remaining work is not about fixing a known fault; it is about completing the last controlled checks before real payments are turned on.
