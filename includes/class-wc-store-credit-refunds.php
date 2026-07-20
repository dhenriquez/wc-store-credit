<?php
/**
 * Handles refunds and expirations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Store_Credit_Refunds {

	public function __construct() {
		// Refund action
		add_action( 'woocommerce_order_status_refunded', array( $this, 'process_refund' ), 10, 1 );
		
		// Expirations cron
		add_action( 'wc_store_credit_daily_cron', array( $this, 'process_expirations' ) );
	}

	public function process_refund( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_user_id() ) {
			return;
		}

		$user_id = $order->get_user_id();

		// Check if we need to refund spent credit
		$credit_used = (float) $order->get_meta( '_wc_store_credit_used' );
		if ( $credit_used > 0 && ! $order->get_meta( '_wc_store_credit_refunded' ) ) {
			WC_Store_Credit_Ledger::add_transaction(
				$user_id,
				$credit_used,
				'refund',
				'available',
				sprintf( __( 'Refunded credit from order #%d', 'wc-store-credit' ), $order_id ),
				$order_id
			);
			$order->update_meta_data( '_wc_store_credit_refunded', 'yes' );
		}

		// Check if we need to reverse earned credit
		$credit_earned = (float) $order->get_meta( '_wc_store_credit_earned' );
		if ( $credit_earned > 0 && ! $order->get_meta( '_wc_store_credit_earned_reversed' ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'store_credit_transactions';

			// Find the earn transaction for this order
			$tx = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, status FROM $table_name WHERE order_id = %d AND type = 'earn'",
				$order_id
			) );

			if ( $tx ) {
				if ( $tx->status === 'pending' ) {
					// Cancel the pending transaction
					$wpdb->update(
						$table_name,
						array( 'status' => 'cancelled' ),
						array( 'id' => $tx->id )
					);
				} else {
					// Create a negative transaction since it was already available (and possibly spent)
					WC_Store_Credit_Ledger::add_transaction(
						$user_id,
						-$credit_earned,
						'reversed',
						'available',
						sprintf( __( 'Reversed earned credit from refunded order #%d', 'wc-store-credit' ), $order_id ),
						$order_id
					);
				}
			}
			$order->update_meta_data( '_wc_store_credit_earned_reversed', 'yes' );
		}
		
		$order->save();
	}

	public function process_expirations() {
		$expiration_days = (int) get_option( 'wc_store_credit_expiration_days', 365 );
		if ( $expiration_days <= 0 ) {
			return; // No expiration
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'store_credit_transactions';

		// Simple FIFO expiration logic based on aggregates
		$users = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT user_id FROM $table_name WHERE amount > 0 AND status = 'available' AND created_at <= %s",
			gmdate( 'Y-m-d H:i:s', strtotime( "-$expiration_days days" ) )
		) );

		foreach ( $users as $user_id ) {
			$old_earned = $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(amount) FROM $table_name WHERE user_id = %d AND amount > 0 AND status = 'available' AND created_at <= %s",
				$user_id,
				gmdate( 'Y-m-d H:i:s', strtotime( "-$expiration_days days" ) )
			) );

			$total_spent = $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM(ABS(amount)) FROM $table_name WHERE user_id = %d AND amount < 0 AND status = 'available'",
				$user_id
			) );

			$old_earned = $old_earned ? (float) $old_earned : 0;
			$total_spent = $total_spent ? (float) $total_spent : 0;

			$to_expire = $old_earned - $total_spent;
			$current_balance = WC_Store_Credit_Ledger::get_user_balance( $user_id );
			$to_expire = min( $to_expire, $current_balance );

			if ( $to_expire > 0 ) {
				WC_Store_Credit_Ledger::add_transaction(
					$user_id,
					-$to_expire,
					'expire',
					'available',
					__( 'Credit expired', 'wc-store-credit' )
				);
			}
		}
	}
}

new WC_Store_Credit_Refunds();
