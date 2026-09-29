# Branded email templates

One file per kind of email, named after its key in `gb_get_brevo_template_ids()`
(`inc/cleaner-profiles.php`). They are used twice:

1. **In Brevo**, pasted into Brevo's HTML editor as templates. The site sends
   Brevo the template id and the parameters, and Brevo fills them in.
2. **By the site itself** (`inc/email-render.php`), whenever Brevo is not the
   one sending: Brevo refusing the site, a template missing in Brevo, or the
   backup mailbox and the web server doing the work. The same parameters are
   filled in here, so an email looks the same whichever route it takes.

Because of (2), **keep these files and Brevo's copies in step.** Change the
wording here, then paste the file into the matching Brevo template.
Settings → Email & delivery checks each Brevo template exists, is active and
uses the link parameter the site sends.

| File | Brevo id | Parameters |
| --- | --- | --- |
| `verification.html` | 1 | `first_name`, `verification_url` |
| `password_reset.html` | 2 | `first_name`, `reset_url` |
| `booking_customer.html` | 3 | `first_name`, `booking_url`, `service`, `professional_name`, `date`, `time`, `duration`, `address`, `total` |
| `booking_cleaner.html` | 4 | `first_name`, `booking_url`, `service`, `customer_name`, `date`, `time`, `duration`, `address` |
| `support_reply.html` | 5 | `first_name`, `reply_message`, `inbox_url` |
| `manager_message.html` | 6 | `context`, `sender_name`, `sender_email`, `subject`, `message`, `dashboard_url` |
| `subscription_expired.html` | 7 | `first_name`, `support_url` |

Only this much of Brevo's template language is understood locally:
`{{ params.name }}`, `{{ params.name|default:"text" }}` and
`{{ params.name|linebreaksbr }}`. Blank parameters are not sent, so the
`default` text shows instead of a gap.

The `.htaccess` stops these files being served over the web.
