=== WP Client Reports ===
Contributors: thejester12
Donate link: https://switchwp.com/plugins/wp-client-reports/
Tags:  reports, client reports, reporting, statistics, analytics, maintenance, updates, plugin updates, theme updates
Requires at least: 5.3.0
Tested up to: 6.0.2
Stable tag: 1.0.15
Requires PHP: 5.6.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Send professional client maintenance report emails, including software update statistics for plugins, themes, and WordPress.

== Description ==

Deliver confidence and prove your value routinely to clients and stakeholders.

== Client Website Maintenance Reports ==

The perfect maintenance report builder plugin for agencies, freelancers and site maintainers who update their client's sites on a weekly, monthly, or quarterly basis. The plugin tracks what updates have happened and records them daily.

You can use the cleanly designed reporting analytics screen to show the updates that have happened within amounts of time such as last month, this month, last 30 days, or a custom length.

Send a professional looking email including update statistics whenever you complete updates to show the value of your work to your client or other site stakeholders. No PDF's here, just a nicely designed email.

== Pro Version ==

[WP Client Reports Pro](https://switchwp.com/plugins/wp-client-reports/?utm_source=wporg&utm_medium=readme&utm_campaign=wpclientreports) allows you to self brand the maintenance report email with your logo and company color. It allows you to send reports out automatically on a weekly or monthly schedule. It also adds a number of optional integrations with other services and plugins to display their statistics.

- Site Maintenance Notes
- Google Analytics
- Gravity Forms, Ninja Forms, WP Forms, Fomidable Forms & Contact Form 7
- Uptime Robot & Pingdom
- UpdraftPlus, BackWPup & BackupBuddy, WPEngine Backups
- Mailchimp
- SearchWP
- WooCommerce, Easy Digital Downloads, GiveWP & Stripe

Have an idea that should be added? Let me know at [SwitchWP](https://switchwp.com/plugins/wp-client-reports/?utm_source=wporg&utm_medium=readme&utm_campaign=wpclientreports).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-client-reports` directory, or install the zipped plugin through the WordPress plugins screen directly.
2. Activate the WP Client Reports plugin through the 'Plugins' screen in WordPress.
3. Use the Settings->WP Client Reports screen to configure the default settings.
4. Use the Dashboard->Reports screen to view update statistics.


== Frequently Asked Questions ==

= Where Are My Past Updates? =

WordPress by default does not track when updates have happened. WP Client Reports adds the functionality to do that, but that means that it cannot report on updates that happened before the plugin was installed.


== Screenshots ==

1. The WP Client Reports main report screen
2. Easily switch dates to view states for any time period
3. Send an html email with friendly statistics to your client
3. Adds a handy dashboard widget
4. Manage email settings and which sections are enabled


== Changelog ==

= 1.0.15 =
* Add optional "Reply To" field for email settings
* Fix issue with report intro removing paragraphs and line breaks

= 1.0.14 =
* Ensure that jquery ui calendar next/prev month buttons are visible regardless of conflicts with other plugins

= 1.0.13 =
* Make report title a required field
* Update moment.js

= 1.0.12 =
* Fix an issue with formatting of dates in reports where placeholders [YEAR], [MONTH], and [DAY] are used
* Bumped WP version requirement to 5.3.0 related to use of wp_timezone() function

= 1.0.11 =
* Fix issues with meta box headers in newer versions of WordPress

= 1.0.10 =
* New Feature: Ability to use [YEAR], [MONTH], and [DATE] shortcodes in email title and description.
* New Feature: Loading spinners while reports are loading
* New Feature: New fields for email: Send From Email, Send From Name, and Footer
* Fix issues with timezones using UTC offsets
* Fix issues with HTML in email titles and descriptions
* Fix some untranslatable strings
* Fix some issues with settings screen when Easy Digital Downloads is installed

= 1.0.9 =
* Change the way reports are sent to allow for sending via schedules in Pro version

= 1.0.8 =
* Fix some issues with calendar when jquery-ui styles are enqued by another plugin

= 1.0.7 =
* Fix issues with description formatting
* Fix issue when selecting single date in chooser

= 1.0.6 =
* Fix saving issue with default report title

= 1.0.5 =
* Check for updates after each plugin/theme update

= 1.0.4 =
* Allow title of report to be customized
* Fix issue with sending to multiple email addresses

= 1.0.3 =
* Restructure parts of the plugin for more consistency
* Add content stats section

= 1.0.2 =
* Rethink data loading for report page and email

= 1.0.1 =
* Security enhancements
* Readying plugin for pro version

= 1.0.0 =
* Initial Version


== Upgrade Notice ==

= 1.0.0 =
Initial Version
