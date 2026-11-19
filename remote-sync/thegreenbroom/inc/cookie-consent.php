<?php
/**
 * Cookie consent bar.
 *
 * Design notes, because the constraints here are unusual:
 *
 * 1. CACHE SAFE. Pages are served from a full-page cache, so the bar cannot be
 *    rendered conditionally on the server - a cached copy would carry one
 *    visitor's decision to everyone. It is always in the HTML and hidden
 *    immediately by inline JS when a stored choice exists, before paint.
 *
 * 2. HONEST. Without consent the site sets only strictly necessary cookies
 *    (login, security, caching), which need no consent under PECR. Marketing
 *    consent switches on the Meta Pixel (inc/tracking.php) and nothing else.
 *    There are no analytics cookies; the Analytics choice is kept so that one
 *    could only ever be added behind it.
 *
 * 3. NO PRE-TICKED BOXES and reject is as easy as accept - both are one click
 *    at the same level. That is the part regulators actually enforce.
 *
 * The decision is stored in a first-party cookie (so PHP can read it) and in
 * localStorage (so it survives cookie clearing by the cache layer). Read it
 * with gb_cookie_consent_allows( 'analytics' | 'marketing' ) before printing
 * any third-party tag.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const GB_CONSENT_COOKIE  = 'gb_cookie_consent';
// 2: November 2026, when the Meta Pixel was added. Everybody is asked again,
// because a yes given while the banner said nothing about adverts does not
// cover them.
const GB_CONSENT_VERSION = 2;

/**
 * Whether the visitor has allowed a given category.
 * Defaults to false: no consent means no tag, which is the legal default.
 */
function gb_cookie_consent_allows( $category ) {
    if ( empty( $_COOKIE[ GB_CONSENT_COOKIE ] ) ) {
        return false;
    }

    $raw = sanitize_text_field( wp_unslash( $_COOKIE[ GB_CONSENT_COOKIE ] ) );
    $decoded = json_decode( $raw, true );
    if ( ! is_array( $decoded ) || (int) ( $decoded['v'] ?? 0 ) !== GB_CONSENT_VERSION ) {
        return false;
    }

    return ! empty( $decoded[ $category ] );
}

function gb_cookie_consent_settings() {
    $settings_id = function_exists( 'gb_get_site_settings_id' ) ? gb_get_site_settings_id() : (int) get_option( 'page_on_front' );
    $get = function ( $key, $default ) use ( $settings_id ) {
        $value = $settings_id ? trim( (string) get_post_meta( $settings_id, $key, true ) ) : '';
        return $value === '' ? $default : $value;
    };

    $policy = $get( '_gb_cookie_policy_url', '' );
    if ( $policy === '' && function_exists( 'gb_get_cookie_policy_page_url' ) ) {
        $policy = gb_get_cookie_policy_page_url();
    }

    return array(
        'enabled' => $settings_id ? get_post_meta( $settings_id, '_gb_cookie_bar_enabled', true ) !== '0' : true,
        'title'   => $get( '_gb_cookie_bar_title', 'We use cookies' ),
        'body'    => $get( '_gb_cookie_bar_body', 'We use essential cookies to keep the site working and you signed in. With your permission we would also like to use marketing cookies, including Meta\'s (Facebook and Instagram), to measure our adverts and show them to people likely to find us useful.' ),
        'accept'  => $get( '_gb_cookie_bar_accept', 'Accept all' ),
        'reject'  => $get( '_gb_cookie_bar_reject', 'Essential only' ),
        'choose'  => $get( '_gb_cookie_bar_choose', 'Choose' ),
        'policy'  => $policy,
    );
}

/**
 * The bar itself. Printed on every public page; never on the manager
 * dashboard, which renders its own document.
 */
