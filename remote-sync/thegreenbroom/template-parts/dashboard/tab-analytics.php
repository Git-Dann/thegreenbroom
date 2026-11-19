<?php
/**
 * Manager OS > Analytics.
 *
 * Everything here comes from the site's own cookieless counts (see
 * inc/tracking.php), so it covers every visitor, not only the ones who allow
 * cookies. Meta's own numbers, in Events Manager, will be lower: Meta only
 * hears about visitors who said yes to marketing cookies.
 */

global $wpdb;

$settings_id = (int) get_option('page_on_front');
$an_ready    = function_exists('gb_analytics_ready') && gb_analytics_ready();
$an_table    = function_exists('gb_analytics_table') ? gb_analytics_table() : '';
$an_catalogue = function_exists('gb_tracking_catalogue') ? gb_tracking_catalogue() : [];

$an_ranges = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last 12 months'];
$an_days   = isset($_GET['range']) ? (int) $_GET['range'] : 30;
if (!isset($an_ranges[$an_days])) {
    $an_days = 30;
}

$an_today      = current_time('Y-m-d');
$an_today_ts   = strtotime($an_today);
$an_start      = gmdate('Y-m-d', $an_today_ts - ($an_days - 1) * DAY_IN_SECONDS);
$an_prev_end   = gmdate('Y-m-d', $an_today_ts - $an_days * DAY_IN_SECONDS);
$an_prev_start = gmdate('Y-m-d', $an_today_ts - (2 * $an_days - 1) * DAY_IN_SECONDS);

$an_totals = static function ($from, $to) use ($wpdb, $an_ready, $an_table) {
    $out = [];
    if (!$an_ready) {
        return $out;
    }
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT metric, SUM(hits) AS hits, SUM(amount) AS amount FROM {$an_table} WHERE day BETWEEN %s AND %s GROUP BY metric",
        $from, $to
    ));
    foreach ((array) $rows as $row) {
        $out[$row->metric] = ['hits' => (int) $row->hits, 'amount' => (float) $row->amount];
    }
    return $out;
};

$an_top = static function ($metric, $limit = 10, $label_like = null) use ($wpdb, $an_ready, $an_table, $an_start, $an_today) {
    if (!$an_ready) {
        return [];
    }
    $sql  = "SELECT label, SUM(hits) AS hits, SUM(amount) AS amount FROM {$an_table} WHERE metric = %s AND day BETWEEN %s AND %s";
    $args = [$metric, $an_start, $an_today];
    if ($label_like !== null) {
        $sql   .= ' AND label = %s';
        $args[] = $label_like;
    }
    $sql   .= ' GROUP BY label ORDER BY hits DESC LIMIT %d';
    $args[] = (int) $limit;
    return (array) $wpdb->get_results($wpdb->prepare($sql, $args));
};

$an_now  = $an_totals($an_start, $an_today);
$an_prev = $an_totals($an_prev_start, $an_prev_end);

$an_hits   = static function ($set, ...$metrics) { $n = 0; foreach ($metrics as $m) { $n += isset($set[$m]) ? $set[$m]['hits'] : 0; } return $n; };
$an_amount = static function ($set, ...$metrics) { $n = 0.0; foreach ($metrics as $m) { $n += isset($set[$m]) ? $set[$m]['amount'] : 0; } return $n; };

$an_delta = static function ($now, $before) {
    if ($before <= 0) {
        return $now > 0 ? ['new', 'New'] : ['flat', ''];
    }
    $pct = (int) round((($now - $before) / $before) * 100);
    if ($pct === 0) {
        return ['flat', 'No change'];
    }
    return [$pct > 0 ? 'up' : 'down', ($pct > 0 ? '+' : '') . $pct . '%'];
};

$an_cards = [
    ['Visits',                 ['page_view'],                         false, 'Pages viewed on the website'],
    ['Cleaner profile views',  ['profile_view'],                      false, 'Times a cleaner profile was opened'],
    ['Cleaner searches',       ['listing_search'],                    false, 'Searches by area or service'],
    ['Enquiries',              ['cleaner_enquiry', 'contact_form'],   false, 'Messages to cleaners and the contact form'],
    ['Bookings paid',          ['booking_paid'],                      true,  'Confirmed by Stripe'],
    ['Customer sign-ups',      ['customer_signup'],                   false, 'New customer accounts'],
    ['Cleaner sign-ups',       ['cleaner_signup'],                    false, 'New cleaner accounts'],
    ['Memberships & listings', ['membership_paid', 'listing_paid'],   true,  'Customer memberships and cleaner listings started'],
];

