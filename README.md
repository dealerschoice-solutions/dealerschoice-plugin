# DealersChoice Solutions

DealersChoice Solutions is a WordPress plugin that allows you to manage dealership inventory on your website. With a valid DealersChoice API, inventory can be synced from your Dealership Management System (DMS) to your website automatically.

## Features

- **Automatic Inventory Sync**: Connect to your DMS and automatically sync boat listings to WordPress
- **Custom Post Type**: Dedicated "Boat" post type with custom taxonomies for advanced filtering
- **Advanced Custom Fields**: Custom fields for comprehensive boat specifications
- **Smart Sync Logic**: Timestamp-based sync prevents unnecessary processing
- **Image Management**: Automatic image download and caching to prevent duplicates
- **Local Listings**: Add manual inventory that won't be affected by API syncs
- **Draft Management**: Old synced listings are moved to draft (not deleted), with automatic cleanup after 6 months
- **Manual Sync Trigger**: Admin UI and WP-CLI commands to trigger sync on demand
- **Comprehensive Logging**: Track all sync operations, errors, and changes
- **Performance Optimized**: Database indexes and efficient queries for large inventories
- **Client Favorites**: LocalStorage based favorites system to allow for saved inventory

## Requirements

- **WordPress**: 6.0 or higher
- **PHP**: 7.4 or higher
- **Advanced Custom Fields (ACF)**: Required (free or Pro version)
- **DealersChoice API**: Valid API credentials from DealersChoice subscription

## Installation

1. **Install Advanced Custom Fields**
   - Install and activate the Advanced Custom Fields plugin from the WordPress plugin repository
   - Either the free or Pro version will work

2. **Install DealersChoice Plugin**
   - Upload the plugin files to `/wp-content/plugins/dealerschoice/` directory
   - Or install through the WordPress plugins screen directly

3. **Activate the Plugin**
   - Activate through the 'Plugins' screen in WordPress
   - The plugin will automatically create the boat post type and taxonomies
   - Database indexes will be added for optimal performance

4. **Configure API Credentials**
   - Navigate to **Dealers Choice** > **Settings** in the WordPress admin
   - Enter your Client ID and API Key
   - Click "Save Settings" to validate your credentials

5. **Run Initial Sync**
   - Go to **Dealers Choice** > **Sync Inventory**
   - Click the "Sync Inventory Now" button
   - Or use WP-CLI: `wp dealers-choice sync`

## Usage

### Out-of-the-Box Display

The plugin provides complete, ready-to-use templates for displaying your inventory:

#### Inventory Page Setup

**The plugin uses a page-based approach for the main inventory listing:**

1. Create a new WordPress page (e.g., "Inventory", "Our Boats", etc.)
2. Add the inventory shortcode to the page:
   ```
   [dealerschoice_inventory]
   ```
3. Publish the page - that's it!

The page will display the complete inventory with filters, search, sorting, and pagination.

#### Single Boat Pages
- Each boat gets its own detail page at `/boat/boat-type/boat-name/`
- Displays gallery, specifications, pricing, and related boats
- Mobile-responsive with modern design

### Shortcodes

Embed inventory anywhere using shortcodes:

#### Full Inventory Listing
```
[dealerschoice_inventory]
```

**Attributes:**
- `posts_per_page="12"` - Boats per page (default: 12)
- `show_filters="true"` - Show filter sidebar (default: true)
- `show_search="true"` - Show search box (default: true)
- `show_sort="true"` - Show sort dropdown (default: true)
- `category="pontoon"` - Filter by category slug
- `categories="pontoon,bow-rider"` - Filter by multiple categories
- `condition="new"` - Filter by condition slug
- `location="tampa"` - Filter by location slug
- `make="sea-ray"` - Filter by make slug
- `status="In Stock"` - Filter by availability status

**Examples:**
```
[dealerschoice_inventory posts_per_page="20"]
[dealerschoice_inventory category="pontoon" condition="new"]
[dealerschoice_inventory location="tampa" show_filters="false"]
```

#### Boat Slider/Carousel
```
[dealerschoice_slider]
```

**Attributes:**
- `limit="6"` - Number of boats (default: 6)
- `category="pontoon"` - Filter by category
- `condition="new"` - Filter by condition
- `location="tampa"` - Filter by location
- `make="sea-ray"` - Filter by make
- `orderby="date"` - Sort by: date, price, year, length (default: date)
- `order="DESC"` - Sort direction: ASC or DESC (default: DESC)

**Examples:**
```
[dealerschoice_slider limit="10" category="pontoon"]
[dealerschoice_slider condition="new" orderby="price" order="ASC"]
```

#### Favorite Inventory
```
[dealerschoice_favorites]
```
Displays the boats from a list of IDs stored in LocalStorage that the user has identified as inventory to watch.

#### Standalone Filters
```
[dealerschoice_filters]
```
Displays just the filter sidebar for custom layouts.

### Theme Customization

The plugin follows WordPress template hierarchy for easy customization:

#### Override Templates
Copy any template from the plugin to your theme to customize it:

**Plugin templates:**
```
dealerschoice/templates/
├── single-boat.php        # Single boat detail page
├── content-boat.php       # Boat card/block
└── filters.php            # Filter sidebar
```

**Copy to your theme:**
```
your-theme/dealerschoice/
├── single-boat.php        # Your custom detail page
├── content-boat.php       # Your custom boat card
└── filters.php            # Your custom filters
```