function gb_render_cookie_consent_bar() {
    if ( is_admin() ) {
        return;
    }

    // The manager dashboard prints its own document and calls wp_footer(), but
    // it is a logged-in tool sitting behind strictly necessary cookies. A
    // consent bar there is noise, not a choice.
    if ( is_page_template( 'page-manager-dashboard.php' ) ) {
        return;
    }

    $s = gb_cookie_consent_settings();
    if ( ! $s['enabled'] ) {
        return;
    }
    ?>
    <div id="gb-cookie-bar" class="gb-cookie-bar" role="dialog" aria-modal="false"
         aria-labelledby="gb-cookie-title" aria-describedby="gb-cookie-body" hidden>
        <div class="gb-cookie-inner">
            <div class="gb-cookie-copy">
                <p id="gb-cookie-title" class="gb-cookie-title"><?php echo esc_html( $s['title'] ); ?></p>
                <p id="gb-cookie-body" class="gb-cookie-text">
                    <?php echo esc_html( $s['body'] ); ?>
                    <?php if ( $s['policy'] !== '' ) : ?>
                        <a href="<?php echo esc_url( $s['policy'] ); ?>">Read our cookie policy</a>
                    <?php endif; ?>
                </p>
            </div>
            <div class="gb-cookie-actions">
                <button type="button" class="gb-cookie-btn gb-cookie-link" data-gb-consent="choose"><?php echo esc_html( $s['choose'] ); ?></button>
                <button type="button" class="gb-cookie-btn" data-gb-consent="reject"><?php echo esc_html( $s['reject'] ); ?></button>
                <button type="button" class="gb-cookie-btn gb-cookie-primary" data-gb-consent="accept"><?php echo esc_html( $s['accept'] ); ?></button>
            </div>
        </div>

        <div class="gb-cookie-panel" id="gb-cookie-panel" hidden>
            <label class="gb-cookie-row gb-cookie-row-locked">
                <input type="checkbox" checked disabled>
                <span>
                    <strong>Strictly necessary</strong>
                    <small>Keeps you signed in and the site secure. Always on, and allowed without consent.</small>
                </span>
            </label>
            <label class="gb-cookie-row">
                <input type="checkbox" id="gb-consent-analytics">
                <span>
                    <strong>Analytics</strong>
                    <small>Not used at the moment. If we ever add an analytics tool, it will only run if this is ticked.</small>
                </span>
            </label>
            <label class="gb-cookie-row">
                <input type="checkbox" id="gb-consent-marketing">
                <span>
                    <strong>Marketing</strong>
                    <small>Lets Meta (Facebook and Instagram) measure our adverts and show them to the right people, using the Meta Pixel.</small>
                </span>
            </label>
            <div class="gb-cookie-panel-actions">
                <button type="button" class="gb-cookie-btn gb-cookie-primary" data-gb-consent="save">Save my choices</button>
            </div>
        </div>
    </div>


    <script>
    (function () {
        var NAME = <?php echo wp_json_encode( GB_CONSENT_COOKIE ); ?>;
        var VERSION = <?php echo (int) GB_CONSENT_VERSION; ?>;
        var bar = document.getElementById('gb-cookie-bar');
        var panel = document.getElementById('gb-cookie-panel');
        if (!bar) { return; }

        function read() {
            try {
                var m = document.cookie.match(new RegExp('(?:^|; )' + NAME + '=([^;]*)'));
                if (m) { return JSON.parse(decodeURIComponent(m[1])); }
                var ls = window.localStorage.getItem(NAME);
                if (ls) { return JSON.parse(ls); }
            } catch (e) { /* private mode, blocked storage - treat as undecided */ }
            return null;
        }

        function save(choice) {
            var value = {
                v: VERSION,
                analytics: !!choice.analytics,
                marketing: !!choice.marketing,
                at: new Date().toISOString()
            };
            var json = JSON.stringify(value);
            try {
                // Six months, the usual expectation for re-asking.
                var expires = new Date(Date.now() + 182 * 864e5).toUTCString();
                document.cookie = NAME + '=' + encodeURIComponent(json) +
                    ';expires=' + expires + ';path=/;SameSite=Lax' +
                    (location.protocol === 'https:' ? ';Secure' : '');
                window.localStorage.setItem(NAME, json);
            } catch (e) { /* nothing we can do; the bar simply reappears */ }

            bar.hidden = true;
            document.dispatchEvent(new CustomEvent('gb:consent', { detail: value }));
        }

        function fill(choice) {
            var a = document.getElementById('gb-consent-analytics');
            var m = document.getElementById('gb-consent-marketing');
            if (a) { a.checked = !!(choice && choice.analytics); }
            if (m) { m.checked = !!(choice && choice.marketing); }
        }

        var stored = read();
        fill(stored);
        if (!stored || stored.v !== VERSION) {
            bar.hidden = false;
        } else {
            document.dispatchEvent(new CustomEvent('gb:consent', { detail: stored }));
        }

        // "Cookie settings" in the footer reopens the bar with the panel already
        // expanded, so a visitor can change their mind without clearing cookies.
        document.addEventListener('click', function (e) {
            var link = e.target.closest('[data-gb-cookie-settings]');
            if (!link) { return; }
            e.preventDefault();
            fill(read());
            bar.hidden = false;
            if (panel) { panel.hidden = false; }
            bar.scrollIntoView({ block: 'nearest' });
        });

        bar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-gb-consent]');
            if (!btn) { return; }
            var action = btn.getAttribute('data-gb-consent');

            if (action === 'accept') { save({ analytics: true, marketing: true }); }
            else if (action === 'reject') { save({ analytics: false, marketing: false }); }
            else if (action === 'choose') {
                var open = panel.hidden;
                panel.hidden = !open;
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            } else if (action === 'save') {
                save({
                    analytics: document.getElementById('gb-consent-analytics').checked,
                    marketing: document.getElementById('gb-consent-marketing').checked
                });
            }
        });
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'gb_render_cookie_consent_bar', 5 );
