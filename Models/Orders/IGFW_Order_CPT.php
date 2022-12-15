<?php
namespace IGFW\Models\Orders;

use IGFW\Abstracts\Abstract_Main_Plugin_Class;
use IGFW\Helpers\Helper_Functions;
use IGFW\Helpers\Plugin_Constants;
use IGFW\Interfaces\Model_Interface;

if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly

/**
 * Model that houses the logic of wc order cpt.
 * Private Model.
 *
 * @since 1.0.0
 */
class IGFW_Order_CPT implements Model_Interface {

    /*
    |--------------------------------------------------------------------------
    | Class Properties
    |--------------------------------------------------------------------------
     */

    /**
     * Property that holds the single main instance of Bootstrap.
     *
     * @since 1.0.0
     * @access private
     * @var Bootstrap
     */
    private static $_instance;

    /**
     * Model that houses all the plugin constants.
     *
     * @since 1.0.0
     * @access private
     * @var Plugin_Constants
     */
    private $_constants;

    /**
     * Property that houses all the helper functions of the plugin.
     *
     * @since 1.0.0
     * @access private
     * @var Helper_Functions
     */
    private $_helper_functions;

    /*
    |--------------------------------------------------------------------------
    | Class Methods
    |--------------------------------------------------------------------------
     */

    /**
     * Class constructor.
     *
     * @since 1.0.0
     * @access public
     *
     * @param Abstract_Main_Plugin_Class $main_plugin      Main plugin object.
     * @param Plugin_Constants           $constants        Plugin constants object.
     * @param Helper_Functions           $helper_functions Helper functions object.
     */
    public function __construct(Abstract_Main_Plugin_Class $main_plugin, Plugin_Constants $constants, Helper_Functions $helper_functions) {

        $this->_constants = $constants;
        $this->_helper_functions = $helper_functions;

        $main_plugin->add_to_all_plugin_models($this);

    }

    /**
     * Ensure that only one instance of this class is loaded or can be loaded ( Singleton Pattern ).
     *
     * @since 1.0.0
     * @access public
     *
     * @param Abstract_Main_Plugin_Class $main_plugin      Main plugin object.
     * @param Plugin_Constants           $constants        Plugin constants object.
     * @param Helper_Functions           $helper_functions Helper functions object.
     * @return Bootstrap
     */
    public static function get_instance(Abstract_Main_Plugin_Class $main_plugin, Plugin_Constants $constants, Helper_Functions $helper_functions) {

        if (!self::$_instance instanceof self) {
            self::$_instance = new self($main_plugin, $constants, $helper_functions);
        }

        return self::$_instance;

    }

    /**
     * Add order invoice meta box.
     *
     * @since 1.0.0
     * @access public
     */
    public function add_order_invoice_meta_box() {

        add_meta_box(
            'igfw-order-invoice',
            __('Order Invoice', 'invoice-gateway-for-woocommerce'),
            array($this, 'view_order_invoice_meta_box'),
            'shop_order',
            'side',
            'default'
        );

    }

    /**
     * Order invoice meta box.
     *
     * @since 1.0.0
     * @access public
     */
    public function view_order_invoice_meta_box() {

        include $this->_constants->VIEWS_ROOT_PATH() . 'order' . DIRECTORY_SEPARATOR . 'view-order-invoice-meta-box.php';

    }

    /**
     * Add invoice number field.
     *
     * @since 1.0.0
     * @access public
     */
    public function add_invoice_number_field() {

        if (get_option('igfw_enable_purchase_order_number') == 'yes') {

            woocommerce_wp_text_input(array(
                'id' => Plugin_Constants::Purchase_Order_Number,
                'style' => 'width: 100%;',
                'label' => __('Purchase Order Number', 'invoice-gateway-for-woocommerce'),
                'type' => 'text',
                'data_type' => 'text',
            ));

        }

        woocommerce_wp_text_input(array(
            'id' => Plugin_Constants::Invoice_Number,
            'style' => 'width: 100%;',
            'label' => __('Invoice Number', 'invoice-gateway-for-woocommerce'),
            'description' => __('<br>Enter the Invoice ID from your accounting system for tracking purposes', 'invoice-gateway-for-woocommerce'),
            'type' => 'text',
            'data_type' => 'text',
        ));

        wp_nonce_field('igfw_action_save_invoice_number', 'igfw_nonce_save_invoice_number');

    }

    /**
     * Save invoice data.
     *
     * @since 1.0.0
     * @since 1.0.1 Bug fix: when saving an order with WooCommerce Admin plugin is active it will show an error.
     * @access public
     *
     * @param int $post_id Post id.
     */
    public function save_invoice_data($post_id) {

        // On manual click of 'update' , 'publish' or 'save draft' button, execute code inside the if statement
        if ($this->_helper_functions->check_if_valid_save_post_action($post_id, 'shop_order')) {

            $order = wc_get_order($post_id);

            if (is_a($order, 'WC_Admin_Order') || is_a($order, 'WC_Order')) {
                $this->_save_invoice_number($post_id);
            }

        }

    }

