=== 3RING Domain Manager ===
Contributors: 3ring
Tags: domains, registrar, dns, renewals, portfolio
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track company domains, registrars, DNS, renewals, documents, and expiry alerts from the WordPress admin.

== Description ==

**3RING Domain Manager** is a WordPress admin tool from [3RING Studios](https://3ring.com) for managing a company domain portfolio across multiple registrars and providers.

Features include:

* Domain portfolio with registration, expiry, usage, and ownership fields
* Providers (registrar, DNS, hosting, email) with multi-type support
* Renewals, notes, and document uploads tied to each domain
* Manual DNS record tracking per domain
* Email alerts for upcoming expiries and review-due dates (WP-Cron + opportunistic admin checks)
* Optional frontend shortcode `[domain-list]` for a public/read-only domain table
* CSV import/export for portfolio data
* Configurable brand color for the admin UI

On activation, the plugin creates its custom database tables automatically.

**Plugin Administrator:** The WordPress user who activates the plugin is granted full plugin admin capabilities. Other users can be granted Domain Manager access from their user profile.

== Installation ==

1. Upload the `3ring-domain-manager` folder to `/wp-content/plugins/`, or install the zip via **Plugins → Add New → Upload Plugin**.
2. Activate **3RING Domain Manager** through the **Plugins** screen.
3. Open **Domain Manager** in the admin menu.
4. Optionally grant Domain Manager access on user profiles, and configure **Settings** (alert windows, brand color, etc.).
5. Optional: add `[domain-list]` to a page to show the portfolio on the front end.

== Frequently Asked Questions ==

= Does this connect to registrar APIs? =

No. You enter and maintain registrar, DNS, and renewal data manually (or via CSV). API sync is not included.

= Who receives expiry alert emails? =

Users with Domain Manager capabilities (and configured recipients in settings). Alerts use WordPress `wp_mail()`.

= Who becomes the Plugin Administrator? =

The site user who activates the plugin receives Plugin Administrator capabilities. Additional Domain Managers can be granted access from each user’s profile screen.

= Will uninstall delete my data? =

By default, options are removed and tables are kept. You can enable dropping tables on uninstall in Settings if desired.

== Screenshots ==

1. Dashboard with portfolio counters and domain list
2. Domains list with client-side column sorting
3. Domain edit form with renewals, notes, documents, and DNS records
4. Providers management
5. Settings including alert windows and brand color

== Changelog ==

= 1.0.0 =
* Initial public release: domain portfolio, providers, renewals, documents, DNS records, expiry/review email alerts, CSV import/export, and `[domain-list]` shortcode

== Upgrade Notice ==

= 1.0.0 =
Initial public release.
