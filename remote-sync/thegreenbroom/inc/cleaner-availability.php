<?php
/**
 * When a cleaner is free, as a grid of days against parts of the day.
 *
 * This is a claim about a typical week, not a calendar of real dates, and the
 * wording on the profile says so. Nothing here books anything: a customer still
 * messages the cleaner to agree a time. The shape is deliberately small enough
 * that a cleaner will actually keep it up to date.
 *
 * The slot keys are stored as an array under _gb_availability_slots. Every read
 * and every write passes through the whitelist below, so a stale or hand-edited
 * key can never reach the page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gb_availability_days() {
    return array(
        'mon' => 'Mon',
        'tue' => 'Tue',
        'wed' => 'Wed',
        'thu' => 'Thu',
        'fri' => 'Fri',
        'sat' => 'Sat',
        'sun' => 'Sun',
    );
}

function gb_availability_periods() {
    return array(
        'morning'   => array( 'label' => 'Morning',   'hint' => '8am to 12pm' ),
        'afternoon' => array( 'label' => 'Afternoon', 'hint' => '12pm to 5pm' ),
        'evening'   => array( 'label' => 'Evening',   'hint' => '5pm to 9pm' ),
    );
}

/**
 * Every slot key that is allowed to exist, as a flat list.
 */
function gb_availability_slot_keys() {
    $keys = array();

    foreach ( array_keys( gb_availability_days() ) as $day ) {
        foreach ( array_keys( gb_availability_periods() ) as $period ) {
            $keys[] = $day . '_' . $period;
        }
    }

    return $keys;
}

function gb_get_availability_slots( $cleaner_id ) {
    $stored = get_post_meta( (int) $cleaner_id, '_gb_availability_slots', true );

    if ( ! is_array( $stored ) ) {
        return array();
    }

    return array_values( array_intersect( gb_availability_slot_keys(), $stored ) );
}

/**
 * Save a submitted set of slots.
 *
 * An empty submission clears the grid, which is the whole point: a cleaner who
 * is no longer free on Saturdays has to be able to say so. The caller is
 * responsible for only invoking this when the form that owns the grid was
 * actually submitted, so an unrelated save cannot wipe it.
 */
function gb_save_availability_slots( $cleaner_id, $submitted ) {
    $submitted = is_array( $submitted ) ? $submitted : array();
    $submitted = array_map( 'sanitize_key', wp_unslash( $submitted ) );
    $clean     = array_values( array_intersect( gb_availability_slot_keys(), $submitted ) );

    if ( empty( $clean ) ) {
        delete_post_meta( (int) $cleaner_id, '_gb_availability_slots' );
        return array();
    }

    update_post_meta( (int) $cleaner_id, '_gb_availability_slots', $clean );

    return $clean;
}

/**
 * The tick-box grid, shared by the cleaner's own edit screen and the manager's.
 *
 * The field name has no leading underscore on purpose: the manager dashboard's
 * global meta saver only picks up keys starting with _gb_, and it would never
 * see an all-unticked grid at all, since unchecked boxes submit nothing. Both
 * callers handle this field explicitly instead.
 */
function gb_render_availability_editor( $cleaner_id, $field_name = 'gb_availability_slots' ) {
    $selected = gb_get_availability_slots( $cleaner_id );
    $days     = gb_availability_days();
    $periods  = gb_availability_periods();
    ?>
    <div class="gb-avail-editor">
        <table class="gb-avail-table">
            <thead>
                <tr>
                    <th scope="col"><span class="screen-reader-text">Part of day</span></th>
                    <?php foreach ( $days as $day_key => $day_label ) : ?>
                        <th scope="col"><?php echo esc_html( $day_label ); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $periods as $period_key => $period ) : ?>
                    <tr>
                        <th scope="row">
                            <?php echo esc_html( $period['label'] ); ?>
                            <small><?php echo esc_html( $period['hint'] ); ?></small>
                        </th>
                        <?php foreach ( $days as $day_key => $day_label ) :
                            $slot = $day_key . '_' . $period_key;
                            $id   = 'gb-avail-' . (int) $cleaner_id . '-' . $slot;
                            ?>
                            <td>
                                <input type="checkbox"
                                       id="<?php echo esc_attr( $id ); ?>"
                                       name="<?php echo esc_attr( $field_name ); ?>[]"
                                       value="<?php echo esc_attr( $slot ); ?>"
                                       <?php checked( in_array( $slot, $selected, true ) ); ?>>
                                <label for="<?php echo esc_attr( $id ); ?>">
                                    <span class="screen-reader-text"><?php echo esc_html( $day_label . ' ' . $period['label'] ); ?></span>
                                </label>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="gb-avail-note">Tick the parts of the week you are usually free. This is a guide for customers, not a booking calendar &mdash; they still message you to agree a time.</p>
    </div>
    <?php
}

/**
 * The read-only grid for the public profile. Prints nothing when the cleaner
 * has not filled it in, rather than an empty week that reads as "never free".
 */
function gb_render_availability_summary( $cleaner_id ) {
    $selected = gb_get_availability_slots( $cleaner_id );

    if ( empty( $selected ) ) {
        return;
    }

    $days    = gb_availability_days();
    $periods = gb_availability_periods();
    ?>
    <div class="gb-avail-view">
        <table class="gb-avail-table gb-avail-table-view">
            <caption class="screen-reader-text">Parts of the week this cleaner is usually available</caption>
            <thead>
                <tr>
                    <th scope="col"><span class="screen-reader-text">Part of day</span></th>
                    <?php foreach ( $days as $day_key => $day_label ) : ?>
                        <th scope="col"><?php echo esc_html( $day_label ); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $periods as $period_key => $period ) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html( $period['label'] ); ?></th>
                        <?php foreach ( $days as $day_key => $day_label ) :
                            $slot = $day_key . '_' . $period_key;
                            $free = in_array( $slot, $selected, true );
                            ?>
                            <td class="<?php echo $free ? 'is-free' : 'is-busy'; ?>">
                                <span aria-hidden="true"><?php echo $free ? '&#10003;' : '&ndash;'; ?></span>
                                <span class="screen-reader-text"><?php echo esc_html( $day_label . ' ' . $period['label'] . ': ' . ( $free ? 'usually available' : 'not usually available' ) ); ?></span>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="gb-avail-note">A guide to a typical week. Message <?php echo esc_html( get_the_title( $cleaner_id ) ); ?> to agree an actual time.</p>
    </div>
    <?php
}
