=== Customizable Payment Link Generator ===
Contributors: Refat Rahman
Donate link: https://refat.ovh/donate
Tags: payment link, customizable payment, custom amount
Requires at least: 1.0.0
Tested up to: 1.0.2
Stable tag: 1.0.2
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

A PipraPay module to generate a customizable payment link where users can enter a custom amount and other details to pay.

**Key Features:**
* **Enable/Disable Link**: Easily enable or disable the payment link at any time.
* **Custom Title & Description**: Set a custom title and description for the payment page.
* **Flexible Currency Options**: Choose a specific currency for your link or automatically use your PipraPay system's default currency.
* **Custom Logo & Favicon**: Add your brand logo and favicon to the payment page.
* **Custom Footer**: Display a custom footer text.
* **Instruction Notice**: Show a notice with instructions for the payment.
* **Pretty Link**: Use a clean, user-friendly URL for your payment link.
* **Customizable Form Fields**: Choose to show or hide fields for customer name and contact information.

**Why Use Customizable Payment Link Generator?**
* **Flexibility**: Allow your customers to pay any amount they want, perfect for donations, invoicing, or custom services.
* **Branding**: Customize the payment page with your own logo, favicon, and text to maintain a consistent brand experience.
* **User-Friendly**: A simple and intuitive interface for both you and your customers, making payments quick and easy.
* **Easy to Manage**: Configure all the settings from a single, user-friendly page in your admin dashboard.

== Installation ==

1.  Download the plugin.
2.  Upload the `customizable-payment-link-generator` folder to your PipraPay `modules` section.
3.  Activate the plugin from PipraPay's module settings.
4.  Go to **Admin Dashboard → Module → Customizable Payment Link Generator** to configure the settings.

== Changelog ==

= 1.0.2 =
* **Feature:** Added "Use system currency" option.
* **Fix:** Resolved critical bug causing the admin page to not load.
* **Fix:** Corrected an issue where the system's default currency was not being applied correctly.

= 1.0.1 =
* **Fix:** Resolved a PHP error that prevented the plugin page from loading by ensuring the database connection is properly established.
* **Fix:** Addressed an issue where the "Use system currency" option was not functioning as expected.

= 1.0.0 =
* Initial release