# Going live with payments

Everything below is done once, in this order. The order matters: several steps
overwrite or ignore each other if taken out of sequence, and the notes say why.

Nothing here touches the sandbox. Test mode keeps working throughout, so you
can stop at any point and come back.

---

## Before you start: what does *not* carry over

Stripe keeps test and live completely separate, and so does this site. None of
the following come with you:

| Thing | What happens |
|---|---|
| The three plans | Recorded per mode (`_gb_stripe_subs_test` / `_gb_stripe_subs_live`). Live starts empty and must be provisioned again. |
| Cleaners' payout accounts | A Connect account created in test does not exist in live. **Every cleaner has to complete payout setup again.** |
| The billing portal settings | Configured per mode. The live portal starts unconfigured, and an unconfigured portal makes *Manage or cancel* fail. |
| Existing subscriptions | The sandbox subscriptions are not real. Nobody is carried over as a paying member. |
| Webhook endpoints | Separate per mode, with their own signing secrets. |

The one that bites hardest is the second. Plan for telling cleaners they need
to set up payouts once more, and expect that to take a few days to chase.

---

## 1. Activate the Stripe account

**Stripe Dashboard → Activate payments** (top right, while in Live mode).

Stripe needs the business details, a bank account, and identity for whoever
controls the business. This is Stripe's process, not ours, and it is the long
pole: it can be instant, or it can sit in review for a day or two.

**Do this first.** Everything else is quick, and there is no point configuring
a live account that cannot take a payment yet.

---

## 2. Branding

**Stripe → Settings → Business → Branding**

This is what customers see on the Checkout page, on the billing portal, and on
Stripe's receipts. Logo, icon, brand colour, business name.

Skip it and people pay a page that looks like a generic Stripe form, which is
exactly where card abandonment comes from.

---

## 3. Customer emails

**Stripe → Settings → Business → Customer emails**

Turn on **successful payments** and **refunds** at a minimum. The site sends
its own booking confirmations, but the receipt for money taken should come
from the thing that took it.

---

## 4. Configure the billing portal — **in Live mode**

**Stripe → Settings → Billing → Customer portal**
→ https://dashboard.stripe.com/settings/billing/portal

Set it up as you did in the sandbox, then press **Save changes** at the bottom.
It is not saved until you do, and until it is saved the API refuses to open a
portal session at all.

Recommended settings, matching the sandbox:

- **Cancel subscriptions** — on
- **Cancel at end of billing period** — somebody who has paid to December keeps
  what they paid for
- **Collect a cancellation reason** — on; it is the only churn feedback you get
- **Update payment methods** — on

The site handles *cancel at end of period* correctly: the member's account
reads "Ending", with the date, and Members shows them under **Ending** rather
than as a happy subscriber.

---

## 5. Live API keys into the site

**Stripe → Developers → API keys** (in Live mode), then
**Manager OS → Settings → Payments → Stripe credentials**.

- **Live publishable key** — `pk_live_…`
- **Live secret key** — `sk_live_…`

The secret fields are write-only: they render empty on purpose and a blank
submission means "leave it alone", so you can save that screen later without
wiping them.

**Leave the mode on Test for now.** Keys first, switch after.

---

## 6. Live webhook endpoints — all three

**Stripe → Developers → Webhooks → Add endpoint**, three times, all pointing at:

```
https://thegreenbroom.uk/wp-admin/admin-post.php?action=gb_stripe_webhook
```

They have to be three separate endpoints because Stripe will not mix v1
snapshot events and v2 thin events on one destination.

**a. Memberships and bookings** — 10 events:

```
checkout.session.completed
checkout.session.async_payment_succeeded
checkout.session.async_payment_failed
checkout.session.expired
customer.subscription.created
customer.subscription.updated
customer.subscription.deleted
customer.subscription.trial_will_end
invoice.payment_succeeded
invoice.payment_failed
```

**b. Connect** — `account.updated`, with **"Listen to events on connected
accounts"** ticked.

