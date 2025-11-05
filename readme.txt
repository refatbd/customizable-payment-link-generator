=== Customizable Payment Link Generator ===
Contributors: Refat Rahman
Donate link: https://refat.ovh/donate
Tags: payment link, customizable payment, custom amount, fixed amount, multiple links, form builder
Requires at least: 1.0.0
Tested up to: 1.0.3
Stable tag: 3.1.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A PipraPay module to generate multiple, highly customizable payment links for fixed prices, custom amounts, or simple products.

== Description ==

A PipraPay module to generate multiple, highly customizable payment links. You can create unlimited links, each with its own settings for fixed prices, custom user-entered amounts, or even simple products with stock control.

== Key Features ==

* **Multiple Links**: Create and manage unlimited, unique payment links.
* **Advanced Amount Controls**: Set **Fixed Amounts**, **Custom Amounts**, or use Min/Max limits and Suggested Amount "chips".
* **Stock & Quantity Control**: Enable a quantity field and manage inventory for fixed-price items. The link automatically disables when an item is sold out.
* **Advanced Form Builder**: Add custom fields (Text, Text Area, Dropdowns, Checkboxes, Radios) and mark them as required.
* **Admin Dashboard**: A full admin area with a **Reports Tab** (filterable by date) and a **Transactions Tab**.
* **Transaction Management**: Filter transactions by status, date, or search term, and **bulk delete** selected transactions.
* **Branding**: Customize each link with its own Title, Description, Logo, and Favicon.
* **Custom Redirects**: Redirect users to a custom URL after a successful payment.
* **Flexible Currency**: Use the system currency or set a specific currency per link.
* **Instruction Notice**: Show a custom notice with instructions on the payment page.
* **Pretty Link (Permalinks)**: Use clean, user-friendly URLs (e.g., `yourdomain.com/pay`).
* **Built-in Update Checker**: Get notified of new versions from your admin dashboard.

== Why Use This Plugin? ==

* **Flexibility**: Perfect for donations, invoicing, custom services, or selling simple products with inventory.
* **Data Collection**: Collect any extra data you need (like an ID, size, or custom note) directly with the payment.
* **Branding**: Customize each payment page with your own logo, favicon, and text for a consistent brand experience.
* **Easy to Manage**: Configure all settings, track all transactions, and view reports from a single, user-friendly interface.

== Installation ==

1.  **Download** the plugin.
2.  **Upload** the `customizable-payment-link-generator` folder to your PipraPay `modules` section (e.g., `pp-content/plugins/modules/`).
3.  **Activate** the plugin from PipraPay's module settings.
4.  Go to **Admin Dashboard → Module → Customizable Payment Link Generator**.
5.  Follow the on-screen instructions to configure your settings.

== Changelog ==

= 3.1.0 =
* **Feature:** Added **Stock & Quantity Control** for fixed-price links, allowing for simple product sales and inventory management.
* **Feature:** Added **Advanced Transaction Filtering** to the Transactions tab (filter by Status, Date Range, and Search).
* **Feature:** Added **Bulk Deletion** for transactions on the Transactions tab.
* **Feature:** Expanded **Advanced Form Builder** to support Text Area, Dropdown Select, Checkboxes, and Radio Buttons (previously only Text).
* **Feature:** Added "Items Sold" count to the Reports page.

= 3.0.0 =
* **Feature:** Implemented **Multiple Link Support**. You can now create unlimited links.
* **Feature:** Added **Advanced Amount Controls** per link (Fixed, Custom, Min/Max, Suggested Amounts).
* **Feature:** Added **Basic Form Builder** to add custom text fields.
* **Feature:** Added **Custom Success Redirect URL** option per link.
* **Feature:** Added **Reporting Page** in admin to see revenue and transaction counts per link.
* **Refactor:** Major database update to store settings on a per-link basis in a new table.
* **Refactor:** Overhauled Admin UI with a tabbed interface (Links, Reports) and new forms.

= 1.0.3 =
* **Fix:** Replaced deprecated `FILTER_SANITIZE_STRING` constant in `link.php` to resolve PHP 8+ deprecation notices and "headers already sent" warnings.
* **Fix:** Corrected an "Undefined array key default_currency" PHP warning in `functions.php` that occurred when saving settings with "Use System Currency" enabled.
* **Tweak:** Moved the update checker to the bottom of the admin page and added a developer information block.

= 1.0.2 =
* **Feature:** Added "Use system currency" option.
* **Fix:** Resolved critical bug causing the admin page to not load.
* **Fix:** Corrected an issue where the system's default currency was not being applied correctly.

= 1.0.1 =
* **Fix:** Resolved a PHP error that prevented the plugin page from loading by ensuring the database connection is properly established.
* **Fix:** Addressed an issue where the "Use system currency" option was not functioning as expected.

= 1.0.0 =
* Initial release.

== About the Author ==

This plugin is developed and maintained by **Refat Rahman**.
* **GitHub:** [github.com/refatbd](https://github.com/refatbd)
* **Facebook:** [facebook.com/rjrefat](https://www.facebook.com/rjrefat)