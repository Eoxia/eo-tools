=== EO Tools ===
Contributors: eoxia
Tags: maintenance, coming soon, 404, landing page, holding page
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Display custom landing pages on your WordPress site: Coming Soon, Maintenance and 404.

== Description ==

EO Tools is a lightweight plugin dedicated to displaying custom landing pages
on your WordPress site.

Three modes are available:

* **Coming Soon**: displays a "Coming soon" page to visitors while the site is
  being built. Administrators keep browsing the site normally.
* **Maintenance**: displays a temporary unavailability page and returns an
  HTTP 503 status code to visitors and search engines.
* **404**: replaces the default 404 error page with a custom screen including a
  button back to the home page.

Each page is customizable: title, text (with lightweight formatting), visual
style (Minimalist, Gradient, Glass effect) and colors.

The Coming Soon and Maintenance modes are mutually exclusive. A badge in the
admin bar indicates the active mode at any time.

= Privacy =

The plugin does not load any external resource and does not make any outgoing
network request. No personal data is collected.

== Installation ==

1. Upload the `eo-tools` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to the "Landing Pages" menu to configure and enable the modes you need.

== Frequently Asked Questions ==

= Do administrators see the maintenance page? =

No. Users with the `manage_options` capability keep accessing the site normally.
A preview button lets you see each page.

= Can I enable Coming Soon and Maintenance at the same time? =

No, these two modes are mutually exclusive. Enabling one disables the other.

== Changelog ==

= 1.0.0 =
* Initial lightweight release: Coming Soon, Maintenance and 404 pages.
