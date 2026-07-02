=== DealersChoice Solutions ===
Contributors: mannixmarketing
Tags: inventory, dealership, boats, custom post type, api sync
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync and manage dealership inventory from your DMS to WordPress. Perfect for boat dealers, ATV dealers, and vehicle dealerships.

== Description ==

DealersChoice Solutions is a powerful WordPress plugin that allows you to automatically sync and display dealership inventory (boats, ATVs, vehicles, etc.) on your website. Connect to your Dealership Management System (DMS) through our API and keep your website inventory up-to-date automatically.

= Key Features =

* **Automatic Inventory Sync** - Connect to your DMS and automatically sync listings to WordPress
* **Custom Post Type** - Dedicated "Boat" post type with 9 taxonomies for advanced filtering
* **30+ Custom Fields** - Comprehensive boat specifications using Advanced Custom Fields
* **Smart Sync Logic** - Timestamp-based sync prevents unnecessary processing
* **Image Management** - Automatic image download with intelligent caching
* **Local Listings** - Add manual inventory that won't be affected by API syncs
* **Draft Management** - Old listings moved to draft (not deleted), with automatic cleanup
* **Manual Sync Trigger** - Admin UI button and WP-CLI commands
* **Comprehensive Logging** - Track all sync operations, errors, and changes
* **Performance Optimized** - Database indexes and efficient queries for large inventories

= Requirements =

* Advanced Custom Fields (ACF) plugin - Free or Pro version
* DealersChoice API credentials (requires separate subscription)

= WP-CLI Commands =

For advanced users:

* `wp dealers-choice sync` - Run inventory sync
* `wp dealers-choice cleanup` - Delete old draft boats
* `wp dealers-choice logs` - View recent logs
* `wp dealers-choice stats` - Display statistics

== Installation ==

1. **Install Required Plugin**
   * Install and activate Advanced Custom Fields (ACF) plugin
   * Available for free in the WordPress plugin repository

2. **Install DealersChoice**
   * Upload plugin files to `/wp-content/plugins/dealerschoice/`
   * Or install through WordPress admin (Plugins > Add New)

3. **Activate Plugin**
   * Activate through 'Plugins' screen in WordPress
   * Post type, taxonomies, and database indexes are created automatically

4. **Configure API Credentials**
   * Go to Dealers Choice > Settings
   * Enter your Client ID and API Key
   * Click Save to validate credentials

5. **Run Initial Sync**
   * Go to Dealers Choice > Sync Inventory
   * Click "Sync Inventory Now" button

== Frequently Asked Questions ==

= Does this plugin require Advanced Custom Fields? =

Yes, ACF is required for this plugin to function. You can use either the free or Pro version of ACF. The plugin will display an admin notice if ACF is not installed.

= Do I need a DealersChoice subscription? =

Yes. While the plugin software is free (GPL-licensed), access to the DealersChoice API requires a separate paid subscription. Visit https://www.dealerschoicesolutions.com for pricing and terms.

= How often does inventory sync? =

Inventory syncs when you manually trigger it through the admin interface or WP-CLI. The plugin uses timestamp-based sync, so if your inventory hasn't changed since the last sync, it will skip processing.

= What happens to boats that are removed from my DMS? =

Boats that are no longer in your DMS inventory are automatically moved to draft status (not deleted). A daily CRON job deletes drafts older than 6 months.

= Can I add boats manually that won't be affected by sync? =

Yes! Enable the "Is Local" field when creating/editing a boat post. Local listings are excluded from the sync unpublishing process.

= Does this work with large inventories? =

Yes. The plugin includes performance optimizations:
* Database indexes for fast queries
* Image caching to prevent duplicate downloads
* Efficient batch processing
* Optimized taxonomy counting

= Can I see what happened during a sync? =

Yes. Go to Dealers Choice > Sync Inventory to view recent logs. Logs show detailed information about what was created, updated, or unpublished, plus any errors.

= What are the WP-CLI commands? =

Available commands:
* `wp dealers-choice sync` - Run inventory sync
* `wp dealers-choice cleanup` - Delete old draft boats
* `wp dealers-choice logs --lines=50` - View 50 recent log entries
* `wp dealers-choice logs --level=error` - View only errors
* `wp dealers-choice stats` - Display plugin statistics

== Changelog ==
### 1.0.3
- Updated check for shortcodes to ensure proper enqueuing of scripts and styles.

### 1.0.2
- Updated the reveal price popup system to include the stock number and boat name to pass to the form notification.

= 1.0.1 =
* Added plugin update notification system

= 1.0.0 =
* Initial release
* Automatic inventory sync from DealersChoice API
* Custom boat post type with 9 taxonomies
* 30+ Advanced Custom Fields for boat specifications
* Manual sync trigger (admin UI and WP-CLI)
* Comprehensive logging system with admin viewer
* Performance optimizations with database indexes
* Smart image caching to prevent duplicates
* Local listing support
* Automatic draft cleanup (6 months)
* Activation/deactivation hooks
* ACF dependency check with admin notice

== Upgrade Notice ==
### 1.0.3
- Updated check for shortcodes to ensure proper enqueuing of scripts and styles.

### 1.0.2
- Updated the reveal price popup system to include the stock number and boat name to pass to the form notification.

### 1.0.1
- Added plugin update checker

= 1.0.0 =
Initial release of DealersChoice Solutions plugin.

== License ==

This plugin is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or any later version.

This plugin is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

**Note**: Premium services and API access require separate subscription. Visit https://www.dealerschoicesolutions.com for service terms and pricing.