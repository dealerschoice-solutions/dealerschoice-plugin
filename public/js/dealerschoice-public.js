/**
 * DealersChoice Public JavaScript
 * 
 * Handles dynamic inventory filtering, search, pagination, and UI interactions.
 * 
 * @package DealersChoice
 * @subpackage Public
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Inventory Search Class
     * 
     * Manages all inventory filtering, searching, sorting, and pagination functionality
     */
    class InventorySearch {
        constructor() {
            // Initialize filter state from checkboxes
            this.filterLocations = this.getCheckedValues('inventory-location');
            this.filterConditions = this.getCheckedValues('inventory-condition');
            this.filterStatuses = this.getCheckedValues('inventory-status');
            this.filterYears = this.getCheckedValues('inventory-year');
            this.filterCategories = this.getCheckedValues('inventory-category');
            this.filterMakes = this.getCheckedValues('inventory-make');
            this.filterModels = this.getCheckedValues('inventory-model');
            this.filterPrices = this.getCheckedValues('inventory-price');
            this.filterLengths = this.getCheckedValues('inventory-length');
            this.filterHorsepowers = this.getCheckedValues('inventory-horsepower');
            this.filterCapacities = this.getCheckedValues('inventory-capacity');

            this.filters = {
                location: this.filterLocations,
                condition: this.filterConditions,
                status: this.filterStatuses,
                year: this.filterYears,
                category: this.filterCategories,
                make: this.filterMakes,
                model: this.filterModels,
                price: this.filterPrices,
                length: this.filterLengths,
                horsepower: this.filterHorsepowers,
                capacity: this.filterCapacities,
            };

            // Locked taxonomy constraints declared by the shortcode's
            // 'promotion' or 'lock' attribute. Held separately from
            // this.filters because these must
            // survive clearAllFilters(), pagination and sorting. Sent on every
            // request and re-validated server-side; this is a convenience copy,
            // not the source of truth.
            this.locked = this.readLockedConstraints();

            // Where to send a visitor who picks a different location from a
            // header location selector while this page's location is locked.
            // Emitted by the shortcode only when location is locked and this
            // page is not itself the site-wide inventory page.
            this.locationSwitchUrl = $('#inventory-wrapper').attr('data-location-switch-url') || '';

            this.sortBy = $('#inventory-sort').val() || 'date-desc';
            this.searchQuery = $('#q').val() || '';
            this.currentPage = 1;
            this.postsPerPage = parseInt($('#inventory-wrapper').data('posts-per-page'), 10) || 12;
            this.searchTimeout = null;

            // Restore page from URL on load (supports browser back/forward and bookmarking)
            const urlInventoryPage = parseInt(new URLSearchParams(window.location.search).get('inventory_page'), 10);
            if (urlInventoryPage > 1) {
                this.currentPage = urlInventoryPage;
            }

            this.init();
        }

        /**
         * Set initial query from URL
         */
        setInitialQuery(query) {
            if (query) {
                $('#q').val(query);
                this.searchQuery = query;
            }
        }

        /**
         * Get checked values for a filter type
         */
        getCheckedValues(name) {
            return $('input[name="' + name + '"]:checked')
                .map(function() { return $(this).val(); })
                .get();
        }

        /**
         * Read locked taxonomy constraints from the wrapper's data-locked
         * attribute, written server-side by the inventory shortcode.
         *
         * Returns {} on anything unexpected. That is intentionally the loose
         * direction: the constraint is re-derived and enforced in PHP, so a
         * malformed attribute here degrades to an unscoped request that the
         * server still refuses to widen.
         */
        readLockedConstraints() {
            const raw = $('#inventory-wrapper').attr('data-locked');

            if (!raw) {
                return {};
            }

            try {
                const parsed = JSON.parse(raw);
                return (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) ? parsed : {};
            } catch (e) {
                return {};
            }
        }

        /**
         * Is this filter key locked by the shortcode?
         *
         * Anything locked is enforced server-side and cannot be widened from
         * the client, so the UI must not offer or silently apply a competing
         * value for the same key: it would simply AND to zero results.
         */
        isLocked(key) {
            return Object.prototype.hasOwnProperty.call(this.locked, key)
                && Array.isArray(this.locked[key])
                && this.locked[key].length > 0;
        }

        /**
         * Terms locked for a given key, or an empty array.
         */
        lockedTerms(key) {
            return this.isLocked(key) ? this.locked[key] : [];
        }

        /**
         * Initialize event handlers
         */
        init() {
            this.handleEvents();
            this.initFilterUI();
        }

        /**
         * Initialize Filter UI interactions (Toggles & Show More)
         */
        initFilterUI() {
            // Widget Collapse/Expand
            $('.dealerschoice-filters .widget-title button').on('click', function(e) {
                e.preventDefault();
                const $button = $(this);
                const $content = $('#' + $button.attr('aria-controls'));
                const isExpanded = $button.attr('aria-expanded') === 'true';

                $button.attr('aria-expanded', !isExpanded);
                $content.slideToggle(200);
            });

            // Show More / Show Less logic
            $('.dealerschoice-filters .widget-content').each(function() {
                const $content = $(this);
                const $items = $content.find('.inventory-filter-wrapper:not([style*="display:none"])');
                const limit = 5;

                if ($items.length > limit) {
                    // Hide items beyond limit
                    $items.slice(limit).addClass('hidden-option');

                    // Create toggle button
                    const $toggleBtn = $('<button type="button" class="show-more-link" aria-expanded="false">Show More</button>');
                    $content.append($toggleBtn);

                    $toggleBtn.on('click', function() {
                        const $btn = $(this);
                        const isExpanded = $btn.attr('aria-expanded') === 'true';

                        if (isExpanded) {
                            // Collapse
                            $items.slice(limit).addClass('hidden-option');
                            $btn.text('Show More').attr('aria-expanded', 'false');
                        } else {
                            // Expand
                            $items.slice(limit).removeClass('hidden-option');
                            $btn.text('Show Less').attr('aria-expanded', 'true');
                            // Focus the first revealed item for accessibility
                            $items.eq(limit).find('input').first().focus();
                        }
                    });
                }
                if ($items.length === 0) {
                    $content.closest('.widget').hide();
                }
            });
        }

        /**
         * Set up all event handlers
         */
        handleEvents() {
            // Filter checkbox changes
            $('#inventory-filters input[type="checkbox"]').on('change', (e) => this.updateFilters(e));

            // Sort dropdown change
            $('#inventory-sort').on('change', (e) => {
                this.sortBy = $(e.target).val();
                this.resetPagination();
                this.fetchResults();
            });

            // Search input with debounce
            $('#inventory-search').on('input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.searchQuery = $(e.target).val();
                    this.resetPagination();
                    this.fetchResults();
                }, 300);
            });

            // Search form submit (prevent default)
            $('#inventory-search').on('submit', function(e) {
                e.preventDefault();
            });

            // Pagination clicks (delegated)
            $('.pagination-wrapper').on('click', 'a', (e) => {
                e.preventDefault();
                const $target = $(e.target).closest('a');
                const page = $target.data('page');
                if (page) {
                    this.currentPage = page;
                    this.syncPageToUrl(true);
                    this.fetchResults();
                    this.smoothScrollToTop();
                }
            });

            // Browser back/forward: restore the page the user was on
            window.addEventListener('popstate', (e) => {
                const page = (e.state && e.state.inventory_page)
                    ? e.state.inventory_page
                    : (parseInt(new URLSearchParams(window.location.search).get('inventory_page'), 10) || 1);
                this.currentPage = page;
                this.fetchResults();
            });

            // Clear all filters button
            $(document).on('click', '#clear-all-filters', () => {
                this.clearAllFilters();
            });

            // Mobile filter toggle
            $('#mobile-filter-toggle button').on('click', (e) => {
                e.preventDefault();
                $('#inventory-filters').toggle().toggleClass('active');
                $('#mobile-filter-toggle').toggleClass('active');
            });

            // Mobile filter close
            $('#mobile-filter-close button').on('click', (e) => {
                e.preventDefault();
                $('#inventory-filters').toggle().toggleClass('active');
                $('#mobile-filter-toggle').toggleClass('active');
            });

            // Gallery thumbnail clicks
            $(document).on('click', '.gallery-thumb', function() {
                const fullImage = $(this).data('full-image');
                $('.gallery-main-image').attr('src', fullImage);
                $('.gallery-thumb').removeClass('active');
                $(this).addClass('active');
            });
        }

        /**
         * Update filters when checkboxes change
         */
        updateFilters(e) {
            const $checkbox = $(e.target);
            const filterName = $checkbox.attr('name').split('-')[1];
            const filterValue = $checkbox.val();

            // Handle "All" checkbox
            if (filterValue === 'all') {
                if ($checkbox.is(':checked')) {
                    // Uncheck all other checkboxes in this group
                    $('input[name="inventory-' + filterName + '"]').not($checkbox).prop('checked', false);
                    this.filters[filterName] = [];
                } else {
                    // Do nothing if unchecking "All"
                    return;
                }
            } else {
                // Uncheck "All" if it's checked
                const $allCheckbox = $('#inventory-' + filterName + '-all');
                if ($allCheckbox.is(':checked')) {
                    $allCheckbox.prop('checked', false);
                }

                // Update filter array
                if ($checkbox.is(':checked')) {
                    if (!this.filters[filterName].includes(filterValue)) {
                        this.filters[filterName].push(filterValue);
                    }
                } else {
                    this.filters[filterName] = this.filters[filterName].filter(
                        val => val !== filterValue
                    );
                }
            }

            this.resetPagination();
            this.fetchResults();
        }

        /**
         * Clear all active filters
         */
        clearAllFilters() {
            // Uncheck all filter checkboxes
            $('#inventory-filters input[type="checkbox"]').prop('checked', false);

            // Reset filter state. this.locked is deliberately NOT reset: a page
            // scoped by the shortcode's 'lock' attribute must stay scoped after
            // the visitor clears their own filters.
            for (let key in this.filters) {
                this.filters[key] = [];
            }

            // Clear search
            $('#inventory-search').val('');
            this.searchQuery = '';
            
            this.resetPagination();
            this.fetchResults();
        }

        /**
         * Fetch filtered results via AJAX
         */
        fetchResults() {
            // Clone filters to avoid mutating original
            const filters = JSON.parse(JSON.stringify(this.filters));

            // Drop any key already covered by a locked constraint. The sidebar
            // still pre-checks (and hides) the widget for a constrained
            // taxonomy, so without this the same term would be sent twice and
            // WP_Query would build two identical tax_query clauses, adding a
            // redundant JOIN to every request. Safe because a constrained
            // taxonomy has no visible control, so there is no legitimate
            // visitor selection to preserve.
            const self = this;
            Object.keys(this.locked).forEach(function(key) {
                if (Object.prototype.hasOwnProperty.call(filters, key) && self.isLocked(key)) {
                    delete filters[key];
                }
            });
            // If filters.id exists and is a non-empty array, ensure all values are strings/ints
            if (filters.id && Array.isArray(filters.id) && filters.id.length > 0) {
                // Remove empty/invalid values
                filters.id = filters.id.filter(function(id) { return id !== null && id !== undefined && id !== ''; });
            }
            const ajaxData = {
                action: 'search_inventory',
                filters: filters,
                locked: this.locked,
                sortBy: this.sortBy,
                query: this.searchQuery,
                currentPage: this.currentPage,
                postsPerPage: this.postsPerPage
            };

            // Show loading state
            this.updateInventoryList(
                '<div class="loading-spinner">' +
                '<i class="fa-light fa-arrows-rotate-reverse"></i> ' +
                'Loading inventory...' +
                '</div>'
            );
            this.updateInventoryPagination('');

            // Make AJAX request
            $.ajax({
                url: dealersChoicePublic.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    if (response.success) {
                        this.updateInventoryList(response.data.results);
                        this.updateInventoryPagination(response.data.pagination);
                        // Re-init favorite buttons in new content
                        if (window.DealersChoiceFavorites && typeof window.DealersChoiceFavorites.initFavoriteButtons === 'function') {
                            window.DealersChoiceFavorites.initFavoriteButtons('#inventory-results');
                        }
                    } else {
                        this.updateInventoryList(
                            '<div class="no-results">' +
                            '<h2>Error</h2>' +
                            '<p>There was an error loading the inventory. Please try again.</p>' +
                            '</div>'
                        );
                    }
                },
                error: (error) => {
                    console.error('Error fetching inventory:', error);
                    this.updateInventoryList(
                        '<div class="no-results">' +
                        '<h2>Error</h2>' +
                        '<p>There was an error loading the inventory. Please try again.</p>' +
                        '</div>'
                    );
                }
            });
        }

        /**
         * Update inventory list HTML
         */
        updateInventoryList(html) {
            $('#inventory-results').html(html);
            // Notify other scripts (e.g. reveal-price) that new inventory HTML is in the DOM.
            $(document).trigger('dc:inventoryRendered');
        }

        /**
         * Update pagination HTML
         */
        updateInventoryPagination(html) {
            $('.pagination-wrapper').html(html);
        }

        /**
         * Reset to first page
         */
        resetPagination() {
            this.currentPage = 1;
            this.syncPageToUrl(false);
        }

        /**
         * Sync current page to the browser URL.
         * push=true  → pushState  (pagination click: creates a Back-button entry)
         * push=false → replaceState (filter/sort/search reset: updates in place, no extra history entry)
         */
        syncPageToUrl(push) {
            const url = new URL(window.location.href);
            if (this.currentPage > 1) {
                url.searchParams.set('inventory_page', this.currentPage);
            } else {
                url.searchParams.delete('inventory_page');
            }
            const state = { inventory_page: this.currentPage };
            if (push) {
                history.pushState(state, '', url.toString());
            } else {
                history.replaceState(state, '', url.toString());
            }
        }

        /**
         * Smooth scroll to top of results
         */
        smoothScrollToTop() {
            $('html, body').animate({
                scrollTop: $('#inventory-results').offset().top - 100
            }, 300);
        }

        /**
         * Set filters programmatically (for URL parameters)
         */
        setFilters(filters) {
            this.filters = filters;
            this.fetchResults();
        }
    }

    /**
     * Get URL parameters
     * Supports both array-style (?param[]=value) and regular parameters
     */
    function getUrlParameters(paramName) {
        const url = new URL(window.location.href);
        const searchParams = new URLSearchParams(url.search);
        const values = [];

        searchParams.forEach((value, key) => {
            if (key === paramName + '[]' || key === paramName) {
                values.push(value);
            }
        });

        return values;
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        
        // Only initialize on pages with inventory filters
        if ($('#inventory-filters').length === 0) {
            return;
        }

        // Prevent the browser from trying to restore scroll position on AJAX-paginated pages;
        // content height changes during load make auto-restoration unreliable.
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }

        // Initialize inventory search
        const inventorySearch = new InventorySearch();

        // Seed the current history entry with page state so popstate fires correctly when
        // the user navigates back to this page from a detail page.
        history.replaceState({ inventory_page: inventorySearch.currentPage }, '', window.location.href);

        // Check for URL parameters and apply filters
        if (window.location.search) {
            // Note: 'year' is NOT in formInputs — ?year= is a WordPress reserved query var
            // that triggers date-based archive rewrites. Use ?boat_year= instead.
            const formInputs = [
                'location', 'condition', 'status', 'category',
                'make', 'model', 'price', 'length', 'horsepower'
            ];
            
            const filters = {
                location: [],
                condition: [],
                status: [],
                year: [],      // internal AJAX key; populated below from ?boat_year=
                category: [],
                make: [],
                model: [],
                price: [],
                length: [],
                horsepower: [],
            };

            let hasFilters = false;

            formInputs.forEach(function(input) {
                // A URL parameter must never fight a locked constraint. The
                // lock is enforced server-side, so applying a competing value
                // for the same key would AND to zero results with no way back.
                // URL parameters are a convenience for presetting filters on
                // the site-wide inventory page; on a scoped page the scope wins.
                if (inventorySearch.isLocked(input)) {
                    return;
                }

                const param = getUrlParameters(input);
                if (param.length > 0) {
                    hasFilters = true;
                    param.forEach(function(value) {
                        filters[input].push(value);
                        $('#inventory-' + input + '-' + value).prop('checked', true);
                    });
                }
            });

            // ?boat_year= → internal 'year' filter key (avoids WordPress reserved query var)
            const boatYearParam = getUrlParameters('boat_year');
            if (boatYearParam.length > 0 && !inventorySearch.isLocked('year')) {
                hasFilters = true;
                boatYearParam.forEach(function(value) {
                    filters.year.push(value);
                    $('#inventory-year-' + value).prop('checked', true);
                });
            }

            // Apply filters if any were found in URL
            if (hasFilters) {
                inventorySearch.setFilters(filters);
            } else {
                // Otherwise just fetch initial results
                inventorySearch.fetchResults();
            }
        } else {
            // Check LocalStorage for preferred location.
            //
            // Skipped entirely when location is locked. This assignment (not
            // append) is why a returning visitor whose saved location differs
            // from a locked page's location would otherwise land on an empty
            // listing having done nothing: the saved slug would AND against
            // the locked term. On a location-scoped page the page wins.
            if (!inventorySearch.isLocked('location')) {
                const savedLocation = localStorage.getItem('dc_preferred_location');
                if (savedLocation && savedLocation !== 'all') {
                    // Pre-check the box in the sidebar
                    $('#inventory-location-' + savedLocation).prop('checked', true);
                    // Update the search instance
                    inventorySearch.filters.location = [savedLocation];
                }
            }

            // Fetch initial results
            inventorySearch.fetchResults();
        }

        $(document).on('dc_location_changed', function(e, slug) {
            // On a location-locked page the constraint is enforced server-side
            // and the sidebar has no location widget, so filtering in place
            // would strand the visitor on a permanent zero-results listing.
            // Hand off to the site-wide inventory page with the chosen location
            // preselected instead, which is what ?location= exists for.
            if (inventorySearch.isLocked('location')) {
                const lockedLocations = inventorySearch.lockedTerms('location');

                // Already looking at the locked location: nothing to do.
                if (slug !== 'all' && lockedLocations.indexOf(slug) !== -1) {
                    return;
                }

                // No destination available (e.g. this IS the inventory page).
                // Ignoring the change is not great, but it beats emptying the
                // listing with no way to recover.
                if (!inventorySearch.locationSwitchUrl) {
                    return;
                }

                let target = inventorySearch.locationSwitchUrl;

                if (slug !== 'all') {
                    target += (target.indexOf('?') === -1 ? '?' : '&')
                        + 'location=' + encodeURIComponent(slug);
                }

                window.location.href = target;

                return;
            }

            // Uncheck all location filters
            $('input[name="inventory-location"]').prop('checked', false);
            inventorySearch.filters.location = [];

            if (slug !== 'all') {
                $('#inventory-location-' + slug).prop('checked', true);
                inventorySearch.filters.location = [slug];
            } else {
                $('#inventory-location-all').prop('checked', true);
            }

            inventorySearch.resetPagination();
            inventorySearch.fetchResults();
        });

        // Handle responsive filter sidebar
        function handleResponsiveFilters() {
            if (window.matchMedia('(max-width: 1024px)').matches) {
                if (!$('#mobile-filter-toggle').hasClass('active')) {
                    $('#inventory-filters').hide();
                }
            } else {
                $('#inventory-filters').show().removeClass('active');
                $('#mobile-filter-toggle').removeClass('active');
            }
        }

        handleResponsiveFilters();
        $(window).on('resize', handleResponsiveFilters);
    });

})(jQuery);
