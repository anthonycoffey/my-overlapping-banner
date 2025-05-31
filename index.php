<?php
/**
 * Plugin Name: My WooCommerce Overlapping Banner
 * Plugin URI:  https://example.com/my-woocommerce-banner
 * Description: Adds an overlapping banner to WooCommerce product titles in the shop loop and single product pages, with dynamic content based on stock.
 * Version:     0.0.1
 * Author:      Anthony Coffey
 * Author URI:  https://coffey.codes
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: my-woocommerce-banner
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 *
 * @package MyWooCommerceBanner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function my_woocommerce_banner_check_woocommerce_active() {
    if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        add_action( 'admin_notices', 'my_woocommerce_banner_admin_notice' );
        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
}
add_action( 'admin_init', 'my_woocommerce_banner_check_woocommerce_active' );

function my_woocommerce_banner_admin_notice() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php esc_html_e( 'My WooCommerce Overlapping Banner requires WooCommerce to be installed and active. The plugin has been deactivated.', 'my-woocommerce-banner' ); ?></p>
    </div>
    <?php
}

function my_woocommerce_banner_enqueue_styles() {
    if ( is_woocommerce() || is_shop() || is_product_category() || is_product_tag() || is_singular( 'product' ) || is_front_page() ) {
        wp_enqueue_style(
            'my-woocommerce-banner-style',
            plugins_url( 'index.css', __FILE__ ),
            array(),
            filemtime( plugin_dir_path( __FILE__ ) . 'index.css' )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'my_woocommerce_banner_enqueue_styles' );

function my_woocommerce_banner_add_initial_stock_field() {
    echo '<div class="options_group show_if_simple show_if_variable">';
    woocommerce_wp_text_input(
        array(
            'id'            => '_initial_stock_quantity',
            'label'         => esc_html__( 'Initial Stock Quantity', 'my-woocommerce-banner' ),
            'placeholder'   => esc_html__( 'Enter original stock quantity', 'my-woocommerce-banner' ),
            'description'   => esc_html__( 'Set the original stock quantity for calculating "sold out" percentage.', 'my-woocommerce-banner' ),
            'data_type'     => 'decimal',
            'type'          => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min'  => '0',
            ),
        )
    );
    echo '</div>';
}
add_action( 'woocommerce_product_options_inventory_product_data', 'my_woocommerce_banner_add_initial_stock_field' );

function my_woocommerce_banner_save_initial_stock_field( $post_id ) {
    $initial_stock_quantity = isset( $_POST['_initial_stock_quantity'] ) ? sanitize_text_field( $_POST['_initial_stock_quantity'] ) : '';
    update_post_meta( $post_id, '_initial_stock_quantity', $initial_stock_quantity );
}
add_action( 'woocommerce_process_product_meta', 'my_woocommerce_banner_save_initial_stock_field' );

function my_woocommerce_banner_add_dynamic_banner_html() {
    global $product;

    if ( ! $product || ! $product->managing_stock() ) {
        return;
    }

    $current_stock  = $product->get_stock_quantity();
    $initial_stock  = (float) $product->get_meta( '_initial_stock_quantity' );

    if ( $initial_stock <= 0 ) {
        return;
    }

    $sold_quantity = $initial_stock - $current_stock;

    if ( $initial_stock > 0 ) {
        $sold_out_percentage = round( ( $sold_quantity / $initial_stock ) * 100 );
    } else {
        $sold_out_percentage = 0;
    }

    // Clamp percentage between 0 and 100 for display
    $display_percentage = max( 0, min( 100, $sold_out_percentage ) );

    $banner_text = '';
    $banner_subtext = '';

    if ( $display_percentage >= 100 ) {
        $banner_text = esc_html__( 'Sold Out!', 'my-woocommerce-banner' );
    } elseif ( $display_percentage > 0 ) {
        $banner_text = sprintf( esc_html__( '%s%% Sold', 'my-woocommerce-banner' ), $display_percentage );
        // You can add a sub-text here if needed, e.g., showing current stock
        // $banner_subtext = sprintf( esc_html__( '%s left!', 'my-woocommerce-banner' ), $current_stock );
    } else {
        return; // Don't show banner if 0% sold
    }

    $extra_class = is_singular( 'product' ) ? ' is-single-product-banner' : '';

    echo '<div class="overlapping-banner-php' . esc_attr( $extra_class ) . '" style="--sold-out-progress: ' . esc_attr( $display_percentage ) . '%;">';
    echo '<span class="banner-text">' . $banner_text . '</span>';
    if ( ! empty( $banner_subtext ) ) {
        echo '<span class="banner-subtext">' . $banner_subtext . '</span>';
    }
    echo '<div class="progress-meter">';
    echo '<div class="progress-bar"></div>';
    echo '</div>';
    echo '</div>';
}

add_action( 'woocommerce_after_shop_loop_item_title', 'my_woocommerce_banner_add_dynamic_banner_html', 5 );
add_action( 'woocommerce_single_product_summary', 'my_woocommerce_banner_add_dynamic_banner_html', 6 );
