=== DealersChoice Solutions ===
Contributors: mannixmarketing
Tags: inventory, boats, dealership, marina, inventory sync
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Syncs boat inventory from the DealersChoice IMS and displays it on dealer and marina websites.

== Description ==

A WordPress plugin that syncs boat inventory from the DealersChoice Inventory Management System (IMS) and displays it on dealer and marina websites: a filterable inventory listing, single boat pages, sliders, favorites, a "find your boat" quiz, finance calculators, and an optional "Reveal Price" lead form.

The plugin is free software (GPL v2 or later). Syncing inventory requires a DealersChoice subscription and API credentials.

= Requirements =

* WordPress: 6.0 or higher
* PHP: 8.1 or higher (8.3 recommended)
* Advanced Custom Fields: Required, free or Pro
* DealersChoice API: Client ID and API Key from a DealersChoice subscription

**Optional integrations**

Each feature turns itself off when its plugin is missing or not configured.

* Gravity Forms: Reveal Price form, quiz lead form
* Popup Maker: Reveal Price popup, repeat-viewer call-to-action popup
* Yoast SEO: Default SEO titles and descriptions for boats

= How sync works =

- Inventory syncs **twice daily** by WP-Cron, and on demand from the Sync Inventory page or WP-CLI.
- A sync is skipped when the IMS reports no changes since the last sync.
- Boats are matched by their IMS ID, then created or updated with their details, taxonomy terms and photos. Photos are downloaded once and reused.
- Synced fields are overwritten on every sync. Manual gallery order and the featured image are kept.
- Boats no longer in the IMS feed are set to **draft**. A daily cron permanently deletes boat drafts not modified in 6 months.
- **Local listings:** boats with **Is Local** checked are never unpublished by sync. Use this for boats entered by hand in WordPress.

= Shortcodes =

The **Insert DealersChoice** button above the Classic Editor builds these for you.

On/off attributes accept `true` or `false`, except where noted.

**`[dealerschoice_inventory]`**

The full inventory listing with filters, search, sort and pagination. The first page of results is rendered on the server so search engines see it; later updates load by AJAX.

* `posts_per_page` (Default: `12`): Boats per page (maximum 60)
* `show_filters` (Default: on): Show the filter sidebar. Use `0` to hide (`false` does not work).
* `show_search` (Default: on): Show the search box. Use `0` to hide.
* `show_sort` (Default: on): Show the sort dropdown. Use `0` to hide.
* `category` / `categories`: Boat type slug(s), comma separated
* `condition`: Condition slug(s)
* `status`: Status slug(s), e.g. `in-stock`
* `location`: Location slug(s)
* `make`: Make slug(s)
* `promotion`: Promotion slug(s)
* `lock`: Extra constraints as `key:slug,slug|key:slug`
* `lock_notice` (Default: `false`): Show a "Showing: ..." line for the constrained values

Filter attributes are enforced on the server and their filter controls are hidden, so visitors can't clear them.

`lock` keys: `location`, `condition`, `status`, `year`, `category`, `make`, `model`, `price`, `length`, `horsepower`, `capacity`, `promotion`.

    [dealerschoice_inventory posts_per_page="24"]
    [dealerschoice_inventory category="pontoon" condition="new"]
    [dealerschoice_inventory location="main-street" show_filters="0"]
    [dealerschoice_inventory lock="promotion:featured|condition:used" lock_notice="true"]

**`[dealerschoice_slider]`**

A carousel of boats.

* `limit` (Default: `6`): Number of boats
* `category`, `condition`, `location`, `make`, `year`, `promotion`: Slug filters
* `tax`: Extra constraints, same syntax as `lock`
* `orderby` (Default: `date`): `date`, `price`, `year`, `length` or `rand`
* `order` (Default: `DESC`): `ASC` or `DESC`
* `slides_to_show` (Default: `3`): Slides visible at full width
* `autoplay` (Default: `false`): Advance slides automatically
* `autoplay_speed` (Default: `3000`): Milliseconds between slides

When autoplay is on, the slider shows a play/pause button.

    [dealerschoice_slider limit="10" category="pontoon"]
    [dealerschoice_slider condition="new" orderby="price" order="ASC"]

**Other shortcodes**

