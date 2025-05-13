<?php
/**
 * Plugin Name: Woo Custom Badges
 * Description: Lightweight WooCommerce plugin to add stylish custom badges with AJAX filtering.
 * Version: 1.1.0
 * Author: Haris Maqsood
 * Author URI: https://www.linkedin.com/in/haris-maqsood-ahmad/
 * Text Domain: woo-custom-badges
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue CSS
add_action('wp_enqueue_scripts', 'wcb_enqueue_styles');
function wcb_enqueue_styles()
{
    wp_enqueue_style('woo-custom-badges-style', plugin_dir_url(__FILE__) . 'assets/css/woo-custom-badges.css');
}

// Admin metabox
add_action('add_meta_boxes', 'wcb_add_metabox');
function wcb_add_metabox()
{
    add_meta_box('wcb_metabox', 'Custom Badge', 'wcb_render_metabox', 'product', 'side', 'default');
}

// Render admin fields
function wcb_render_metabox($post)
{
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

// Save metabox data
add_action('save_post_product', 'wcb_save_metabox');
function wcb_save_metabox($post_id)
{
    if (!isset($_POST['wcb_nonce']) || !wp_verify_nonce($_POST['wcb_nonce'], 'wcb_save_metabox')) {
        return;
    }
    update_post_meta($post_id, '_wcb_label', sanitize_text_field($_POST['wcb_label']));
    update_post_meta($post_id, '_wcb_color', sanitize_hex_color($_POST['wcb_color']));
    update_post_meta($post_id, '_wcb_show', isset($_POST['wcb_show']) ? 'yes' : 'no');
}

// Display badge
add_action('woocommerce_before_shop_loop_item_title', 'wcb_display_badge');
add_action('woocommerce_single_product_summary', 'wcb_display_badge', 5);
function wcb_display_badge()
{
    global $product;
    $label = get_post_meta($product->get_id(), '_wcb_label', true);
    $color = get_post_meta($product->get_id(), '_wcb_color', true);
    $show = get_post_meta($product->get_id(), '_wcb_show', true);

    if ($show === 'yes' && !empty($label)) {
        echo '<span class="wcb-badge" style="background-color:' . esc_attr($color) . '">' . esc_html($label) . '</span>';
    }
}

// AJAX badge filter
add_action('woocommerce_before_shop_loop', 'wcb_badge_filter_dropdown', 20);
function wcb_badge_filter_dropdown()
{
    $labels = get_transient('wcb_badge_labels');
    if (!$labels) {
        $products = get_posts([
            'post_type' => 'product',
            'meta_key' => '_wcb_show',
            'meta_value' => 'yes',
            'numberposts' => -1
        ]);
        $labels = array_unique(array_filter(array_map(function ($product) {
            return get_post_meta($product->ID, '_wcb_label', true);
        }, $products)));
        set_transient('wcb_badge_labels', $labels, 3600);
    }

    if ($labels) : ?>
        <select id="wcb-filter">
            <option value="">Filter by Badge</option>
            <?php foreach ($labels as $label) : ?>
                <option value="<?php echo esc_attr($label); ?>" <?php selected($_GET['wcb_badge'] ?? '', $label); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <script>
            jQuery('#wcb-filter').change(function () {
                var badge = jQuery(this).val();
                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'post',
                    data: {
                        action: 'wcb_filter_badge',
                        badge: badge
                    },
                    success: function (response) {
                        jQuery('.woocommerce ul.products').html(response);
                    }
                });
            });
        </script>
    <?php endif;
}

// AJAX handler
add_action('wp_ajax_wcb_filter_badge', 'wcb_ajax_filter');
add_action('wp_ajax_nopriv_wcb_filter_badge', 'wcb_ajax_filter');
function wcb_ajax_filter()
{
    $badge = sanitize_text_field($_POST['badge']);
    $args = ['post_type' => 'product', 'meta_query' => []];

    if ($badge) {
        $args['meta_query'][] = [
            'key' => '_wcb_label',
            'value' => $badge,
            'compare' => '='
        ];
        $args['meta_query'][] = [
            'key' => '_wcb_show',
            'value' => 'yes',
            'compare' => '='
        ];
    }

    $loop = new WP_Query($args);
    woocommerce_product_loop_start();
    if ($loop->have_posts()) : while ($loop->have_posts()) : $loop->the_post();
        wc_get_template_part('content', 'product');
    endwhile;
    else :
        echo '<p>No products found</p>';
    endif;
    woocommerce_product_loop_end();
    wp_reset_postdata();
    wp_die();
}
