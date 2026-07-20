<?php
/**
 * Handles frontend display of store credit potential (Product page, Cart)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Store_Credit_Display {

	public function __construct() {
		// Under the price in single product
		add_action( 'woocommerce_single_product_summary', array( $this, 'display_product_cashback' ), 15 );
		
		// In the cart
		add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'display_cart_cashback' ) );
	}

	public function display_product_cashback() {
		global $product;

		$cashback_percentage = (float) get_option( 'wc_store_credit_cashback_percentage', 1 );
		if ( $cashback_percentage <= 0 || ! $product ) {
			return;
		}

		if ( $this->is_product_excluded( $product ) ) {
			$msg_excluded = get_option( 'wc_store_credit_msg_excluded', 'Este producto no aplica para cashback' );
			if ( ! empty( $msg_excluded ) ) {
				echo '<div class="wc-store-credit-msg-excluded" style="margin-bottom:1em; font-size: 0.9em; color: #777;">' . esc_html( $msg_excluded ) . '</div>';
			}
			return;
		}

		$min_cashback = 0;
		$max_cashback = 0;

		if ( $product->is_type( 'variable' ) ) {
			$prices = $product->get_variation_prices( true );
			if ( ! empty( $prices['price'] ) ) {
				$min_price = min( $prices['price'] );
				$max_price = max( $prices['price'] );
				$min_cashback = $min_price * ( $cashback_percentage / 100 );
				$max_cashback = $max_price * ( $cashback_percentage / 100 );
			}
		} else {
			$price = wc_get_price_to_display( $product );
			$min_cashback = $price * ( $cashback_percentage / 100 );
			$max_cashback = $min_cashback;
		}

		if ( $max_cashback <= 0 ) {
			return;
		}

		$amount_text = '';
		if ( $min_cashback === $max_cashback ) {
			$amount_text = wc_price( $min_cashback );
		} else {
			$amount_text = sprintf( __( 'entre %s y %s', 'wc-store-credit' ), wc_price( $min_cashback ), wc_price( $max_cashback ) );
		}

		$this->render_message( $amount_text );
	}

	public function display_cart_cashback() {
		$cashback_percentage = (float) get_option( 'wc_store_credit_cashback_percentage', 1 );
		if ( $cashback_percentage <= 0 ) {
			return;
		}

		$eligible_amount = 0;
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$product = $cart_item['data'];
				if ( $this->is_product_excluded( $product ) ) {
					continue;
				}
				$eligible_amount += $cart_item['line_subtotal'];
			}
		}

		if ( $eligible_amount <= 0 ) {
			return;
		}

		$cashback_amount = $eligible_amount * ( $cashback_percentage / 100 );
		if ( $cashback_amount <= 0 ) {
			return;
		}

		?>
		<tr class="order-total store-credit-potential">
			<th><?php esc_html_e( 'Cashback Estimado', 'wc-store-credit' ); ?></th>
			<td data-title="<?php esc_attr_e( 'Cashback Estimado', 'wc-store-credit' ); ?>">
				<?php 
				$this->render_message( wc_price( $cashback_amount ), true ); 
				?>
			</td>
		</tr>
		<?php
	}

	private function render_message( $amount_text, $is_cart = false ) {
		if ( is_user_logged_in() ) {
			$msg = get_option( 'wc_store_credit_msg_product', '🎁 Gana {amount} de crédito con esta compra' );
			$msg = str_replace( '{amount}', $amount_text, $msg );
		} else {
			$msg = get_option( 'wc_store_credit_msg_guest', 'Crea una cuenta o inicia sesión para ganar {amount} de cashback.' );
			$login_url = '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . __( 'inicia sesión', 'wc-store-credit' ) . '</a>';
			$register_url = '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . __( 'Crea una cuenta', 'wc-store-credit' ) . '</a>';
			
			$msg = str_replace( '{amount}', $amount_text, $msg );
			$msg = str_replace( '{login_url}', $login_url, $msg );
			$msg = str_replace( '{register_url}', $register_url, $msg );
		}

		$style = $is_cart ? '' : 'margin-bottom:1em; font-weight:bold; color: #2a2a2a;';
		echo '<div class="wc-store-credit-msg-potential" style="' . esc_attr( $style ) . '">' . wp_kses_post( $msg ) . '</div>';
	}

	private function is_product_excluded( $product ) {
		if ( ! $product ) {
			return true;
		}

		$exclude_sale_items = get_option( 'wc_store_credit_exclude_sale_items', 'yes' ) === 'yes';
		if ( $exclude_sale_items && $product->is_on_sale() ) {
			return true;
		}

		$exclude_categories = get_option( 'wc_store_credit_exclude_categories', array() );
		if ( ! is_array( $exclude_categories ) ) {
			$exclude_categories = empty( $exclude_categories ) ? array() : array( $exclude_categories );
		}

		if ( ! empty( $exclude_categories ) ) {
			$terms = get_the_terms( $product->get_id(), 'product_cat' );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( in_array( $term->term_id, $exclude_categories ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}

new WC_Store_Credit_Display();
