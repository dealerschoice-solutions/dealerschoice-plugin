<?php
/**
 * Template: Filter Sidebar
 * 
 * Displays the inventory filter sidebar with all available filters.
 * This template can be overridden by copying it to:
 * your-theme/dealerschoice/filters.php
 * 
 * @package DealersChoice
 * @subpackage Templates
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<?php do_action('dealerschoice_filters_start'); ?>

<?php if (!empty($locations) && !empty($locations['terms'])): ?>
<div class="widget inventory-location" <?php echo ($filters['location'] !== false) ? 'style="display:none;"' : ''; ?>>
    <h2 class="widget-title">
        <button type="button" aria-expanded="true" aria-controls="filter-location-content">
            Location
            <span class="toggle-icon" aria-hidden="true"></span>
        </button>
    </h2>
    <div id="filter-location-content" class="widget-content">
        <p class="inventory-filter-wrapper">
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-location" id="inventory-location-all" value="all">
                <label for="inventory-location-all">All Locations</label>
            </span>
            <span class="filter-count">(<?php echo esc_html($locations['total']); ?>)</span>
        </p>
        <?php foreach ($locations['terms'] as $term): 
            $count = $term['count'];
            $isChecked = ($filters['location'] && in_array($term['term']->slug, $filters['location']));
        ?>
        <p class="inventory-filter-wrapper" <?php echo ($count == 0) ? 'style="display:none;"' : ''; ?>>
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-location" id="inventory-location-<?php echo esc_attr($term['term']->slug); ?>" value="<?php echo esc_attr($term['term']->slug); ?>" <?php checked($isChecked); ?>>
                <label for="inventory-location-<?php echo esc_attr($term['term']->slug); ?>"><?php echo esc_html($term['term']->name); ?></label>
            </span>
            <span class="filter-count">(<?php echo esc_html($count); ?>)</span>
        </p>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($conditions) && !empty($conditions['terms'])): ?>
<div class="widget inventory-condition" <?php echo ($filters['condition'] !== false) ? 'style="display:none;"' : ''; ?>>
    <h2 class="widget-title">
        <button type="button" aria-expanded="true" aria-controls="filter-condition-content">
            Condition
            <span class="toggle-icon" aria-hidden="true"></span>
        </button>
    </h2>
    <div id="filter-condition-content" class="widget-content">
        <p class="inventory-filter-wrapper">
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-condition" id="inventory-condition-all" value="all">
                <label for="inventory-condition-all">All Conditions</label>
            </span>
            <span class="filter-count">(<?php echo esc_html($conditions['total']); ?>)</span>
        </p>
        <?php foreach ($conditions['terms'] as $term): 
            $count = $term['count'];
            $isChecked = ($filters['condition'] && in_array($term['term']->slug, $filters['condition']));
        ?>
        <p class="inventory-filter-wrapper" <?php echo ($count == 0) ? 'style="display:none;"' : ''; ?>>
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-condition" id="inventory-condition-<?php echo esc_attr($term['term']->slug); ?>" value="<?php echo esc_attr($term['term']->slug); ?>" <?php checked($isChecked); ?>>
                <label for="inventory-condition-<?php echo esc_attr($term['term']->slug); ?>"><?php echo esc_html($term['term']->name); ?></label>
            </span>
            <span class="filter-count">(<?php echo esc_html($count); ?>)</span>
        </p>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($statuses) && !empty($statuses['terms'])): ?>
<div class="widget inventory-status" <?php echo ($filters['boat_status'] !== false) ? 'style="display:none;"' : ''; ?>>
    <h2 class="widget-title">
        <button type="button" aria-expanded="true" aria-controls="filter-status-content">
            Status
            <span class="toggle-icon" aria-hidden="true"></span>
        </button>
    </h2>
    <div id="filter-status-content" class="widget-content">
        <p class="inventory-filter-wrapper">
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-status" id="inventory-status-all" value="all">
                <label for="inventory-status-all">All Availabilities</label>
            </span>
            <span class="filter-count">(<?php echo esc_html($statuses['total']); ?>)</span>
        </p>
        <?php foreach ($statuses['terms'] as $term): 
            $count = $term['count'];
            $isChecked = ($filters['boat_status'] && in_array($term['term']->slug, $filters['boat_status']));
        ?>
        <p class="inventory-filter-wrapper" <?php echo ($count == 0) ? 'style="display:none;"' : ''; ?>>
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-status" id="inventory-status-<?php echo esc_attr($term['term']->slug); ?>" value="<?php echo esc_attr($term['term']->slug); ?>" <?php checked($isChecked); ?>>
                <label for="inventory-status-<?php echo esc_attr($term['term']->slug); ?>"><?php echo esc_html($term['term']->name); ?></label>
            </span>
            <span class="filter-count">(<?php echo esc_html($count); ?>)</span>
        </p>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($years) && !empty($years['terms'])): ?>
<div class="widget inventory-year" <?php echo ($filters['year'] !== false) ? 'style="display:none;"' : ''; ?>>
    <h2 class="widget-title">
        <button type="button" aria-expanded="true" aria-controls="filter-year-content">
            Year
            <span class="toggle-icon" aria-hidden="true"></span>
        </button>
    </h2>
    <div id="filter-year-content" class="widget-content">
        <p class="inventory-filter-wrapper">
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-year" id="inventory-year-all" value="all">
                <label for="inventory-year-all">All Years</label>
            </span>
            <span class="filter-count">(<?php echo esc_html($years['total']); ?>)</span>
        </p>
        <?php foreach ($years['terms'] as $term): 
            $count = $term['count'];
            $isChecked = ($filters['year'] && in_array($term['term']->slug, $filters['year']));
        ?>
        <p class="inventory-filter-wrapper" <?php echo ($count == 0) ? 'style="display:none;"' : ''; ?>>
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-year" id="inventory-year-<?php echo esc_attr($term['term']->slug); ?>" value="<?php echo esc_attr($term['term']->slug); ?>" <?php checked($isChecked); ?>>
                <label for="inventory-year-<?php echo esc_attr($term['term']->slug); ?>"><?php echo esc_html($term['term']->name); ?></label>
            </span>
            <span class="filter-count">(<?php echo esc_html($count); ?>)</span>
        </p>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($categories) && !empty($categories['terms'])): ?>
<div class="widget inventory-category" <?php echo ($filters['boat_type'] !== false) ? 'style="display:none;"' : ''; ?>>
    <h2 class="widget-title">
        <button type="button" aria-expanded="true" aria-controls="filter-category-content">
            Type
            <span class="toggle-icon" aria-hidden="true"></span>
        </button>
    </h2>
    <div id="filter-category-content" class="widget-content">
        <p class="inventory-filter-wrapper">
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-category" id="inventory-category-all" value="all">
                <label for="inventory-category-all">All Types</label>
            </span>
            <span class="filter-count">(<?php echo esc_html($categories['total']); ?>)</span>
        </p>
        <?php foreach ($categories['terms'] as $term): 
            $count = $term['count'];
            $isChecked = ($filters['boat_type'] && in_array($term['term']->slug, $filters['boat_type']));
        ?>
        <p class="inventory-filter-wrapper" <?php echo ($count == 0) ? 'style="display:none;"' : ''; ?>>
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-category" id="inventory-category-<?php echo esc_attr($term['term']->slug); ?>" value="<?php echo esc_attr($term['term']->slug); ?>" <?php checked($isChecked); ?>>
                <label for="inventory-category-<?php echo esc_attr($term['term']->slug); ?>"><?php echo esc_html($term['term']->name); ?></label>
            </span>
            <span class="filter-count">(<?php echo esc_html($count); ?>)</span>
        </p>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($makes) && !empty($makes['terms'])): ?>
<div class="widget inventory-make" <?php echo ($filters['make'] !== false) ? 'style="display:none;"' : ''; ?>>
    <h2 class="widget-title">
        <button type="button" aria-expanded="true" aria-controls="filter-make-content">
            Make
            <span class="toggle-icon" aria-hidden="true"></span>
        </button>
    </h2>
    <div id="filter-make-content" class="widget-content">
        <p class="inventory-filter-wrapper">
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-make" id="inventory-make-all" value="all">
                <label for="inventory-make-all">All Makes</label>
            </span>
            <span class="filter-count">(<?php echo esc_html($makes['total']); ?>)</span>
        </p>
        <?php foreach ($makes['terms'] as $term): 
            $count = $term['count'];
            $isChecked = ($filters['make'] && in_array($term['term']->slug, $filters['make']));
        ?>
        <p class="inventory-filter-wrapper" <?php echo ($count == 0) ? 'style="display:none;"' : ''; ?>>
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-make" id="inventory-make-<?php echo esc_attr($term['term']->slug); ?>" value="<?php echo esc_attr($term['term']->slug); ?>" <?php checked($isChecked); ?>>
                <label for="inventory-make-<?php echo esc_attr($term['term']->slug); ?>"><?php echo esc_html($term['term']->name); ?></label>
            </span>
            <span class="filter-count">(<?php echo esc_html($count); ?>)</span>
        </p>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($models) && !empty($models['terms'])): ?>
<div class="widget inventory-model" <?php echo ($filters['model'] !== false) ? 'style="display:none;"' : ''; ?>>
    <h2 class="widget-title">
        <button type="button" aria-expanded="true" aria-controls="filter-model-content">
            Model
            <span class="toggle-icon" aria-hidden="true"></span>
        </button>
    </h2>
    <div id="filter-model-content" class="widget-content">
        <p class="inventory-filter-wrapper">
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-model" id="inventory-model-all" value="all">
                <label for="inventory-model-all">All Models</label>
            </span>
            <span class="filter-count">(<?php echo esc_html($models['total']); ?>)</span>
        </p>
        <?php foreach ($models['terms'] as $term): 
            $count = $term['count'];
            $isChecked = ($filters['model'] && in_array($term['term']->slug, $filters['model']));
        ?>
        <p class="inventory-filter-wrapper" <?php echo ($count == 0) ? 'style="display:none;"' : ''; ?>>
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-model" id="inventory-model-<?php echo esc_attr($term['term']->slug); ?>" value="<?php echo esc_attr($term['term']->slug); ?>" <?php checked($isChecked); ?>>
                <label for="inventory-model-<?php echo esc_attr($term['term']->slug); ?>"><?php echo esc_html($term['term']->name); ?></label>
            </span>
            <span class="filter-count">(<?php echo esc_html($count); ?>)</span>
        </p>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($priceRanges) && !empty($priceRanges['terms'])): ?>
<div class="widget inventory-price" <?php echo ($filters['price_range'] !== false) ? 'style="display:none;"' : ''; ?>>
    <h2 class="widget-title">
        <button type="button" aria-expanded="true" aria-controls="filter-price-content">
            Price Range
            <span class="toggle-icon" aria-hidden="true"></span>
        </button>
    </h2>
    <div id="filter-price-content" class="widget-content">
        <?php foreach ($priceRanges['terms'] as $term): 
            $count = $term['count'];
            $isChecked = ($filters['price_range'] && in_array($term['term']->slug, $filters['price_range']));
        ?>
        <p class="inventory-filter-wrapper" <?php echo ($count == 0) ? 'style="display:none;"' : ''; ?>>
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-price" id="inventory-price-<?php echo esc_attr($term['term']->slug); ?>" value="<?php echo esc_attr($term['term']->slug); ?>" <?php checked($isChecked); ?>>
                <label for="inventory-price-<?php echo esc_attr($term['term']->slug); ?>"><?php echo esc_html($term['term']->name); ?></label>
            </span>
            <span class="filter-count">(<?php echo esc_html($count); ?>)</span>
        </p>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($lengths) && !empty($lengths['terms'])): ?>
<div class="widget inventory-length" <?php echo ($filters['length_range'] !== false) ? 'style="display:none;"' : ''; ?>>
    <h2 class="widget-title">
        <button type="button" aria-expanded="true" aria-controls="filter-length-content">
            Length
            <span class="toggle-icon" aria-hidden="true"></span>
        </button>
    </h2>
    <div id="filter-length-content" class="widget-content">
        <?php foreach ($lengths['terms'] as $term): 
            $count = $term['count'];
            $isChecked = ($filters['length_range'] && in_array($term['term']->slug, $filters['length_range']));
        ?>
        <p class="inventory-filter-wrapper" <?php echo ($count == 0) ? 'style="display:none;"' : ''; ?>>
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-length" id="inventory-length-<?php echo esc_attr($term['term']->slug); ?>" value="<?php echo esc_attr($term['term']->slug); ?>" <?php checked($isChecked); ?>>
                <label for="inventory-length-<?php echo esc_attr($term['term']->slug); ?>"><?php echo esc_html($term['term']->name); ?></label>
            </span>
            <span class="filter-count">(<?php echo esc_html($count); ?>)</span>
        </p>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($horsepowers) && !empty($horsepowers['terms'])): ?>
<div class="widget inventory-horsepower" <?php echo ($filters['horsepower_range'] !== false) ? 'style="display:none;"' : ''; ?>>
    <h2 class="widget-title">
        <button type="button" aria-expanded="true" aria-controls="filter-horsepower-content">
            Horsepower
            <span class="toggle-icon" aria-hidden="true"></span>
        </button>
    </h2>
    <div id="filter-horsepower-content" class="widget-content">
        <?php foreach ($horsepowers['terms'] as $term): 
            $count = $term['count'];
            $isChecked = ($filters['horsepower_range'] && in_array($term['term']->slug, $filters['horsepower_range']));
        ?>
        <p class="inventory-filter-wrapper" <?php echo ($count == 0) ? 'style="display:none;"' : ''; ?>>
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-horsepower" id="inventory-horsepower-<?php echo esc_attr($term['term']->slug); ?>" value="<?php echo esc_attr($term['term']->slug); ?>" <?php checked($isChecked); ?>>
                <label for="inventory-horsepower-<?php echo esc_attr($term['term']->slug); ?>"><?php echo esc_html($term['term']->name); ?></label>
            </span>
            <span class="filter-count">(<?php echo esc_html($count); ?>)</span>
        </p>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($capacities) && !empty($capacities['terms'])): ?>
<div class="widget inventory-capacity" <?php echo ($filters['person_capacity'] !== false) ? 'style="display:none;"' : ''; ?>>
    <h2 class="widget-title">
        <button type="button" aria-expanded="true" aria-controls="filter-capacity-content">
            Person Capacity
            <span class="toggle-icon" aria-hidden="true"></span>
        </button>
    </h2>
    <div id="filter-capacity-content" class="widget-content">
        <?php foreach ($capacities['terms'] as $term): 
            $count = $term['count'];
            $isChecked = ($filters['person_capacity'] && in_array($term['term']->slug, $filters['person_capacity']));
        ?>
        <p class="inventory-filter-wrapper" <?php echo ($count == 0) ? 'style="display:none;"' : ''; ?>>
            <span class="filter-input-wrapper">
                <input type="checkbox" name="inventory-capacity" id="inventory-capacity-<?php echo esc_attr($term['term']->slug); ?>" value="<?php echo esc_attr($term['term']->slug); ?>" <?php checked($isChecked); ?>>
                <label for="inventory-capacity-<?php echo esc_attr($term['term']->slug); ?>"><?php echo esc_html($term['term']->name); ?></label>
            </span>
            <span class="filter-count">(<?php echo esc_html($count); ?>)</span>
        </p>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php do_action('dealerschoice_filters_end'); ?>