* `[dealerschoice_filters]`: The filter sidebar on its own, for custom layouts
* `[dealerschoice_favorites]`: Boats the visitor saved as favorites (stored in their browser)
* `[dealerschoice_location_list]`: Buttons that set the visitor's preferred location
* `[dealerschoice_boat_quiz]`: "Find your boat" quiz. Attributes: `title`, `subtitle`, `submit_label`, `gravity_form_id` (optional lead form), `boat_count` (1-12, default 3)
* `[dealerschoice_finance_calculator]`: Payment calculator. Attributes: `title`, `default_amount`
* `[dealerschoice_reveal_price_message]`: Where Reveal Price status messages appear inside the popup

= Privacy =

Dealers should cover these in their privacy policy:

- **Favorites** are stored in the visitor's browser (localStorage). Favorite and unfavorite events are counted per boat for analytics with no personal data.
- **Reveal Price** sets a cookie (`dc_price_unlocked`) to remember unlocked prices. When a ZIP code list is set, the visitor's browser location is sent to BigDataCloud to look up their ZIP code.
- **Preferred location** and the **repeat-viewer popup** count are stored in the visitor's browser.
- Quiz answers are not stored by the plugin. They are only sent if the visitor submits the optional Gravity Form.

Template overrides, hooks, filters and other developer documentation are in README.md, included with the plugin.

== Installation ==

1. Install and activate Advanced Custom Fields.
2. Upload the `dealerschoice` folder to `/wp-content/plugins/` (or upload the zip under **Plugins > Add New > Upload Plugin**) and activate it.
   Activation registers the `boat` post type and taxonomies, adds a database index, creates the favorites analytics table, and schedules the sync.
3. Go to **DealersChoice > Settings**, enter your Client ID and API Key, and click **Save Changes**. Credentials are validated against the IMS before they are saved.
4. Go to **DealersChoice > Sync Inventory** and click **Sync Inventory Now** (or run `wp dealers-choice sync`).
5. Create a page at `/inventory/` containing `[dealerschoice_inventory]`. Redirects and quiz links point to this page.

Updates are delivered through the WordPress **Plugins** screen from the DealersChoice update server.

== Changelog ==

= 1.0.9 =
- Enhancement: The first page of `[dealerschoice_inventory]` results is now rendered server-side, so boats are in the initial HTML for search engines and AI crawlers. Filters, sort, search and pagination still update via AJAX.
- Enhancement: Inventory listings output `ItemList` structured data (JSON-LD) for the boats shown, kept in sync on every AJAX update.
- Enhancement: Pagination links now use real `?inventory_page=N` URLs (crawlable, open in new tab).
- Enhancement: Added a "Shop Other Locations" setting. When a multi-location marina filters by location, they can enable a "Shop Other Locations" block that shows up after the current location's inventory with similar boats available at their other locations.
- Fix: Single boat schema no longer outputs gated or hidden prices; boat schema type is now Product + Vehicle.
- Accessibility: Inventory result updates are announced to screen readers; current page is marked with `aria-current`.
- Fix: In-page anchor links on inventory pages no longer trigger an inventory reload.
- Minor plugin security fixes

= 1.0.8 =
- Minor plugin settings and display updates
- Fix: Reveal Price system now tracks displayed price in form submission
- Enhancement: Added special 'Promotions' taxonomy to allow custom flags like Featured, Quick Sales, etc.
- Enhancement: Admin listing table now allows for Stock and HIN search as well as Location filters
- Fix: When the shortcode sets a filter, clicking 'clear all filters' would clear even the set filters. Shortcode now locks those filters.
- Enhancement: Boat quiz results now include an optional number of boats to show in the slider
- Fix: Image import now checks order of images from the API and ensures gallery order matches
- Enhancement: Inventory Sliders now allow autoplay via shortcode attribute
- Enhancement: Added settings for Reveal Price system to unlock all prices or only the unit in which the button was clicked

= 1.0.7 =
- Minor plugin settings and display updates

= 1.0.6 =
- Added boat finance calculator with two options: full financial data and quick calculator on the single boat listing

= 1.0.5 =
- Added boat weight sync and display

= 1.0.4 =
- Fixed image sync to store last image sync only on successful sync
- Fixed image sync to use a proper file extension when a URL does not include extension
- Updated search functionality to include search by HIN or Stock Number
- Added Custom Offers settings to the plugin settings display
- Fixed display of Favorites graph

= 1.0.3 =
- Updated check for shortcodes to ensure proper enqueuing of scripts and styles.

= 1.0.2 =
- Updated the reveal price popup system to include the stock number and boat name to pass to the form notification.

= 1.0.1 =
- Added plugin update checker

= 1.0.0 =
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