The plugin will automatically use your theme's version if it exists.

#### Available Hooks

**Actions (for adding content):**
- `dealerschoice_before_archive` - Before archive page content
- `dealerschoice_after_archive` - After archive page content
- `dealerschoice_archive_header` - In archive header area
- `dealerschoice_before_filters` - Before filter sidebar
- `dealerschoice_after_filters` - After filter sidebar
- `dealerschoice_filters_start` - Start of filters template
- `dealerschoice_filters_end` - End of filters template
- `dealerschoice_before_results` - Before results area
- `dealerschoice_after_results` - After results area
- `dealerschoice_before_single_boat` - Before single boat content
- `dealerschoice_after_single_boat` - After single boat content
- `dealerschoice_single_boat_header_before` - Before boat header
- `dealerschoice_single_boat_header_after` - After boat header
- `dealerschoice_before_gallery` - Before image gallery
- `dealerschoice_after_gallery` - After image gallery
- `dealerschoice_before_boat_info` - Before boat info section
- `dealerschoice_after_boat_info` - After boat info section
- `dealerschoice_contact_cta` - In contact CTA area
- `dealerschoice_before_boat_card` - Before individual boat card
- `dealerschoice_after_boat_card` - After individual boat card
- `dealerschoice_before_template_part` - Before loading template part
- `dealerschoice_after_template_part` - After loading template part

**Filters (for modifying data):**
- `dealerschoice_posts_per_page` - Modify posts per page (default: 12)

**Example:**
```php
// Add custom content after boat header
add_action('dealerschoice_single_boat_header_after', function($post_id) {
    echo '<div class="custom-notice">Special Financing Available!</div>';
});

// Change posts per page
add_filter('dealerschoice_posts_per_page', function($per_page) {
    return 24;
});
```

### Manual Sync Trigger

### Admin Interface

**Settings Page** (`/wp-admin/admin.php?page=dealers-choice-settings`)
- Configure API credentials (Client ID and API Key)
- Test API connection with real-time validation

**Sync Inventory Page** (`/wp-admin/admin.php?page=dealers-choice-sync`)
- Manually trigger inventory sync
- View last sync timestamp
- Enable/disable logging
- View recent sync logs with context details
- Clear log history

### WP-CLI Commands

For advanced users and automated workflows:

```bash
# Sync inventory from API
wp dealers-choice sync

# Clean up draft boats older than 6 months
wp dealers-choice cleanup

# View recent sync logs
wp dealers-choice logs
wp dealers-choice logs --lines=50
wp dealers-choice logs --level=error

# Display plugin statistics
wp dealers-choice stats
```

### Automatic Sync

The plugin includes a daily CRON job that automatically deletes draft boats older than 6 months. To set up automatic inventory sync, add a custom cron job or use a WordPress cron management plugin.

### Local Listings

To add boats that won't be affected by API syncs:
1. Create a new Boat post
2. Set the "Is Local" field to true/checked
3. These boats will be excluded from unpublishing during sync

## Custom Post Type & Fields

**Post Type**: `boat`

**Taxonomies**:
- Boat Type
- Condition (New/Used)
- Location
- Year
- Make
- Model
- Price Range
- Length
- Horsepower

**ACF Field Groups**:
- General Information (25+ fields including price, specifications, features)
- Photos & Videos (gallery and video embed fields)

## Software License (GPL v2 or later)

This WordPress plugin is licensed under the GNU General Public License v2 or later (GPL-2.0+). The plugin source code is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation.

**Key GPL Rights:**
- You may use, study, modify, and distribute this plugin
- You may redistribute modified versions under the same GPL license
- Source code must remain available and open

**GPL Disclaimer:**
This plugin is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

## Service License Agreement

While the plugin software is GPL-licensed, access to our premium services, API endpoints, updates, and support requires a separate paid subscription governed by our [Terms of Service](https://www.dealerschoicesolutions.com/terms-of-use/).

## Changelog
### 1.0.4
- Fixed image sync to store last image sync only on successful sync
- Fixed image sync to use a proper file extension when a URL does not include extension
- Updated search functionality to include search by HIN or Stock Number
- Added Custom Offers settings to the plugin settings display
- Fixed display of Favorites graph

### 1.0.3
- Updated check for shortcodes to ensure proper enqueuing of scripts and styles.

### 1.0.2
- Updated the reveal price popup system to include the stock number and boat name to pass to the form notification.

### 1.0.1
- Added plugin update checker

### 1.0.0
- Initial release
- Automatic inventory sync from DealersChoice API
- Custom boat post type with custom taxonomies
- Advanced Custom Fields for storing boat details
- Manual sync trigger (admin UI and WP-CLI)
- Comprehensive logging system
- Performance optimizations with database indexes
- Smart image caching
- Local listing support
- Automatic draft cleanup

## Support & Documentation

- **Feedback**: [Submit feedback or feature requests](https://www.dealerschoicesolutions.com/feedback/)
- **Website**: [https://www.dealerschoicesolutions.com](https://www.dealerschoicesolutions.com)
- **Terms**: [Terms of Use](https://www.dealerschoicesolutions.com/terms-of-use/)

## Credits

**Author**: DealersChoice, by Mannix Marketing  
**Plugin URI**: [https://www.dealerschoicesolutions.com](https://www.dealerschoicesolutions.com)  
**Version**: 1.0.0
