<?php
/**
 * Handles spending store credit at checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Store_Credit_Checkout {

	public function __construct() {
		// Register additional checkout field for WooCommerce Blocks (WC 8.9+)
		add_action( 'woocommerce_init', array( $this, 'register_additional_fields' ) );

		// Display checkout field for logged-in users or promo for guests (Classic Checkout)
		add_action( 'woocommerce_before_checkout_form', array( $this, 'checkout_credit_form' ), 10 );
		
		// AJAX action to apply/remove credit (Classic Checkout)
		add_action( 'wp_ajax_wc_store_credit_apply', array( $this, 'ajax_apply_credit' ) );
		
		// Apply discount as a fee
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'calculate_credit_discount' ), 20, 1 );

		// Process order and deduct credit
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_order_credit' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'process_order_credit_store_api' ), 10, 1 );
	}

	public function register_additional_fields() {
		if ( function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			$user_id = get_current_user_id();
			$balance = 0;
			
			// We check class_exists in case this runs early or is called independently.
			if ( $user_id && class_exists( 'WC_Store_Credit_Ledger' ) ) {
				$balance = WC_Store_Credit_Ledger::get_user_balance( $user_id );
			}
			
			$label = __( 'Monto de Crédito a usar', 'wc-store-credit' );
			if ( $balance > 0 ) {
				// WooCommerce blocks will automatically append " (opcional)" since required is false
				// We must use strip_tags because wc_price returns HTML and Blocks Checkout escapes it.
				$plain_price = html_entity_decode( strip_tags( wc_price( $balance ) ) );
				$label = sprintf( __( 'Monto de Crédito %s disponible a usar', 'wc-store-credit' ), $plain_price );
			}

			woocommerce_register_additional_checkout_field(
				array(
					'id'         => 'wc-store-credit/amount',
					'label'      => $label,
					'location'   => 'order',
					'type'       => 'text',
					'required'   => false,
				)
			);
		}
	}

	public function checkout_credit_form() {
		if ( ! is_user_logged_in() ) {
			// Promotional message for guests uses display class logic for consistency
			$cashback_percentage = (float) get_option( 'wc_store_credit_cashback_percentage', 1 );
			if ( $cashback_percentage <= 0 ) return;

			$eligible_amount = 0;
			if ( WC()->cart ) {
				foreach ( WC()->cart->get_cart() as $cart_item ) {
					$eligible_amount += $cart_item['line_subtotal'];
				}
			}
			$cashback_amount = $eligible_amount * ( $cashback_percentage / 100 );

			if ( $cashback_amount > 0 ) {
				$msg = get_option( 'wc_store_credit_msg_guest', 'Crea una cuenta o inicia sesión para ganar {amount} de cashback.' );
				$login_url = '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . __( 'inicia sesión', 'wc-store-credit' ) . '</a>';
				$register_url = '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . __( 'Crea una cuenta', 'wc-store-credit' ) . '</a>';
				
				$msg = str_replace( '{amount}', wc_price( $cashback_amount ), $msg );
				$msg = str_replace( '{login_url}', $login_url, $msg );
				$msg = str_replace( '{register_url}', $register_url, $msg );

				wc_print_notice( wp_kses_post( $msg ), 'notice' );
			}
			return;
		}

		$user_id = get_current_user_id();
		$balance = WC_Store_Credit_Ledger::get_user_balance( $user_id );

		if ( $balance > 0 ) {
			$applied_amount = WC()->session->get( 'wc_store_credit_applied_amount', 0 );
			
			echo '<div class="wc-store-credit-checkout-box" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ccc; background: #fff;">';
			echo '<h4>' . __( 'Crédito de Tienda', 'wc-store-credit' ) . '</h4>';
			echo '<p>' . sprintf( __( 'Tienes %s disponibles.', 'wc-store-credit' ), '<strong>' . wc_price( $balance ) . '</strong>' ) . '</p>';
			
			if ( $applied_amount > 0 ) {
				echo '<p><strong>' . sprintf( __( 'Aplicando descuento de: %s', 'wc-store-credit' ), wc_price( $applied_amount ) ) . '</strong></p>';
				echo '<button type="button" class="button" id="wc_remove_store_credit">' . __( 'Quitar Crédito', 'wc-store-credit' ) . '</button>';
			} else {
				echo '<div style="display:flex; align-items:center; gap:10px;">';
				echo '<input type="number" id="wc_store_credit_input_amount" step="0.01" min="0.01" max="' . esc_attr( $balance ) . '" placeholder="' . esc_attr( $balance ) . '" style="width: 120px;" />';
				echo '<button type="button" class="button" id="wc_apply_store_credit">' . __( 'Aplicar Crédito', 'wc-store-credit' ) . '</button>';
				echo '</div>';
			}
			echo '</div>';
			
			?>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				$(document.body).on('click', '#wc_apply_store_credit, #wc_remove_store_credit', function(e) {
					e.preventDefault();
					var action = $(this).attr('id') === 'wc_apply_store_credit' ? 'apply' : 'remove';
					var amount = $('#wc_store_credit_input_amount').val();
					
					if ( action === 'apply' && (!amount || amount <= 0) ) {
						amount = $('#wc_store_credit_input_amount').attr('max');
					}
					
					$(this).prop('disabled', true);
					
					$.ajax({
						type: 'POST',
						url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
						data: {
							action: 'wc_store_credit_apply',
							credit_action: action,
							amount: amount,
							security: '<?php echo wp_create_nonce( 'wc_store_credit_nonce' ); ?>'
						},
						success: function(response) {
							$('body').trigger('update_checkout');
						}
					});
				});
			});
			</script>
			<?php
		}
	}

	public function ajax_apply_credit() {
		check_ajax_referer( 'wc_store_credit_nonce', 'security' );
		
		$action = isset( $_POST['credit_action'] ) ? sanitize_text_field( wp_unslash( $_POST['credit_action'] ) ) : '';
		
		if ( $action === 'apply' ) {
			$amount = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
			WC()->session->set( 'wc_store_credit_applied_amount', $amount );
		} else {
			WC()->session->set( 'wc_store_credit_applied_amount', 0 );
		}
		
		wp_send_json_success();
	}

	public function calculate_credit_discount( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$applied_amount = WC()->session->get( 'wc_store_credit_applied_amount', 0 );
		
		// Attempt to read from WooCommerce Blocks additional checkout fields if not applied via session
		if ( empty( $applied_amount ) ) {
			// In WooCommerce Blocks, the checkout field is processed during checkout.
			// It may be in the request data depending on how the request was sent.
			// We can attempt to parse it if we are in an API request.
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				$request_body = file_get_contents( 'php://input' );
				$data = json_decode( $request_body, true );
				if ( isset( $data['extensions']['wc-store-credit/amount'] ) ) {
					$applied_amount = floatval( $data['extensions']['wc-store-credit/amount'] );
				} elseif ( isset( $data['additional_fields']['wc-store-credit/amount'] ) ) {
					$applied_amount = floatval( $data['additional_fields']['wc-store-credit/amount'] );
				}
			}
		}

		if ( $applied_amount <= 0 ) {
			return;
		}

		$user_id = get_current_user_id();
		$balance = WC_Store_Credit_Ledger::get_user_balance( $user_id );

		if ( $balance <= 0 ) {
			WC()->session->set( 'wc_store_credit_applied_amount', 0 );
			return;
		}

		$max_discount = $cart->get_subtotal() + $cart->get_subtotal_tax();
		
		$discount = min( $balance, $max_discount, $applied_amount );

		if ( $discount > 0 ) {
			$cart->add_fee( __( 'Store Credit', 'wc-store-credit' ), -$discount, false );
		}
	}

	public function process_order_credit( $order_id, $posted_data, $order ) {
		$this->deduct_credit_from_order( $order );
	}

	public function process_order_credit_store_api( $order ) {
		// When processed via Store API, the additional field is saved as order meta.
		// Let's check if the field was filled out, and apply it as a fee if it wasn't already applied.
		$additional_field_amount = $order->get_meta( 'wc-store-credit/amount' );
		if ( ! empty( $additional_field_amount ) && is_numeric( $additional_field_amount ) ) {
			// Need to verify if the fee was actually added.
			$has_fee = false;
			foreach ( $order->get_fees() as $fee ) {
				if ( $fee->get_name() === __( 'Store Credit', 'wc-store-credit' ) ) {
					$has_fee = true;
					break;
				}
			}

			if ( ! $has_fee ) {
				// If the fee wasn't added dynamically, we need to add it now and recalculate totals.
				$user_id = $order->get_customer_id();
				$balance = WC_Store_Credit_Ledger::get_user_balance( $user_id );
				$amount = floatval( $additional_field_amount );
				$max_discount = $order->get_subtotal() + $order->get_cart_tax();
				$discount = min( $balance, $max_discount, $amount );

				if ( $discount > 0 ) {
					$fee = new WC_Order_Item_Fee();
					$fee->set_name( __( 'Store Credit', 'wc-store-credit' ) );
					$fee->set_amount( -$discount );
					$fee->set_total( -$discount );
					$order->add_item( $fee );
					$order->calculate_totals();
					$order->save();
				}
			}
		}

		$this->deduct_credit_from_order( $order );
	}

	private function deduct_credit_from_order( $order ) {
		if ( ! $order->get_customer_id() ) {
			return;
		}

		$credit_used = 0;
		foreach ( $order->get_fees() as $fee ) {
			if ( $fee->get_name() === __( 'Store Credit', 'wc-store-credit' ) ) {
				$credit_used = abs( $fee->get_total() );
				break;
			}
		}

		if ( $credit_used > 0 ) {
			$user_id = $order->get_customer_id();
			
			// Prevent double deduction
			if ( $order->get_meta( '_wc_store_credit_used' ) ) {
				return;
			}
			
			WC_Store_Credit_Ledger::add_transaction(
				$user_id,
				-$credit_used,
				'spend',
				'available',
				sprintf( __( 'Used in order #%d', 'wc-store-credit' ), $order->get_id() ),
				$order->get_id()
			);

			$order->update_meta_data( '_wc_store_credit_used', $credit_used );
			$order->save();
			
			// Clear session
			if ( isset( WC()->session ) ) {
				WC()->session->set( 'wc_store_credit_applied_amount', 0 );
			}
		}
	}
}

new WC_Store_Credit_Checkout();
