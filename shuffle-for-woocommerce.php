<?php
/**
 * Plugin Name: Shuffle For Woocommerce
 * Plugin URI: https://wordpress.org/plugins/shuffle-for-woocommerce
 * Description: Take credit card payments on your store using Shuffle.
 * Author: Shuffle
 * Author URI: https://shufflepay.co
 * Version: 1.0.0
 * Requires at least: 4.4
 * Tested up to: 5.3
 * WC requires at least: 2.6
 * WC tested up to: 3.6
 * Text Domain: woocommerce-shuffle
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function woocommerce_shuffle_missing_wc_notice() {
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Shuffle requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-shuffle' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

add_action( 'plugins_loaded', 'woocommerce_shuffle_init' );

function woocommerce_shuffle_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_shuffle_missing_wc_notice' );
		return;
	}

	if ( ! class_exists( 'WC_Shuffle' ) ) :
		define( 'WC_SHUFFLE_VERSION', '1.0.0' );
		define( 'WC_SHUFFLE_MIN_PHP_VER', '5.6.0' );
		define( 'WC_SHUFFLE_MIN_WC_VER', '2.6.0' );
		define( 'WC_SHUFFLE_MAIN_FILE', __FILE__ );
		define( 'WC_SHUFFLE_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
		define( 'WC_SHUFFLE_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

		class WC_Shuffle extends WC_Payment_Gateway {

			private static $instance;

			private $url = 'https://shufflepay.co/api/';

			public static function get_instance() {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}
				return self::$instance;
			}

			public function __construct() {
				$this->id = 'shuffle';
				$this->has_fields = true;
				$this->method_title = 'Shuffle';
				$this->method_description = 'Take credit card payments on your store using Shuffle.';
				$this->supports = ['products', 'refunds'];

				$this->init_form_fields();
				$this->init_settings();

				$this->title       = $this->get_option('title');
        		$this->description = $this->get_option('description');
        		$this->api_key = $this->get_option('api_key');

				$this->init();
			}

			public function init() {
				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				add_action('woocommerce_product_options_related', [$this, 'add_product_options']);
				add_action('woocommerce_process_product_meta', [$this, 'save_product_options']);
			}

			public function update_plugin_version() {
				delete_option( 'wc_shuffle_version' );
				update_option( 'wc_shuffle_version', WC_SHUFFLE_VERSION );
			}

			public function install() {
				if ( ! is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					return;
				}

				if ( ! defined( 'IFRAME_REQUEST' ) && ( WC_SHUFFLE_VERSION !== get_option( 'wc_shuffle_version' ) ) ) {
					do_action( 'woocommerce_shuffle_updated' );

					if ( ! defined( 'WC_SHUFFLE_INSTALLING' ) ) {
						define( 'WC_SHUFFLE_INSTALLING', true );
					}

					$this->update_plugin_version();
				}
			}

			public function plugin_action_links( $links ) {
				$plugin_links = array(
					'<a href="admin.php?page=wc-settings&tab=checkout&section=shuffle">' . esc_html__( 'Settings', 'woocommerce-shuffle' ) . '</a>',
					'<a href="https://shufflepay.co/docs" target="_blank">' . esc_html__( 'Docs', 'woocommerce-shuffle' ) . '</a>',
					'<a href="https://woocommerce.com/contact-us/" target="_blank">' . esc_html__( 'Support', 'woocommerce-shuffle' ) . '</a>',
				);
				return array_merge( $plugin_links, $links );
			}

			public function add_gateways( $methods ) {
				$methods[] = 'WC_Shuffle';
				return $methods;
			}

			public function init_form_fields() {
				$this->form_fields = [
				    'enabled' => [
				        'title' => __( 'Enable/Disable', 'woocommerce-shuffle' ),
				        'type' => 'checkbox',
				        'label' => __( 'Enable Shuffle', 'woocommerce-shuffle' ),
				        'default' => 'yes'
				    ],
				    'title' => [
				        'title' => __( 'Title', 'woocommerce-shuffle' ),
				        'type' => 'text',
				        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-shuffle' ),
				        'default' => __( 'Credit Card', 'woocommerce-shuffle' ),
				        'desc_tip'      => true,
				    ],
				    'description' => [
				        'title' => __( 'Description', 'woocommerce-shuffle' ),
				        'type' => 'text',
				        'default' => 'Pay with a Credit Card'
				    ],
				    'apisettings' => [
						'title'       => __( 'API Settings', 'woocommerce-shuffle' ),
						'type'        => 'title',
						'description' => '',
					],
				    'api_key' => [
				    	'title' => __( 'API Key', 'woocommerce-shuffle' ),
				    	'type' => 'text',
				    	'default' => ''
				    ]
				];
			}

			public function payment_fields() {
				include_once __DIR__ . '/templates/payment_fields.php';
			}

			public function get_transaction_url( $order ) {
				$this->view_transaction_url = 'https://shufflepay.co/transactions/%s';
				return parent::get_transaction_url( $order );
			}

			public function process_payment( $order_id ) {
			    global $woocommerce;
			    $order = new WC_Order( $order_id );

			    // payment info
			    $card_number = esc_attr($_POST['card_number']);
			    $card_exp = explode('/', esc_attr($_POST['card_exp']));
			    $card_cvv = esc_attr($_POST['card_cvv']);

			    $payload = [
			    	'headers' => [
			    		'Authorization' => 'Bearer ' . $this->get_option('api_key'),
			    		'Accepts' => 'application/json',
			    		'X-Shuffle-Partner' => '8231099694fbda416af080e8c48e986991d042ea6ab3c41bc20b231621ec28cf'
			    	],
			    	'body' => [
				    	'first_name' => $order->get_billing_first_name(),
				    	'last_name' => $order->get_billing_last_name(),
				    	'company_name' => $order->get_billing_company(),
				    	'email' => $order->get_billing_email(),
				    	'phone' => $order->get_billing_phone(),
				    	'billing_address' => $order->get_billing_address_1(),
				    	'billing_address2' => $order->get_billing_address_2(),
				    	'billing_city' => $order->get_billing_city(),
				    	'billing_state' => $order->get_billing_state(),
				    	'billing_zipcode' => $order->get_billing_postcode(),
				    	'billing_country' => $order->get_billing_country(),
				    	'shipping_address' => ($order->has_shipping_address()) ? $order->get_shipping_address_1() : $order->get_billing_address_1(),
				    	'shipping_address2' => ($order->has_shipping_address()) ? $order->get_shipping_address_2() : $order->get_billing_address_2(),
				    	'shipping_city' => ($order->has_shipping_address()) ? $order->get_shipping_city() : $order->get_billing_city(),
				    	'shipping_state' => ($order->has_shipping_address()) ? $order->get_shipping_state() : $order->get_billing_state(),
				    	'shipping_zipcode' => ($order->has_shipping_address()) ? $order->get_shipping_postcode() : $order->get_billing_postcode(),
				    	'shipping_country' => ($order->has_shipping_address()) ? $order->get_shipping_country() : $order->get_billing_country(),
	            		'ip_address'  => $order->get_customer_ip_address(),
	            		'card_number' => $card_number,
	            		'card_exp_month' => $card_exp[0],
	            		'card_exp_year' => $card_exp[1],
	            		'card_cvv' => $card_cvv,
	            		'amount' => $order->get_total(),
	            		'tax_amount' => $order->get_cart_tax(),
	            		'shipping_amount' => $order->get_shipping_total()
	            	]
            	];

	            $response = wp_remote_post( $this->url.'charge' , $payload);

	            if (is_wp_error( $response )) {
	                wc_add_notice( __('Payment error:', 'woothemes') . $response->get_error_message(), 'error' );
					return;
	            }
	            $response = json_decode($content);

		        if($response->status == 'error') {
		        	$errors = '';
		        	if(isset($response->transaction)) {
		        		$errors .= $response->transaction->response;
		        	} elseif(isset($response->fields)) {
			        	foreach($response->fields as $error) {
			        		$errors .= $error[0] . ' ';
			        	}
			        } else {
			        	$errors .= $response->message;
			        }
			    	wc_add_notice( __('Payment error:', 'woothemes') . trim($errors), 'error' );
					return;
				} elseif($response->status == 'success') {
					$order->update_meta_data('shuffle_transaction_id', $response->transaction->id);
			    	$order->payment_complete( $response->transaction->id );
			    	$order->reduce_order_stock();
			    	$woocommerce->cart->empty_cart();
				    return [
				        'result' => 'success',
				        'redirect' => $this->get_return_url( $order )
				    ];
				}
			}

			public function can_refund_order( $order ) {
				$has_api_creds = $this->get_option( 'api_key' );
				return $order && $order->get_transaction_id() && $has_api_creds;
			}

			public function process_refund( $order_id, $amount = null, $reason = '' ) {
				$order = wc_get_order( $order_id );

				if ( ! $this->can_refund_order( $order ) ) {
					return new WP_Error( 'error', __( 'Refund failed.', 'woocommerce-shuffle' ) );
				}

				$payload = [
					'headers' => [
						'Authorization' => 'Bearer ' . $this->get_option('api_key'),
						'Accepts' => 'application/json',
						'X-Shuffle-Partner' => '8231099694fbda416af080e8c48e986991d042ea6ab3c41bc20b231621ec28cf'
					],
					'body' => [
			    		'transaction_id' => get_post_meta( $order_id, 'shuffle_transaction_id', true ),
			    		'amount' => ($amount) ? esc_attr($amount) : $order->get_total(),
			    	]
				];

				$response = wp_remote_post( $this->url.'refund' , $payload);

	            if (is_wp_error( $response )) {
	                return new WP_Error( 'error', $response->get_error_message() );
	            }
		       	$response = json_decode($content);

		       	if($response->status == 'error') {
		       		$errors = '';
		       		if(isset($response->transaction)) {
		        		$errors .= $response->transaction->response;
		        	} elseif(isset($response->fields)) {
			        	foreach($response->fields as $error) {
			        		$errors .= $error[0] . ' ';
			        	}
			        } else {
			        	$errors .= $response->message;
			        }
		       		return new WP_Error( 'error', trim($errors) );
		       	} elseif($response->status == 'success') {
		       		$order->add_order_note( __( 'Refunded $'.number_format(esc_attr($response->transaction->refunded_amount), 2, '.', ','), 'woocommerce-shuffle' ) );
					return true;
		       	}
			}
		}

		WC_Shuffle::get_instance();
	endif;
}