    /**
     * Save invoice number.
     *
     * @since 1.0.0
     * @access public
     *
     * @param int $post_id Post id. Id of the order.
     */
    private function _save_invoice_number($post_id) {

        // Check nonce
        if (isset($_POST['igfw_nonce_save_invoice_number']) && wp_verify_nonce($_POST['igfw_nonce_save_invoice_number'], 'igfw_action_save_invoice_number')) {

            $new_invoice_number = isset($_POST[Plugin_Constants::Invoice_Number]) ? 
                filter_var(trim(
                    sanitize_text_field($_POST[Plugin_Constants::Invoice_Number]
                )), FILTER_SANITIZE_STRING) : 
                '';
            $existing_invoice_number = get_post_meta($post_id, Plugin_Constants::Invoice_Number, true);

            $this->_log_invoice_number_activity($new_invoice_number, $existing_invoice_number, $post_id);

            update_post_meta($post_id, Plugin_Constants::Invoice_Number, $new_invoice_number);

            if (isset($_POST['igfw_purchase_order_number'])) {

                $new_invoice_number = isset($_POST[Plugin_Constants::Purchase_Order_Number]) ? 
                    filter_var(trim(
                        sanitize_text_field($_POST[Plugin_Constants::Purchase_Order_Number])
                    ), FILTER_SANITIZE_STRING) : 
                    '';
                $existing_invoice_number = get_post_meta($post_id, Plugin_Constants::Purchase_Order_Number, true);

                $this->_log_invoice_number_activity($new_invoice_number, $existing_invoice_number, $post_id, 'purchase order number');

                update_post_meta($post_id, Plugin_Constants::Purchase_Order_Number, $new_invoice_number);

            }

        }

    }

    /**
     * Log invoice number activity.
     *
     * @since 1.0.0
     * @access public
     *
     * @param string $new_invoice_number      New invoice number.
     * @param string $existing_invoice_number Current invoice number.
     * @param int    $post_id                 Post (Order) id.
     */
    private function _log_invoice_number_activity($new_invoice_number, $existing_invoice_number, $post_id, $type = "invoice number") {

        if ($new_invoice_number == $existing_invoice_number) {
            return;
        }

        $order = wc_get_order($post_id);
        $user = wp_get_current_user();

        if (is_a($order, 'WC_Order')) {

            if ($new_invoice_number != '' && $existing_invoice_number == '') {
                $order->add_order_note(sprintf(__('%1$s added %2$s %3$s.', 'invoice-gateway-for-woocommerce'), $user->display_name, $type, $new_invoice_number));
            } elseif ($new_invoice_number == '' && $existing_invoice_number != '') {
                $order->add_order_note(sprintf(__('%1$s removed %2$s %3$s.', 'invoice-gateway-for-woocommerce'), $user->display_name, $type, $existing_invoice_number));
            } elseif ($new_invoice_number != $existing_invoice_number) {
                $order->add_order_note(sprintf(__('%1$s updated %2$s from %3$s to %4$s.', 'invoice-gateway-for-woocommerce'), $user->display_name, $type, $existing_invoice_number, $new_invoice_number));
            }

        }

    }

    /**
     * Save Purchase Order Number after order is processed.
     *
     * @since 1.0.0
     * @access public
     *
     * @param int       $order_id       Order ID.
     * @param array     $posted_data    Order Details.
     * @param object    $order          WC_Order Object.
     */
    public function wc_checkout_order_processed($order_id, $posted_data, $order) {

        if (isset($_REQUEST['igfw_purchase_order_number']) && !empty($_REQUEST['igfw_purchase_order_number'])) {
            $po_number = sanitize_text_field($_REQUEST['igfw_purchase_order_number']);
            update_post_meta($order_id, Plugin_Constants::Purchase_Order_Number, $po_number);
        }

    }

    /**
     * Execute url coupon model.
     *
     * @inherit IGFW\Interfaces\Model_Interface
     *
     * @since 1.0.0
     * @access public
     */
    public function run() {

        add_action('add_meta_boxes', array($this, 'add_order_invoice_meta_box'));
        add_action('igfw_invoice_gateway_meta_box', array($this, 'add_invoice_number_field'));
        add_action('save_post', array($this, 'save_invoice_data'), 10, 1);

        // Order Processed
        add_action('woocommerce_checkout_order_processed', array($this, 'wc_checkout_order_processed'), 10, 3);

    }

}