# Branded email templates

One file per kind of email, named after its key in `gb_get_brevo_template_ids()`
(`inc/cleaner-profiles.php`).

**These files are the emails.** The site fills them in itself
(`inc/email-render.php`) and hands the finished HTML to whichever route is
sending: Brevo, the backup mailbox or the web server. So every route sends
exactly the same email, and changing a design means changing it here, once.

Brevo's stored templates are no longer used (see
`gb_email_use_brevo_templates()`). Its template engine escaped its own
`linebreaksbr` filter, so multi-paragraph support replies arrived with
`<br /><br />` printed in them, and its copies drifted from these. The ids in
the table are kept so they can be switched back on with that filter.

The logo is `uploads/2026/09/tgb-email-logo.png` (from `deploy-extras/`): the
wordmark on its own white card, because Gmail's dark mode recolours
backgrounds and text but not images, and the plain transparent logo turned
into dark grey on black.

| File | Brevo id | Parameters |
| --- | --- | --- |
| `verification.html` | 1 | `first_name`, `verification_url` |
| `password_reset.html` | 2 | `first_name`, `reset_url` |
| `booking_customer.html` | 3 | `first_name`, `booking_url`, `service`, `professional_name`, `date`, `time`, `duration`, `address`, `total` |
| `booking_cleaner.html` | 4 | `first_name`, `booking_url`, `service`, `customer_name`, `date`, `time`, `duration`, `address` |
| `support_reply.html` | 5 | `first_name`, `reply_message`, `inbox_url` |
| `manager_message.html` | 6 | `context`, `sender_name`, `sender_email`, `subject`, `message`, `dashboard_url` |
| `subscription_expired.html` | 7 | `first_name`, `support_url` |

Only this much of Brevo's template language is understood:
`{{ params.name }}`, `{{ params.name|default:"text" }}` and
`{{ params.name|linebreaksbr }}`. Blank parameters are not sent, so the
`default` text shows instead of a gap.

These files can be fetched over the web. That is harmless: they hold only
the design and placeholders, the same HTML every recipient already receives,
never anybody's details or links. Do not put anything private in them.
(A folder `.htaccess` deny was tried and this host does not honour it.)
