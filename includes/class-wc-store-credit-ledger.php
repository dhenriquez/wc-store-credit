<?php
/**
 * Ledger for Store Credit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Store_Credit_Ledger {

	public function __construct() {
		add_action( 'wc_store_credit_daily_cron', array( $this, 'process_pending_transactions' ) );
		
		if ( ! wp_next_scheduled( 'wc_store_credit_daily_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'wc_store_credit_daily_cron' );
		}
	}

	/**
	 * Insert a transaction and optionally update balance
	 */
	public static function add_transaction( $user_id, $amount, $type, $status = 'available', $comment = '', $order_id = null ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'store_credit_transactions';

		$wpdb->insert(
			$table_name,
			array(
				'user_id'    => $user_id,
				'amount'     => $amount,
				'type'       => $type,
				'status'     => $status,
				'comment'    => $comment,
				'order_id'   => $order_id,
				'created_at' => current_time( 'mysql' )
			),
			array( '%d', '%f', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( $status === 'available' ) {
			self::update_user_balance( $user_id );
		}
	}

	/**
	 * Recalculate and update the user meta balance
	 */
	public static function update_user_balance( $user_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'store_credit_transactions';

		$balance = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(amount) FROM $table_name WHERE user_id = %d AND status = 'available'",
			$user_id
		) );

		$balance = $balance ? (float) $balance : 0.0;
		update_user_meta( $user_id, '_wc_store_credit_balance', $balance );
		return $balance;
	}

	/**
	 * Get user balance
	 */
	public static function get_user_balance( $user_id ) {
		$balance = get_user_meta( $user_id, '_wc_store_credit_balance', true );
		if ( $balance === '' ) {
			return self::update_user_balance( $user_id );
		}
		return (float) $balance;
	}

	/**
	 * Cron job: process pending transactions and make them available
	 */
	public function process_pending_transactions() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'store_credit_transactions';
		
		$pending_days = get_option( 'wc_store_credit_pending_days', 14 );
		
		// Find transactions older than pending_days that are still pending
		$transactions = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, user_id FROM $table_name 
			WHERE status = 'pending' 
			AND created_at <= %s",
			gmdate( 'Y-m-d H:i:s', strtotime( "-$pending_days days" ) )
		) );

		if ( ! empty( $transactions ) ) {
			foreach ( $transactions as $tx ) {
				$wpdb->update(
					$table_name,
					array( 'status' => 'available' ),
					array( 'id' => $tx->id ),
					array( '%s' ),
					array( '%d' )
				);
				self::update_user_balance( $tx->user_id );
				
				// Hook for notifications
				do_action( 'wc_store_credit_made_available', $tx->id, $tx->user_id );
			}
		}
	}
}

new WC_Store_Credit_Ledger();
