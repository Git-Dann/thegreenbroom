<?php
/**
 * The branded emails, rendered by the site itself.
 *
 * The designs live in Brevo as templates, which only helps while Brevo is
 * sending. The moment it is not - refusing the site, missing a template, over
 * its daily limit - the backup routes used to send the bare plain-text
 * version: a wall of text with a raw link in it, which looks like phishing and
 * is exactly the moment somebody is already wondering whether the site works.
 *
 * So the same HTML is kept in the theme (email-templates/, one file per kind
 * of email, named after the key in gb_get_brevo_template_ids()) and filled in
 * here with the same parameters Brevo would be given. Whatever route an email
 * takes, it looks the same.
 *
 * Only the small part of Brevo's template language these files use is
 * understood:
 *
 *   {{ params.name }}
 *   {{ params.name|default:"fallback" }}
 *   {{ params.name|linebreaksbr }}
 *
 * Anything else is left blank rather than shown as code.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function gb_email_template_dir() {
    return get_template_directory() . '/email-templates';
}

/**
 * Send Brevo's stored copy of each template, or the site's own?
 *
 * The site's own, since 29 September. Brevo's template engine escapes the
 * output of its own linebreaksbr filter, so every support reply and manager
 * message with more than one paragraph arrived with "<br /><br />" printed in
 * the middle of it. Its copies also drift: they hold whatever was pasted in
 * last, and have to be re-pasted by hand whenever a design changes. With the
 * site rendering every design, there is one copy of each, in the theme, and
 * every route sends exactly the same email. Brevo still delivers them.
 *
 * Return true from this filter to go back to Brevo's stored templates.
 */
function gb_email_use_brevo_templates() {
    return (bool) apply_filters( 'gb_email_use_brevo_templates', false );
}

/**
 * @return string The finished HTML, or '' if there is no local copy of this one.
 */
function gb_render_local_email_template( $key, array $params = array() ) {
    $key = sanitize_key( (string) $key );

    if ( $key === '' ) {
        return '';
    }

    $file = gb_email_template_dir() . '/' . $key . '.html';

    if ( ! is_readable( $file ) ) {
        return '';
    }

    $html = (string) file_get_contents( $file );

    if ( $html === '' ) {
        return '';
    }

    return preg_replace_callback(
        '/\{\{\s*params\.([a-zA-Z0-9_]+)\s*((?:\|[^}]*)?)\}\}/',
        function ( $m ) use ( $params ) {
            $name    = $m[1];
            $filters = trim( $m[2] );
            $value   = isset( $params[ $name ] ) && is_scalar( $params[ $name ] ) ? trim( (string) $params[ $name ] ) : '';

            if ( $value === '' && preg_match( '/\|\s*default\s*:\s*"([^"]*)"/', $filters, $d ) ) {
                $value = $d[1];
            }

            if ( $value === '' ) {
                return '';
            }

            // Links go into href attributes, so they are escaped as links.
            if ( substr( $name, -4 ) === '_url' ) {
                return esc_url( $value );
            }

            /*
             * Braces as entities: the finished HTML goes to Brevo, whose
             * template engine would otherwise treat somebody typing "{{" in a
             * message as the start of a placeholder and blank it out.
             */
            $text = str_replace( array( '{', '}' ), array( '&#123;', '&#125;' ), esc_html( $value ) );

            if ( strpos( $filters, 'linebreaksbr' ) !== false ) {
                return nl2br( $text );
            }

            return $text;
        },
        $html
    );
}

/**
 * Every link param a template relies on, for checking Brevo's copy has them.
 *
 * @return string[]
 */
function gb_email_template_required_params( $key ) {
    $file = gb_email_template_dir() . '/' . sanitize_key( (string) $key ) . '.html';

    if ( ! is_readable( $file ) ) {
        return array();
    }

    preg_match_all( '/\{\{\s*params\.([a-zA-Z0-9_]+_url)\b/', (string) file_get_contents( $file ), $m );

    return array_values( array_unique( $m[1] ) );
}

/**
 * Swap whatever content type the headers asked for with HTML.
 */
function gb_email_headers_as_html( $headers ) {
    if ( ! is_array( $headers ) ) {
        $headers = $headers === '' || $headers === null ? array() : preg_split( "/\r\n|\n|\r/", (string) $headers );
    }

    $headers = array_values( array_filter( $headers, function ( $h ) {
        return is_string( $h ) && trim( $h ) !== '' && stripos( ltrim( $h ), 'content-type:' ) !== 0;
    } ) );

    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    return $headers;
}

/**
 * A readable plain-text copy of an HTML email.
 *
 * Sent alongside the HTML: mail with no text part scores worse with spam
 * filters, and some people read mail as text. Links keep their address.
 */
function gb_email_html_to_text( $html ) {
    $html = (string) $html;

    // Only the visible body; the <head> is styles and a title.
    if ( preg_match( '/<body[^>]*>(.*)<\/body>/is', $html, $m ) ) {
        $html = $m[1];
    }

    $html = preg_replace( '/<!--.*?-->/s', '', $html );
    $html = preg_replace( '/<(style|script)[^>]*>.*?<\/\1>/is', '', $html );
    // The preheader is for the inbox preview, not the body.
    $html = preg_replace( '/<div[^>]*class="preheader"[^>]*>.*?<\/div>/is', '', $html );

    // "Label (https://...)", or just the address when the label is the address.
    $html = preg_replace_callback( '/<a\s[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', function ( $m ) {
        $label = trim( wp_strip_all_tags( $m[2] ) );
        $url   = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );

        if ( stripos( $url, 'mailto:' ) === 0 ) {
            return $label;
        }

        // An image link (the logo): its alt text, without the address.
        if ( $label === '' && preg_match( '/<img[^>]*alt="([^"]*)"/i', $m[2], $alt ) ) {
            return $alt[1];
        }

        return ( $label === '' || $label === $url ) ? $url : $label . ' (' . $url . ')';
    }, $html );

    $html = preg_replace( '/<br\s*\/?>/i', "\n", $html );
    $html = preg_replace( '/<\/(p|div|h[1-6]|tr|li|table)>/i', "\n\n", $html );

    $text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
    $text = preg_replace( "/[ \t]+/", ' ', $text );
    $text = preg_replace( "/ *\n */", "\n", $text );
    $text = preg_replace( "/\n{3,}/", "\n\n", $text );

    return trim( $text );
}