// Daily visits, with empty days filled in so the chart is honest about gaps.
$an_daily = [];
if ($an_ready) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT day, SUM(hits) AS hits FROM {$an_table} WHERE metric = 'page_view' AND day BETWEEN %s AND %s GROUP BY day",
        $an_start, $an_today
    ));
    foreach ((array) $rows as $row) {
        $an_daily[$row->day] = (int) $row->hits;
    }
}
$an_series = [];
for ($i = $an_days - 1; $i >= 0; $i--) {
    $d = gmdate('Y-m-d', $an_today_ts - $i * DAY_IN_SECONDS);
    $an_series[$d] = isset($an_daily[$d]) ? $an_daily[$d] : 0;
}
// A year of daily bars is unreadable; show it by week instead.
if ($an_days > 90) {
    $weekly = [];
    foreach ($an_series as $d => $n) {
        $key = gmdate('Y-m-d', strtotime($d) - ((int) gmdate('N', strtotime($d)) - 1) * DAY_IN_SECONDS);
        $weekly[$key] = (isset($weekly[$key]) ? $weekly[$key] : 0) + $n;
    }
    $an_series = $weekly;
}
$an_peak = max(1, max($an_series ?: [0]));
$an_keys = array_keys($an_series);
$an_first_day = reset($an_keys);
$an_last_day = end($an_keys);

$an_sources  = $an_top('landing', 10);
$an_pages    = $an_top('page_view', 12);
$an_searches = $an_top('listing_search', 10);
$an_profiles = $an_top('profile_view', 10);
$an_consent  = $an_top('consent', 5);

$an_source_total = 0;
foreach ($an_sources as $row) { $an_source_total += (int) $row->hits; }

$an_consent_yes = 0;
$an_consent_no  = 0;
foreach ($an_consent as $row) {
    if ($row->label === 'Allowed marketing') { $an_consent_yes += (int) $row->hits; } else { $an_consent_no += (int) $row->hits; }
}
$an_consent_total = $an_consent_yes + $an_consent_no;

$an_cleaner_checkouts = 0;
foreach ($an_top('membership_started', 10) as $row) {
    if (strpos((string) $row->label, 'cleaner') === 0) { $an_cleaner_checkouts += (int) $row->hits; }
}

$an_funnels = [
    'Customers' => [
        ['Viewed a cleaner profile', $an_hits($an_now, 'profile_view')],
        ['Sent an enquiry',          $an_hits($an_now, 'cleaner_enquiry')],
        ['Started a booking',        $an_hits($an_now, 'booking_started')],
        ['Paid for a booking',       $an_hits($an_now, 'booking_paid')],
    ],
    'Cleaners' => [
        ['Viewed Become a Cleaner',  $an_hits($an_now, 'join_view')],
        ['Created an account',       $an_hits($an_now, 'cleaner_signup')],
        ['Went to set up a listing', $an_cleaner_checkouts],
        ['Listing started',          $an_hits($an_now, 'listing_paid')],
    ],
];

// Meta settings.
$an_pixel_id     = function_exists('gb_meta_pixel_id') ? gb_meta_pixel_id() : '';
$an_pixel_on     = function_exists('gb_meta_pixel_enabled') && gb_meta_pixel_enabled();
$an_raw_pixel    = (string) get_post_meta($settings_id, '_gb_meta_pixel_id', true);
$an_capi_set     = function_exists('gb_meta_capi_token') && gb_meta_capi_token() !== '';
$an_test_code    = (string) get_post_meta($settings_id, '_gb_meta_test_event_code', true);
$an_last_test    = get_option('gb_meta_capi_last_test');
$an_capi_notice  = isset($_GET['capi_notice']) ? sanitize_key(wp_unslash($_GET['capi_notice'])) : '';
$an_capi_msg     = isset($_GET['capi_msg']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['capi_msg']))) : '';
$an_started      = $an_ready ? (string) $wpdb->get_var("SELECT MIN(day) FROM {$an_table}") : '';

