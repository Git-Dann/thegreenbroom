// Pays a Stripe Checkout page with Stripe's test card, the way a person would.
//
// Used by the "Stripe self-test" step in deploy.yml, in Test mode only: the
// site refuses to hand out a checkout page for this when it is in Live mode.
//
//   node stripe-test-checkout.js <checkout url>
//
// Exits 0 once Stripe has sent the browser back to /membership-success/.
// On failure it says what the page was showing and leaves a screenshot at
// stripe-checkout.png for the workflow to upload.

const { chromium } = require('playwright');

const url = process.argv[2];

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 1000 }, locale: 'en-GB' });

  const fillIfThere = async (selector, value) => {
    const el = page.locator(selector).first();
    if (await el.count() && await el.isVisible() && await el.isEditable()) {
      await el.fill(value);
      return true;
    }
    return false;
  };

  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });

    // With more than one payment method switched on, the card form sits
    // behind an accordion button.
    const cardButton = page.locator('[data-testid="card-accordion-item-button"]');
    if (await cardButton.count() && await cardButton.isVisible()) {
      await cardButton.click();
    }

    await page.locator('#cardNumber').waitFor({ timeout: 30000 });
    await fillIfThere('#email', 'stripe-selftest@thegreenbroom.uk');
    await page.fill('#cardNumber', '4242 4242 4242 4242');
    await page.fill('#cardExpiry', '12 / 34');
    await page.fill('#cardCvc', '123');
    await fillIfThere('#billingName', 'Stripe Self Test');

    const country = page.locator('#billingCountry');
    if (await country.count() && await country.isVisible()) {
      await country.selectOption('GB');
    }
    await fillIfThere('#billingPostalCode', 'L1 1AA');

    // Do not sign the test customer up to Link.
    const link = page.locator('#enableStripePass');
    if (await link.count() && await link.isChecked().catch(() => false)) {
      await link.uncheck();
    }

    await page.locator('.SubmitButton, [data-testid="hosted-payment-submit-button"]').first().click();
    await page.waitForURL(/membership-success/, { timeout: 90000 });

    console.log('Paid with the test card; Stripe sent the browser back to ' + new URL(page.url()).pathname);
    await browser.close();
    process.exit(0);
  } catch (err) {
    console.log('Checkout did not complete: ' + err.message);
    console.log('Page: ' + page.url());
    const shown = await page.locator('[role="alert"], .FieldError, .Notice').allInnerTexts().catch(() => []);
    if (shown.length) {
      console.log('Stripe was showing: ' + shown.join(' | '));
    }
    await page.screenshot({ path: 'stripe-checkout.png', fullPage: true }).catch(() => {});
    await browser.close();
    process.exit(1);
  }
})();
