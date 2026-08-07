# Brevo email templates

These files are standalone HTML templates for Brevo's HTML editor. They use the same branded header, live website logo, responsive table layout, inline-safe styling and Outlook VML button fallback as the verification template.

## Templates

| File | Suggested Brevo name | Subject | Parameters |
| --- | --- | --- | --- |
| `01-password-reset.html` | `GB - Password Reset` | Reset your Green Broom password | `first_name`, `reset_url` |
| `02-booking-confirmed-customer.html` | `GB - Booking Confirmed - Customer` | Your Green Broom booking is confirmed | `first_name`, `booking_url`, `service`, `professional_name`, `date`, `time`, `duration`, `address`, `total` |
| `03-new-booking-cleaner.html` | `GB - New Booking - Cleaner` | New Green Broom booking confirmed | `first_name`, `booking_url`, `service`, `customer_name`, `date`, `time`, `duration`, `address` |
| `04-support-reply.html` | `GB - Support Reply` | A reply from The Green Broom | `first_name`, `reply_message`, `inbox_url` |
| `05-new-message-manager.html` | `GB - New Message - Manager` | New message for The Green Broom | `context`, `sender_name`, `sender_email`, `subject`, `message`, `dashboard_url` |
| `06-subscription-expired.html` | `GB - Subscription Expired` | Your Green Broom subscription has expired | `first_name`, `support_url` |

The verification template created previously remains the seventh template. The first-name templates use `{{ params.first_name|default:"there" }}` so the greeting remains natural when no first name is available.

Keep the templates inactive until the corresponding Brevo template IDs are connected to the website and tested with real parameters.