**c. Accounts v2** — `v2.core.account[requirements].updated`.

Each gives a signing secret (`whsec_…`). They go into
**Settings → Payments** as *Live Webhook Secret*, *Live Connect Webhook Secret*
and *Live v2 Webhook Secret* respectively. One URL serves all three; the
handler tries each secret against the incoming signature.

---

## 7. Switch the site to Live

**Manager OS → Settings → Payments → Operation mode → Live.**

From this moment the buy buttons are public. In Test mode they are shown only
to managers and administrators, deliberately, so the public is never sent to a
sandbox checkout.

---

## 8. Provision the plans in Live

**Settings → Payments → Set up the plans in Stripe.**

This creates the three products and recurring prices in the live account and
records their ids against live mode. Until it is run, checkout has no price to
use and the buttons will not work.

Check the prices on the pricing panels are the ones you actually want to
charge **before** pressing it. Stripe prices are immutable: changing a price
later creates a new one and archives the old, which is correct, but it is
tidier to start right.

Then confirm all three read **Provisioned**.

---

## 9. Prove it with a real card

Use a real card, buy the cheapest plan, then refund it in Stripe.

Check, in order:

1. Checkout opens, branded, on `checkout.stripe.com`
2. Payment succeeds and you land on `/membership-success/`
3. **Manager OS → Members** shows you as **Active**, with the right plan and date
4. **My Account** shows the plan and a working **Manage or cancel**
5. That button opens the live billing portal
6. Cancel in the portal, come back, and Members shows **Ending** with the date
7. Refund in Stripe

If step 3 or 6 lags, press **Sync from Stripe** at the top of Members — it
re-pulls every membership and tells you what Stripe returned.

---

## 10. Cleaners set up payouts again

Every cleaner: **Settings → Stripe Connect → Start Setup**.

Until a cleaner finishes this, their listing shows "Not taking bookings" and
the Book Now button is hidden, because there is no way to pay them. Their own
dashboard now tells them so with a button straight to the right screen.

A manager can see who is outstanding on **Manager OS → Cleaners**, where each
cleaner's Connect status and whether charges are enabled are both shown.

**Today, nobody has completed this, so no cleaner on the platform can be
booked.** That is the single biggest thing standing between the site as it is
and a working marketplace.

---

## 11. Decide on the membership gate

**Settings → Membership.**

It is off. A customer paying £24.95 currently gets exactly what a stranger
gets: the cleaner database is open to everyone.

That is a commercial decision, not a technical one, and it should be taken
deliberately rather than discovered. Either turn it on before you start
charging, or be clear in the sales copy about what the membership buys.

---

## After go-live: what to watch in the first week

- **Stripe → Developers → Webhooks** — any endpoint showing failed deliveries.
  A failed delivery means the site's record of somebody's membership is stale.
- **Members → Ending** — the count of people on their way out, which is the
  first number worth watching.
- **Cleaners** — how many are still not payout-ready.
- **Failed payments** — `invoice.payment_failed` is subscribed; a card that
  fails puts the subscription into `past_due`, which this site still treats as
  active until Stripe gives up on it. That is deliberate, so nobody loses
  access over a temporary bank decline.

---

## If something goes wrong

**The cancel button does nothing at all.** Check the browser console for a
Content Security Policy error. The site's `form-action` policy has to name
every Stripe host a form ends up submitting to — `checkout.stripe.com`,
`connect.stripe.com` and `billing.stripe.com` are all listed in
`functions.php`. A blocked navigation produces no error page, only a console
line, so it looks exactly like a dead button.

**Members disagrees with Stripe.** Press **Sync from Stripe**. It re-pulls
every membership and prints what Stripe returned, including whether the
cancellation fields were present — which is how the last of these was found.

**A cleaner cannot be booked but says they finished payout setup.** Open them
in Manager OS → Cleaners and press **Refresh status**; Stripe may still be
holding a requirement.
