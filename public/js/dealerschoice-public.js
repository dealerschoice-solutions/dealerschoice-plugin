/**
 * Inventory listing: AJAX filtering, search, sorting, and pagination.
 *
 * @package DealersChoice
 */

(function($) {
    'use strict';

    /**
     * Inventory search controller.
     *
     * Holds filter, sort, search, and page state and renders results from the
     * search_inventory AJAX action.
     */
    class InventorySearch {
        constructor() {
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

            // Locked taxonomy constraints from the shortcode's 'promotion' or 'lock'
            // attribute. Kept separate from this.filters and sent with every request.
            this.locked = this.readLockedConstraints();

            // Inventory page URL used when a different location is chosen on a location-locked page.
            this.locationSwitchUrl = $('#inventory-wrapper').attr('data-location-switch-url') || '';

            this.sortBy = $('#inventory-sort').val() || 'date-desc';
            this.searchQuery = $('#q').val() || '';
            this.currentPage = 1;
            this.postsPerPage = parseInt($('#inventory-wrapper').data('posts-per-page'), 10) || 12;
            this.searchTimeout = null;

            // Start on the page given by ?inventory_page.
            const urlInventoryPage = parseInt(new URLSearchParams(window.location.search).get('inventory_page'), 10);
            if (urlInventoryPage > 1) {
                this.currentPage = urlInventoryPage;
            }

            this.init();
        }

        /**
         * Sets the search query and search field value.
         *
         * @param {string} query Search query.
         */
        setInitialQuery(query) {
            if (query) {
                $('#q').val(query);
                this.searchQuery = query;
            }
        }

        /**
         * Returns the checked values for a filter checkbox group.
         *
         * @param {string} name Checkbox input name.
         * @returns {string[]} Checked values.
         */
        getCheckedValues(name) {
            return $('input[name="' + name + '"]:checked')
                .map(function() { return $(this).val(); })
                .get();
        }

        /**
         * Reads locked taxonomy constraints from the wrapper's data-locked attribute.
         *
         * @returns {Object} Map of filter key to locked term slugs, or {} when missing or invalid.
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
         * Checks whether a filter key is locked by the shortcode.
         *
         * @param {string} key Filter key.
         * @returns {boolean} True when the key has locked terms.
         */
        isLocked(key) {
            return Object.prototype.hasOwnProperty.call(this.locked, key)
                && Array.isArray(this.locked[key])
                && this.locked[key].length > 0;
        }

        /**
         * Returns the locked terms for a filter key.
         *
         * @param {string} key Filter key.
         * @returns {string[]} Locked term slugs, or an empty array.
         */
        lockedTerms(key) {
            return this.isLocked(key) ? this.locked[key] : [];
        }

        /**
         * Binds event handlers and sets up the filter UI.
         */
        init() {
            this.handleEvents();
            this.initFilterUI();
        }

        /**
         * Sets up filter widget collapse toggles and Show More/Show Less buttons.
         */
        initFilterUI() {
            // Widget collapse/expand.
            $('.dealerschoice-filters .widget-title button').on('click', function(e) {
                e.preventDefault();
                const $button = $(this);
                const $content = $('#' + $button.attr('aria-controls'));
                const isExpanded = $button.attr('aria-expanded') === 'true';

                $button.attr('aria-expanded', !isExpanded);
                $content.slideToggle(200);
            });

            // Show More/Show Less for widgets with more than five options.
            $('.dealerschoice-filters .widget-content').each(function() {
                const $content = $(this);
                const $items = $content.find('.inventory-filter-wrapper:not([style*="display:none"])');
                const limit = 5;

                if ($items.length > limit) {
                    $items.slice(limit).addClass('hidden-option');

                    const $toggleBtn = $('<button type="button" class="show-more-link" aria-expanded="false">Show More</button>');
                    $content.append($toggleBtn);

                    $toggleBtn.on('click', function() {
                        const $btn = $(this);
                        const isExpanded = $btn.attr('aria-expanded') === 'true';

                        if (isExpanded) {
                            $items.slice(limit).addClass('hidden-option');
                            $btn.text('Show More').attr('aria-expanded', 'false');
                        } else {
                            $items.slice(limit).removeClass('hidden-option');
                            $btn.text('Show Less').attr('aria-expanded', 'true');
                            // Focus the first revealed option.
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
         * Binds filter, sort, search, pagination, history, and mobile toggle handlers.
         */
        handleEvents() {
            $('#inventory-filters input[type="checkbox"]').on('change', (e) => this.updateFilters(e));

            $('#inventory-sort').on('change', (e) => {
                this.sortBy = $(e.target).val();
                this.resetPagination();
                this.fetchResults();
            });

            // Debounced search.
            $('#inventory-search').on('input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.searchQuery = $(e.target).val();
                    this.resetPagination();
                    this.fetchResults();
                }, 300);
            });

            $('#inventory-search').on('submit', function(e) {
                e.preventDefault();
            });

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

            // Browser back/forward: load the page stored in history.
            window.addEventListener('popstate', (e) => {
                const page = (e.state && e.state.inventory_page)
                    ? e.state.inventory_page
                    : (parseInt(new URLSearchParams(window.location.search).get('inventory_page'), 10) || 1);
                // Skip when the page number is unchanged (e.g. in-page anchor links).
                if (page === this.currentPage) {
                    return;
                }
                this.currentPage = page;
                this.fetchResults();
            });

            $(document).on('click', '#clear-all-filters', () => {
                this.clearAllFilters();
            });

            $('#mobile-filter-toggle button').on('click', (e) => {
                e.preventDefault();
                $('#inventory-filters').toggle().toggleClass('active');
                $('#mobile-filter-toggle').toggleClass('active');
            });

            $('#mobile-filter-close button').on('click', (e) => {
                e.preventDefault();
                $('#inventory-filters').toggle().toggleClass('active');
                $('#mobile-filter-toggle').toggleClass('active');
            });

            $(document).on('click', '.gallery-thumb', function() {
                const fullImage = $(this).data('full-image');
                $('.gallery-main-image').attr('src', fullImage);
                $('.gallery-thumb').removeClass('active');
                $(this).addClass('active');
            });
        }

        /**
         * Updates filter state from a checkbox change and fetches results.
         *
         * Checking "All" clears the group; checking any other value unchecks "All".
         *
         * @param {Event} e Change event.
         */
        updateFilters(e) {
            const $checkbox = $(e.target);
            const filterName = $checkbox.attr('name').split('-')[1];
            const filterValue = $checkbox.val();

            if (filterValue === 'all') {
                if ($checkbox.is(':checked')) {
                    $('input[name="inventory-' + filterName + '"]').not($checkbox).prop('checked', false);
                    this.filters[filterName] = [];
                } else {
                    return;
                }
            } else {
                const $allCheckbox = $('#inventory-' + filterName + '-all');
                if ($allCheckbox.is(':checked')) {
                    $allCheckbox.prop('checked', false);
                }

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
         * Clears all filters and the search query, keeping locked constraints.
         */
        clearAllFilters() {
            $('#inventory-filters input[type="checkbox"]').prop('checked', false);

            for (let key in this.filters) {
                this.filters[key] = [];
            }

            $('#inventory-search').val('');
            this.searchQuery = '';
            
            this.resetPagination();
            this.fetchResults();
        }

        /**
         * Builds the filters sent to search_inventory.
         *
         * Omits keys covered by a locked constraint and empty ID values.
         *
         * @returns {Object} Map of filter key to selected values.
         */
        buildRequestFilters() {
            const filters = JSON.parse(JSON.stringify(this.filters));

            const self = this;
            Object.keys(this.locked).forEach(function(key) {
                if (Object.prototype.hasOwnProperty.call(filters, key) && self.isLocked(key)) {
                    delete filters[key];
                }
            });
            if (filters.id && Array.isArray(filters.id) && filters.id.length > 0) {
                filters.id = filters.id.filter(function(id) { return id !== null && id !== undefined && id !== ''; });
            }

            return filters;
        }

        /**
         * Loads the initial results.
         *
         * Keeps the server-rendered results when the current state matches
         * data-ssr-state; otherwise fetches via AJAX.
         */
        loadInitialResults() {
            if (this.matchesServerRenderedState()) {
                $(document).trigger('dc:inventoryRendered');
                return;
            }

            this.fetchResults();
        }

        /**
         * Checks whether the current state matches the server-rendered state.
         *
         * @returns {boolean} True when page, sort, query, page size, and filters match.
         */
        matchesServerRenderedState() {
            const raw = $('#inventory-wrapper').attr('data-ssr-state');

            if (!raw) {
                return false;
            }

            let ssr;
            try {
                ssr = JSON.parse(raw);
            } catch (e) {
                return false;
            }

            if (!ssr || typeof ssr !== 'object') {
                return false;
            }

            return parseInt(ssr.page, 10) === parseInt(this.currentPage, 10)
                && String(ssr.sortBy) === String(this.sortBy)
                && String(ssr.query) === String(this.searchQuery)
                && parseInt(ssr.postsPerPage, 10) === parseInt(this.postsPerPage, 10)
                && this.normalizeFilters(ssr.filters) === this.normalizeFilters(this.buildRequestFilters());
        }

        /**
         * Serializes filters for comparison, dropping empty keys and sorting keys and values.
         *
         * @param {Object} filters Map of filter key to values.
         * @returns {string} JSON string.
         */
        normalizeFilters(filters) {
            const normalized = {};

            if (filters && typeof filters === 'object') {
                Object.keys(filters).sort().forEach(function(key) {
                    const values = Array.isArray(filters[key]) ? filters[key] : [];
                    if (values.length > 0) {
                        normalized[key] = values.map(String).sort();
                    }
                });
            }

            return JSON.stringify(normalized);
        }

        /**
         * Replaces or removes the ItemList JSON-LD for the current results.
         *
         * @param {Object|null} schema ItemList schema, or null to remove it.
         */
        updateItemListSchema(schema) {
            let el = document.getElementById('dealerschoice-itemlist-schema');

            if (!schema) {
                if (el) {
                    el.parentNode.removeChild(el);
                }
                return;
            }

            if (!el) {
                const wrapper = document.getElementById('inventory-wrapper');
                if (!wrapper) {
                    return;
                }
                el = document.createElement('script');
                el.type = 'application/ld+json';
                el.id = 'dealerschoice-itemlist-schema';
                wrapper.appendChild(el);
            }

            el.textContent = JSON.stringify(schema);
        }

        /**
         * Builds the results status message, e.g. "Showing 13-24 of 47 boats."
         *
         * @param {Object} data search_inventory response data.
         * @returns {string} Status message.
         */
        getResultsSummary(data) {
            const total = parseInt(data.total, 10) || 0;

            if (total === 0) {
                return 'No boats found.';
            }

            const shown = (data.schema && data.schema.numberOfItems)
                ? data.schema.numberOfItems
                : $('#inventory-results .inventory-section').length;
            const first = ((this.currentPage - 1) * this.postsPerPage) + 1;
            const last = Math.min(first + shown - 1, total);

            return 'Showing ' + first + '\u2013' + last + ' of ' + total + (total === 1 ? ' boat.' : ' boats.');
        }

        /**
         * Writes a message to the #inventory-results-status live region.
         *
         * @param {string} message Message text.
         */
        announceResults(message) {
            $('#inventory-results-status').text(message);
        }

        /**
         * Fetches results via AJAX and updates the list, pagination, schema, and status.
         */
        fetchResults() {
            const ajaxData = {
                action: 'search_inventory',
                filters: this.buildRequestFilters(),
                locked: this.locked,
                sortBy: this.sortBy,
                query: this.searchQuery,
                currentPage: this.currentPage,
                postsPerPage: this.postsPerPage,
                // Listing page URL for pagination hrefs and the ItemList schema.
                pageUrl: window.location.href.split('#')[0]
            };

            this.announceResults('');
            this.updateInventoryList(
                '<div class="loading-spinner">' +
                '<i class="fa-light fa-arrows-rotate-reverse"></i> ' +
                'Loading inventory...' +
                '</div>'
            );
            this.updateInventoryPagination('');
            this.updateOtherLocations('');

            $.ajax({
                url: dealersChoicePublic.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    if (response.success) {
                        // Render before updateInventoryList() so dc:inventoryRendered covers these cards.
                        this.updateOtherLocations(response.data.other_locations || '');
                        this.updateInventoryList(response.data.results);
                        this.updateInventoryPagination(response.data.pagination);
                        this.updateItemListSchema(response.data.schema || null);
                        this.announceResults(this.getResultsSummary(response.data));
                        if (window.DealersChoiceFavorites && typeof window.DealersChoiceFavorites.initFavoriteButtons === 'function') {
                            window.DealersChoiceFavorites.initFavoriteButtons('#inventory-results');
                            if (response.data.other_locations) {
                                window.DealersChoiceFavorites.initFavoriteButtons('.dealerschoice-other-locations-wrapper');
                            }
                        }
                    } else {
                        this.updateInventoryList(
                            '<div class="no-results">' +
                            '<h2>Error</h2>' +
                            '<p>There was an error loading the inventory. Please try again.</p>' +
                            '</div>'
                        );
                        this.updateItemListSchema(null);
                        this.announceResults('There was an error loading the inventory.');
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
                    this.updateItemListSchema(null);
                    this.announceResults('There was an error loading the inventory.');
                }
            });
        }

        /**
         * Replaces the results HTML and triggers dc:inventoryRendered.
         *
         * @param {string} html Results HTML.
         */
        updateInventoryList(html) {
            $('#inventory-results').html(html);
            $(document).trigger('dc:inventoryRendered');
        }

        /**
         * Replaces the pagination HTML.
         *
         * @param {string} html Pagination HTML.
         */
        updateInventoryPagination(html) {
            $('.pagination-wrapper').html(html);
        }

        /**
         * Replaces the "Shop Other Locations" HTML on location-locked pages.
         *
         * @param {string} html Block HTML.
         */
        updateOtherLocations(html) {
            $('.dealerschoice-other-locations-wrapper').html(html);
        }

        /**
         * Resets to page 1 and updates the URL.
         */
        resetPagination() {
            this.currentPage = 1;
            this.syncPageToUrl(false);
        }

        /**
         * Writes the current page to the inventory_page URL parameter.
         *
         * @param {boolean} push True to add a history entry, false to replace the current one.
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
         * Scrolls to the top of the results.
         */
        smoothScrollToTop() {
            $('html, body').animate({
                scrollTop: $('#inventory-results').offset().top - 100
            }, 300);
        }

        /**
         * Replaces the filters and fetches results.
         *
         * @param {Object} filters Map of filter key to values.
         */
        setFilters(filters) {
            this.filters = filters;
            this.fetchResults();
        }
    }

    /**
     * Returns all values of a URL parameter, including array-style (?param[]=value) values.
     *
     * @param {string} paramName Parameter name.
     * @returns {string[]} Parameter values.
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
     * Initializes the inventory listing on pages with #inventory-filters.
     */
    $(document).ready(function() {
        
        if ($('#inventory-filters').length === 0) {
            return;
        }

        // Disable browser scroll restoration.
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }

        const inventorySearch = new InventorySearch();

        // Store the current page in the history entry.
        history.replaceState({ inventory_page: inventorySearch.currentPage }, '', window.location.href);

        // Preset filters from URL parameters.
        if (window.location.search) {
            // Year is read from ?boat_year= below.
            const formInputs = [
                'location', 'condition', 'status', 'category',
                'make', 'model', 'price', 'length', 'horsepower'
            ];
            
            const filters = {
                location: [],
                condition: [],
                status: [],
                year: [],      // From ?boat_year=.
                category: [],
                make: [],
                model: [],
                price: [],
                length: [],
                horsepower: [],
                capacity: [],  // Not read from the URL.
            };

            let hasFilters = false;

            formInputs.forEach(function(input) {
                // Skip keys locked by the shortcode.
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

            // ?boat_year= maps to the 'year' filter key.
            const boatYearParam = getUrlParameters('boat_year');
            if (boatYearParam.length > 0 && !inventorySearch.isLocked('year')) {
                hasFilters = true;
                boatYearParam.forEach(function(value) {
                    filters.year.push(value);
                    $('#inventory-year-' + value).prop('checked', true);
                });
            }

            if (hasFilters) {
                inventorySearch.filters = filters;
            }

            inventorySearch.loadInitialResults();
        } else {
            // Apply the saved preferred location unless location is locked.
            if (!inventorySearch.isLocked('location')) {
                const savedLocation = localStorage.getItem('dc_preferred_location');
                if (savedLocation && savedLocation !== 'all') {
                    $('#inventory-location-' + savedLocation).prop('checked', true);
                    inventorySearch.filters.location = [savedLocation];
                }
            }

            inventorySearch.loadInitialResults();
        }

        $(document).on('dc_location_changed', function(e, slug) {
            // On a location-locked page, go to the inventory page with ?location= instead.
            if (inventorySearch.isLocked('location')) {
                const lockedLocations = inventorySearch.lockedTerms('location');

                // Already showing the chosen location.
                if (slug !== 'all' && lockedLocations.indexOf(slug) !== -1) {
                    return;
                }

                // No inventory page URL available.
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

        // Show the filter sidebar on desktop; hide it on mobile unless toggled open.
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
