<?php
/**
 * Plugin Name: Woo Custom Badges
 * Description: Adds custom badges to WooCommerce products. Lightweight, admin-controlled, with AJAX filtering.
 * Version: 1.0.0
 * Author: Haris Maqsood
 * Author URI: https://www.linkedin.com/in/haris-maqsood-ahmad/
 * Text Domain: woo-custom-badges
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue frontend styles
add_action('wp_enqueue_scripts', 'wcb_enqueue_styles');
function wcb_enqueue_styles() {
    wp_enqueue_style('woo-custom-badges-style', plugin_dir_url(__FILE__) . 'css/woo-custom-badges.css');
}

// Admin meta box
add_action('add_meta_boxes', 'wcb_add_metabox');
function wcb_add_metabox() {
    add_meta_box('wcb_metabox', 'Custom Badge', 'wcb_render_metabox', 'product', 'side', 'default');
}

// Render admin meta box fields
function wcb_render_metabox($post) {
    wp_nonce_field('wcb_save_metabox', 'wcb_nonce');
    $label = get_post_meta($post->ID, '_wcb_label', true);
    $color = get_post_meta($post->ID, '_wcb_color', true);
    $show_badge = get_post_meta($post->ID, '_wcb_show', true);
    ?>
    <p>
        <label>Badge Label:</label>
        <input type="text" name="wcb_label" value="<?php echo esc_attr($label); ?>" style="width:100%;">
    </p>
    <p>
        <label>Badge Color:</label>
        <input type="color" name="wcb_color" value="<?php echo esc_attr($color ?: '#ff0000'); ?>">
    </p>
    <p>
        <label><input type="checkbox" name="wcb_show" <?php checked($show_badge, 'yes'); ?>> Show Badge</label>
    </p>
    <?php
}

// Save meta box data
add_action('save_post_product', 'wcb_save_metabox');
function wcb_save_metabox($post_id) {
    if (!isset($_POST['wcb_nonce']) || !wp_verify_nonce($_POST['wcb_nonce'], 'wcb_save_metabox')) {
        return;
    }
    update_post_meta($post_id, '_wcb_label', sanitize_text_field($_POST['wcb_label']));
    update_post_meta($post_id, '_wcb_color', sanitize_hex_color($_POST['wcb_color']));
    update_post_meta($post_id, '_wcb_show', isset($_POST['wcb_show']) ? 'yes' : 'no');
}

// Display badge on product images
add_action('woocommerce_before_shop_loop_item_title', 'wcb_display_badge', 10);
add_action('woocommerce_product_thumbnails', 'wcb_display_badge', 5);
function wcb_display_badge() {
    global $product;
    $label = get_post_meta($product->get_id(), '_wcb_label', true);
    $color = get_post_meta($product->get_id(), '_wcb_color', true);
    $show = get_post_meta($product->get_id(), '_wcb_show', true);

    if ($show === 'yes' && !empty($label)) {
        echo '<span class="wcb-badge" style="background-color:' . esc_attr($color) . '">' . esc_html($label) . '</span>';
    }
}

// AJAX badge filter
add_action('woocommerce_before_shop_loop', 'wcb_badge_filter_dropdown', 30);
function wcb_badge_filter_dropdown() {
    $badges = get_posts([
        'post_type' => 'product',
        'meta_key' => '_wcb_show',
        'meta_value' => 'yes',
        'numberposts' => -1
    ]);
    $labels = array_unique(array_filter(array_map(function ($product) {
        return get_post_meta($product->ID, '_wcb_label', true);
    }, $badges)));

    if (!empty($labels)) : ?>
        <select id="wcb-filter">
            <option value="">Filter by Badge</option>
            <?php foreach ($labels as $label) : ?>
                <option value="<?php echo esc_attr($label); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <script>
            jQuery('#wcb-filter').change(function () {
                var badge = jQuery(this).val();
                var url = new URL(window.location);
                url.searchParams.set('wcb_badge', badge);
                window.location = url;
            });
        </script>
    <?php endif;
}

// Filter products by badge
add_action('pre_get_posts', 'wcb_filter_products_by_badge');
function wcb_filter_products_by_badge($query) {
    if (!is_admin() && is_shop() && $query->is_main_query() && isset($_GET['wcb_badge']) && $_GET['wcb_badge']) {
        $query->set('meta_query', [
            [
                'key' => '_wcb_label',
                'value' => sanitize_text_field($_GET['wcb_badge']),
                'compare' => '='
            ],
            [
                'key' => '_wcb_show',
                'value' => 'yes',
                'compare' => '='
            ]
        ]);
    }
}
