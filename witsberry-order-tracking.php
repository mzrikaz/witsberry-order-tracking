<?php
/*
 * Plugin Name: Witsberry WooCommerce Order Tracking
 * Plugin URI: https://witsberry.com
 * Description: Adds tracking number and link functionality to WooCommerce orders, includes tracking in emails, and displays on order details page.
 * Version: 1.0.0
 * Author: Witsberry
 * Author URI: https://witsberry.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: witsberry-order-tracking
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.3
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Witsberry_Order_Tracking {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Add tracking fields to order admin
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'add_tracking_fields']);
        add_action('woocommerce_process_shop_order_meta', [$this, 'save_tracking_fields']);

        // Update order status and send email
        add_action('woocommerce_order_status_changed', [$this, 'handle_tracking_on_status_change'], 10, 4);

        // Add tracking to emails
        add_action('woocommerce_email_order_details', [$this, 'add_tracking_to_email'], 20, 4);

        // Display tracking on order details page
        add_action('woocommerce_order_details_after_order_table', [$this, 'display_tracking_info']);

        // Enqueue styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
    }

    public function add_tracking_fields($order) {
        ?>
        <h2><?php esc_html_e('Order Tracking', 'witsberry-order-tracking'); ?></h2>
        <p class="form-field form-field-wide">
            <label for="tracking_number"><?php esc_html_e('Tracking Number:', 'witsberry-order-tracking'); ?></label>
            <input type="text" name="tracking_number" id="tracking_number" value="<?php echo esc_attr(get_post_meta($order->get_id(), '_tracking_number', true)); ?>" />
        </p>
        <p class="form-field form-field-wide">
            <label for="tracking_link"><?php esc_html_e('Tracking Link:', 'witsberry-order-tracking'); ?></label>
            <input type="url" name="tracking_link" id="tracking_link" value="<?php echo esc_attr(get_post_meta($order->get_id(), '_tracking_link', true)); ?>" placeholder="https://" />
        </p>
        <?php
    }

    public function save_tracking_fields($order_id) {
        if (!empty($_POST['tracking_number'])) {
            update_post_meta($order_id, '_tracking_number', sanitize_text_field($_POST['tracking_number']));
        } else {
            delete_post_meta($order_id, '_tracking_number');
        }

        if (!empty($_POST['tracking_link'])) {
            update_post_meta($order_id, '_tracking_link', esc_url_raw($_POST['tracking_link']));
        } else {
            delete_post_meta($order_id, '_tracking_link');
        }
    }

    public function handle_tracking_on_status_change($order_id, $old_status, $new_status, $order) {
        $tracking_number = get_post_meta($order_id, '_tracking_number', true);
        $tracking_link = get_post_meta($order_id, '_tracking_link', true);

        if ($tracking_number && $tracking_link && 'processing' === $old_status && 'completed' !== $new_status) {
            $order->update_status('completed', __('Order marked as completed due to tracking information added.', 'witsberry-order-tracking'));
        }
    }

    public function add_tracking_to_email($order, $sent_to_admin, $plain_text, $email) {
        if ($email->id === 'customer_completed_order') {
            $tracking_number = get_post_meta($order->get_id(), '_tracking_number', true);
            $tracking_link = get_post_meta($order->get_id(), '_tracking_link', true);

            if ($tracking_number && $tracking_link) {
                if ($plain_text) {
                    echo esc_html__('Tracking Number: ', 'witsberry-order-tracking') . esc_html($tracking_number) . "\n";
                    echo esc_html__('Tracking Link: ', 'witsberry-order-tracking') . esc_url($tracking_link) . "\n";
                } else {
                    ?>
                    <h2><?php esc_html_e('Tracking Information', 'witsberry-order-tracking'); ?></h2>
                    <p>
                        <strong><?php esc_html_e('Tracking Number:', 'witsberry-order-tracking'); ?></strong> <?php echo esc_html($tracking_number); ?><br>
                        <strong><?php esc_html_e('Tracking Link:', 'witsberry-order-tracking'); ?></strong> <a href="<?php echo esc_url($tracking_link); ?>" target="_blank"><?php esc_html_e('Track Your Order', 'witsberry-order-tracking'); ?></a>
                    </p>
                    <?php
                }
            }
        }
    }

    public function display_tracking_info($order) {
        $tracking_number = get_post_meta($order->get_id(), '_tracking_number', true);
        $tracking_link = get_post_meta($order->get_id(), '_tracking_link', true);

        if ($tracking_number && $tracking_link) {
            ?>
            <section class="witsberry-tracking-info">
                <h2><?php esc_html_e('Tracking Information', 'witsberry-order-tracking'); ?></h2>
                <p>
                    <strong><?php esc_html_e('Tracking Number:', 'witsberry-order-tracking'); ?></strong> <?php echo esc_html($tracking_number); ?><br>
                    <strong><?php esc_html_e('Tracking Link:', 'witsberry-order-tracking'); ?></strong> <a href="<?php echo esc_url($tracking_link); ?>" target="_blank" class="witsberry-tracking-link"><?php esc_html_e('Track Your Order', 'witsberry-order-tracking'); ?></a>
                </p>
            </section>
            <?php
        }
    }

    public function enqueue_styles() {
        if (is_account_page()) {
            wp_enqueue_style(
                'witsberry-order-tracking',
                plugin_dir_url(__FILE__) . 'css/witsberry-order-tracking.css',
                [],
                '1.0.0'
            );
        }
    }
}

// Instantiate the plugin
Witsberry_Order_Tracking::get_instance();