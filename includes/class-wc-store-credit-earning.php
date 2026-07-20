<?php
/**
 * Handles earning store credit (cashback)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Store_Credit_Earning {

	public function __construct() {
		add_action( 'woocommerce_order_status_completed', array( $this, 'process_order_cashback' ), 10, 1 );
	}

	public function process_order_cashback( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_user_id() ) {
			return; // Only for logged in users
		}

		// Prevent duplicate processing
		if ( $order->get_meta( '_wc_store_credit_processed' ) ) {
			return;
		}

		$cashback_percentage = (float) get_option( 'wc_store_credit_cashback_percentage', 1 );
		if ( $cashback_percentage <= 0 ) {
			return;
		}

		$eligible_amount = $this->calculate_eligible_amount( $order );
		if ( $eligible_amount <= 0 ) {
			return;
		}

		$cashback_amount = round( $eligible_amount * ( $cashback_percentage / 100 ), 2 );
		
		if ( $cashback_amount > 0 ) {
			// Get pending days
			$pending_days = (int) get_option( 'wc_store_credit_pending_days', 14 );
			$status = $pending_days > 0 ? 'pending' : 'available';

			WC_Store_Credit_Ledger::add_transaction(
				$order->get_user_id(),
				$cashback_amount,
				'earn',
				$status,
				sprintf( __( 'Cashback for order #%d', 'wc-store-credit' ), $order_id ),
				$order_id
			);

			$order->update_meta_data( '_wc_store_credit_processed', 'yes' );
			$order->update_meta_data( '_wc_store_credit_earned', $cashback_amount );
			$order->save();
		}
	}

	private function calculate_eligible_amount( $order ) {
		$exclude_sale_items = get_option( 'wc_store_credit_exclude_sale_items', 'yes' ) === 'yes';
		$exclude_categories = get_option( 'wc_store_credit_exclude_categories', array() );
		if ( ! is_array( $exclude_categories ) ) {
			$exclude_categories = empty( $exclude_categories ) ? array() : array( $exclude_categories );
		}

		$eligible_amount = 0;

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			// Check sale
			if ( $exclude_sale_items && $product->is_on_sale() ) {
				continue;
			}

			// Check categories
			if ( ! empty( $exclude_categories ) ) {
				$terms = get_the_terms( $product->get_id(), 'product_cat' );
				$has_excluded_cat = false;
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						if ( in_array( $term->term_id, $exclude_categories ) ) {
							$has_excluded_cat = true;
							break;
						}
					}
				}
				if ( $has_excluded_cat ) {
					continue;
				}
			}

			$eligible_amount += (float) $item->get_subtotal();
		}

		return $eligible_amount;
	}
}

new WC_Store_Credit_Earning();