$an_fmt_money = static function ($n) { return '£' . number_format((float) $n, $n >= 1000 ? 0 : 2); };
?>

<div class="gb-an">
    <div class="gb-an-head">
        <div>
            <h2>Analytics</h2>
            <p>How people find and use the website<?php echo $an_started ? ', counted since ' . esc_html(date_i18n('j F Y', strtotime($an_started))) : ''; ?>. Counted without cookies, so it includes every visitor.</p>
        </div>
        <nav class="gb-an-ranges" aria-label="Date range">
            <?php foreach ($an_ranges as $n => $label): ?>
                <a href="<?php echo esc_url(add_query_arg(['tab' => 'analytics', 'range' => $n], home_url('/manager-dashboard/'))); ?>" class="<?php echo $n === $an_days ? 'is-on' : ''; ?>"<?php echo $n === $an_days ? ' aria-current="true"' : ''; ?>><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </nav>
    </div>

    <?php if (!$an_ready): ?>
        <div class="gb-card gb-an-note">Analytics is being set up. Numbers will start appearing as soon as people visit the website.</div>
    <?php endif; ?>

    <div class="gb-an-kpis">
        <?php foreach ($an_cards as $card):
            [$title, $metrics, $money, $help] = $card;
            $now_n  = $an_hits($an_now, ...$metrics);
            $prev_n = $an_hits($an_prev, ...$metrics);
            [$tone, $delta] = $an_delta($now_n, $prev_n);
            $value = $money ? $an_amount($an_now, ...$metrics) : 0;
            ?>
            <div class="gb-an-kpi" title="<?php echo esc_attr($help); ?>">
                <span class="gb-an-kpi-label"><?php echo esc_html($title); ?></span>
                <strong class="gb-an-kpi-val"><?php echo esc_html(number_format_i18n($now_n)); ?></strong>
                <span class="gb-an-kpi-foot">
                    <?php if ($money && $value > 0): ?><span class="gb-an-money"><?php echo esc_html($an_fmt_money($value)); ?></span><?php endif; ?>
                    <?php if ($delta !== ''): ?><span class="gb-an-delta is-<?php echo esc_attr($tone); ?>"><?php echo esc_html($delta); ?></span><?php endif; ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="gb-an-small">Changes compare with the <?php echo esc_html(strtolower($an_ranges[$an_days])); ?> before.</p>

    <section class="gb-card gb-an-block">
        <h3><?php echo $an_days > 90 ? 'Visits per week' : 'Visits per day'; ?></h3>
        <div class="gb-an-chart" role="img" aria-label="<?php echo esc_attr(($an_days > 90 ? 'Weekly' : 'Daily') . ' visits, peaking at ' . $an_peak); ?>">
            <?php foreach ($an_series as $d => $n): ?>
                <span class="gb-an-bar" style="height:<?php echo esc_attr(max(2, round($n / $an_peak * 100))); ?>%" title="<?php echo esc_attr(($an_days > 90 ? 'Week of ' : '') . date_i18n('D j M', strtotime($d)) . ': ' . number_format_i18n($n) . ' visits'); ?>"></span>
            <?php endforeach; ?>
        </div>
        <div class="gb-an-chart-axis">
            <span><?php echo esc_html(date_i18n('j M', strtotime($an_first_day))); ?></span>
            <span>Busiest: <?php echo esc_html(number_format_i18n($an_peak)); ?></span>
            <span><?php echo esc_html(date_i18n('j M', strtotime($an_last_day))); ?></span>
        </div>
    </section>

    <div class="gb-an-two">
        <section class="gb-card gb-an-block">
            <h3>Where visitors come from</h3>
            <p class="gb-an-small">Counted when someone arrives from outside the website. Facebook and Instagram adverts show as "Facebook or Instagram ad".</p>
            <?php if ($an_sources): ?>
                <ul class="gb-an-list">
                    <?php foreach ($an_sources as $row): $share = $an_source_total ? round($row->hits / $an_source_total * 100) : 0; ?>
                        <li>
                            <span class="gb-an-list-label"><?php echo esc_html($row->label); ?></span>
                            <span class="gb-an-list-val"><?php echo esc_html(number_format_i18n($row->hits)); ?> <small><?php echo (int) $share; ?>%</small></span>
                            <span class="gb-an-meter"><span style="width:<?php echo (int) $share; ?>%"></span></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="gb-an-empty">Nothing yet for this period.</p>
            <?php endif; ?>
        </section>

        <section class="gb-card gb-an-block">
            <h3>Most viewed pages</h3>
            <?php if ($an_pages): ?>
                <table class="gb-an-table">
                    <tbody>
                    <?php foreach ($an_pages as $row): ?>
                        <tr>
                            <td>
                                <?php if (strpos($row->label, '/') === 0): ?>
                                    <a href="<?php echo esc_url(home_url($row->label)); ?>" target="_blank" rel="noopener"><?php echo esc_html($row->label === '/' ? 'Home page' : untrailingslashit($row->label)); ?></a>
                                <?php else: ?>
                                    <?php echo esc_html($row->label); ?>
                                <?php endif; ?>
                            </td>
                            <td class="gb-an-num"><?php echo esc_html(number_format_i18n($row->hits)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="gb-an-empty">Nothing yet for this period.</p>
            <?php endif; ?>
        </section>
    </div>

    <div class="gb-an-two">
        <?php foreach ($an_funnels as $who => $steps): $first = max(1, (int) $steps[0][1]); ?>
            <section class="gb-card gb-an-block">
                <h3><?php echo esc_html($who); ?>: from first look to paying</h3>
                <ol class="gb-an-funnel">
                    <?php foreach ($steps as $i => $step):
                        $pct = $i === 0 ? 100 : (int) round($step[1] / $first * 100);
                        $from_prev = $i === 0 ? '' : ($steps[$i - 1][1] > 0 ? round($step[1] / $steps[$i - 1][1] * 100) . '% of the step before' : '');
                        ?>
                        <li>
                            <span class="gb-an-funnel-bar" style="width:<?php echo esc_attr(max(4, min(100, $pct))); ?>%"></span>
                            <span class="gb-an-funnel-text"><strong><?php echo esc_html(number_format_i18n($step[1])); ?></strong> <?php echo esc_html($step[0]); ?></span>
                            <?php if ($from_prev !== ''): ?><small><?php echo esc_html($from_prev); ?></small><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="gb-an-two">
        <section class="gb-card gb-an-block">
            <h3>What people search for</h3>
            <?php if ($an_searches): ?>
                <ul class="gb-an-list gb-an-list-plain">
                    <?php foreach ($an_searches as $row): ?>
                        <li><span class="gb-an-list-label"><?php echo esc_html($row->label); ?></span><span class="gb-an-list-val"><?php echo esc_html(number_format_i18n($row->hits)); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="gb-an-empty">No searches yet for this period.</p>
            <?php endif; ?>
        </section>

        <section class="gb-card gb-an-block">
            <h3>Most viewed cleaners</h3>
            <?php if ($an_profiles): ?>
                <ul class="gb-an-list gb-an-list-plain">
                    <?php foreach ($an_profiles as $row): ?>
                        <li><span class="gb-an-list-label"><?php echo esc_html($row->label); ?></span><span class="gb-an-list-val"><?php echo esc_html(number_format_i18n($row->hits)); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="gb-an-empty">No profile views yet for this period.</p>
            <?php endif; ?>
        </section>
    </div>

    <section class="gb-card gb-an-block">
        <h3>Cookie banner answers</h3>
        <?php if ($an_consent_total): $yes_pct = (int) round($an_consent_yes / $an_consent_total * 100); ?>
            <div class="gb-an-split" aria-hidden="true"><span style="width:<?php echo $yes_pct; ?>%"></span></div>
            <p><strong><?php echo $yes_pct; ?>%</strong> allowed marketing cookies (<?php echo esc_html(number_format_i18n($an_consent_yes)); ?>), <strong><?php echo 100 - $yes_pct; ?>%</strong> chose essential only (<?php echo esc_html(number_format_i18n($an_consent_no)); ?>).</p>
            <p class="gb-an-small">Meta only hears about people who allow marketing cookies, so its numbers in Events Manager will be lower than the ones on this page. That is expected, and it is the law.</p>
        <?php else: ?>
            <p class="gb-an-empty">No answers yet for this period. Everybody is being asked again, because the banner now mentions Meta.</p>
        <?php endif; ?>
    </section>

    <?php /* Set-and-forget settings: folded away, but opened after a test or save so the result is visible. */ ?>
    <details class="gb-card gb-an-fold" id="meta"<?php echo ($an_capi_notice !== '' || (isset($_GET['msg']) && $_GET['msg'] === 'saved')) ? ' open' : ''; ?>>
        <summary>
            <span class="gb-an-fold-title">Meta Pixel settings</span>
            <span class="gb-an-fold-status">
                <span class="gb-an-dot <?php echo $an_pixel_on ? 'is-ok' : ''; ?>"></span><?php echo $an_pixel_on ? 'Pixel on' : 'Pixel off'; ?>
                <span class="gb-an-dot <?php echo $an_capi_set ? 'is-ok' : ''; ?>"></span><?php echo $an_capi_set ? 'Conversions API connected' : 'Conversions API not connected (optional)'; ?>
            </span>
        </summary>
        <div class="gb-an-fold-body">

        <?php if ($an_capi_notice !== '' && $an_capi_msg !== ''): ?>
            <div class="gb-an-alert is-<?php echo $an_capi_notice === 'ok' ? 'ok' : 'error'; ?>"><?php echo esc_html($an_capi_msg); ?></div>
        <?php endif; ?>

        <ul class="gb-an-status">
            <li class="<?php echo $an_pixel_on ? 'is-ok' : 'is-off'; ?>">
                <strong>Pixel:</strong>
                <?php if ($an_pixel_on): ?>
                    On, ID <?php echo esc_html($an_pixel_id); ?>. Installed on every page and fires only for visitors who allow marketing cookies.
                <?php elseif ($an_pixel_id !== ''): ?>
                    Switched off. Nothing is sent to Meta.
                <?php else: ?>
                    No pixel ID saved.
                <?php endif; ?>
            </li>
            <li class="<?php echo $an_capi_set ? 'is-ok' : 'is-off'; ?>">
                <strong>Conversions API:</strong>
                <?php if ($an_capi_set): ?>
                    Connected. Key actions are also sent from the server, so ad blockers do not hide them.
                    <?php if (is_array($an_last_test) && !empty($an_last_test['message'])): ?>
                        <br><small>Last test (<?php echo esc_html(date_i18n('j M, H:i', strtotime($an_last_test['at']))); ?>): <?php echo esc_html($an_last_test['message']); ?></small>
                    <?php endif; ?>
                <?php else: ?>
                    Not connected (optional, recommended). Add an access token below to make Meta's numbers more complete.
                <?php endif; ?>
            </li>
        </ul>

        <form method="post" class="gb-an-form">
            <input type="hidden" name="gb_save_action" value="save_settings">
            <input type="hidden" name="post_id" value="<?php echo (int) $settings_id; ?>">
            <input type="hidden" name="gb_return_tab" value="analytics">
            <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>

            <label class="gb-switch-row">
                <span class="gb-switch-text">
                    <strong>Meta Pixel</strong>
                    <span>Turn off to stop sending anything to Meta. Our own counts on this page carry on either way.</span>
                </span>
                <input type="hidden" name="_gb_meta_pixel_enabled" value="0">
                <input type="checkbox" class="gb-switch-input" name="_gb_meta_pixel_enabled" value="1" <?php checked(get_post_meta($settings_id, '_gb_meta_pixel_enabled', true) !== '0'); ?>>
                <span class="gb-switch" aria-hidden="true"></span>
            </label>

            <div class="gb-an-fields">
                <label>
                    <span>Pixel ID (also called the dataset ID)</span>
                    <input type="text" name="_gb_meta_pixel_id" value="<?php echo esc_attr($an_raw_pixel); ?>" inputmode="numeric" autocomplete="off">
                </label>
                <label>
                    <span>Conversions API access token</span>
                    <input type="password" name="_gb_meta_capi_token" value="" autocomplete="new-password" placeholder="<?php echo esc_attr($an_capi_set ? 'Saved. Leave blank to keep it' : 'Paste the token from Events Manager'); ?>">
                </label>
                <label>
                    <span>Test event code (optional, for checking)</span>
                    <input type="text" name="_gb_meta_test_event_code" value="<?php echo esc_attr($an_test_code); ?>" autocomplete="off" placeholder="e.g. TEST12345">
                </label>
            </div>
            <p class="gb-an-small">Only use a test event code while checking. Remove it afterwards, or real events from the server will be marked as tests.</p>
            <button type="submit" class="gb-btn-primary-os">Save Meta settings</button>
        </form>

        <?php if ($an_capi_set): ?>
            <div class="gb-an-actions">
                <form method="post">
                    <input type="hidden" name="gb_save_action" value="meta_capi_test">
                    <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>
                    <button type="submit" class="gb-btn-small-os">Send a test event</button>
                </form>
                <form method="post" onsubmit="return confirm('Disconnect the Conversions API? The pixel keeps working.');">
                    <input type="hidden" name="gb_save_action" value="meta_capi_disconnect">
                    <?php wp_nonce_field('gb_manager_action', 'gb_manager_nonce'); ?>
                    <button type="submit" class="gb-btn-small-os gb-an-danger">Disconnect</button>
                </form>
            </div>
        <?php endif; ?>

        <details class="gb-acc gb-an-help">
            <summary>How to check it is working in Meta</summary>
            <div class="gb-an-help-body">
                <ol>
                    <li>In Meta Business Suite, open <strong>Events Manager</strong> and choose the pixel (dataset) <?php echo esc_html($an_pixel_id ?: ''); ?>.</li>
                    <li>Open the <strong>Test events</strong> tab, type <strong><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></strong> and press <strong>Open website</strong>.</li>
                    <li>On the website, press <strong>Accept all</strong> on the cookie banner. Nothing is sent until you do.</li>
                    <li>Browse: open a cleaner's profile, search, and so on. Each action appears in Test events within a few seconds.</li>
                    <li>To check the Conversions API, copy the test code shown there into <strong>Test event code</strong> above, save, press <strong>Send a test event</strong>, then clear the code and save again.</li>
                </ol>
                <p><strong>To get a Conversions API token:</strong> Events Manager &rarr; the pixel &rarr; <strong>Settings</strong> &rarr; <strong>Conversions API</strong> &rarr; <strong>Generate access token</strong>. Paste it above and save.</p>
            </div>
        </details>
        </div>
    </details>

    <details class="gb-card gb-an-fold">
        <summary>
            <span class="gb-an-fold-title">What is tracked</span>
            <span class="gb-an-fold-status"><?php echo count($an_catalogue); ?> actions, all counted here; Meta hears about them only with marketing consent</span>
        </summary>
        <div class="gb-an-fold-body">
        <p class="gb-an-small">Every action below is counted on this page. The Meta event is what Meta receives, only from visitors who allow marketing cookies, and is what adverts can be optimised for.</p>
        <div class="gb-an-table-wrap">
            <table class="gb-an-table gb-an-catalogue">
                <thead><tr><th>Action</th><th>When it happens</th><th>Meta event</th><th class="gb-an-num"><?php echo esc_html($an_ranges[$an_days]); ?></th></tr></thead>
                <tbody>
                <?php foreach ($an_catalogue as $key => $row): ?>
                    <tr>
                        <td><strong><?php echo esc_html($row['label']); ?></strong></td>
                        <td><?php echo esc_html($row['where']); ?></td>
                        <td><code><?php echo esc_html($row['meta']); ?></code></td>
                        <td class="gb-an-num"><?php echo esc_html(number_format_i18n($an_hits($an_now, $key))); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="gb-an-small">Bookings and memberships also send their value in pounds, so Meta can report the return on ad spend. Signed-in visitors' email addresses are scrambled (hashed) before being sent by the Conversions API, so Meta can match them to accounts but never sees them.</p>
        </div>
    </details>
</div>
