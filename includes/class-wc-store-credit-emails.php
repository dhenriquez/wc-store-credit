<?php
/**
 * Handles store credit notifications
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Store_Credit_Emails {

	public function __construct() {
		// Hook when pending credit becomes available
		add_action( 'wc_store_credit_made_available', array( $this, 'send_credit_earned_email' ), 10, 2 );
		
		// Hook on daily cron to check expirations
		add_action( 'wc_store_credit_daily_cron', array( $this, 'send_expiration_alerts' ) );
	}

	public function send_credit_earned_email( $tx_id, $user_id ) {
		$user_info = get_userdata( $user_id );
		if ( ! $user_info ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'store_credit_transactions';
		$tx = $wpdb->get_row( $wpdb->prepare( "SELECT amount FROM $table_name WHERE id = %d", $tx_id ) );
		if ( ! $tx ) {
			return;
		}

		$balance = WC_Store_Credit_Ledger::get_user_balance( $user_id );

		$subject = __( '¡Tienes nuevo crédito de tienda disponible!', 'wc-store-credit' );
		$message = sprintf(
			__( "Hola %s,\n\nSe han añadido %s de crédito a tu cuenta por tu reciente compra.\n\nTu saldo actual es de %s.\n\n¡Úsalo en tu próxima compra!", 'wc-store-credit' ),
			$user_info->display_name,
			wc_price( $tx->amount ),
			wc_price( $balance )
		);

		// Usar el Mailer de WooCommerce para que el diseño coincida con la tienda
		$mailer = WC()->mailer();
		$wrapped_message = $mailer->wrap_message( $subject, wpautop( $message ) );
		
		$mailer->send( $user_info->user_email, $subject, $wrapped_message );
	}

	public function send_expiration_alerts() {
		$expiration_days = (int) get_option( 'wc_store_credit_expiration_days', 365 );
		if ( $expiration_days <= 0 ) {
			return;
		}

		$alert_days = 7; // Avisar 7 días antes
		$target_days = $expiration_days - $alert_days;
		if ( $target_days <= 0 ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'store_credit_transactions';

		// Buscar transacciones que caducarán en 7 días exactos
		$transactions = $wpdb->get_results( $wpdb->prepare(
			"SELECT user_id, SUM(amount) as amount FROM $table_name 
			WHERE amount > 0 AND status = 'available' 
			AND DATE(created_at) = %s
			GROUP BY user_id",
			gmdate( 'Y-m-d', strtotime( "-$target_days days" ) )
		) );

		if ( empty( $transactions ) ) {
			return;
		}

		$mailer = WC()->mailer();
		$subject = __( 'Tu crédito de tienda caducará pronto', 'wc-store-credit' );

		foreach ( $transactions as $tx ) {
			$user_info = get_userdata( $tx->user_id );
			if ( ! $user_info ) {
				continue;
			}
			
			$balance = WC_Store_Credit_Ledger::get_user_balance( $tx->user_id );
			if ( $balance <= 0 ) {
				continue;
			}

			$message = sprintf(
				__( "Hola %s,\n\nTe recordamos que parte de tu saldo (%s) expirará en %d días.\n\nTu saldo actual es de %s.\n\n¡Apresúrate y úsalo en tu próxima compra antes de perderlo!", 'wc-store-credit' ),
				$user_info->display_name,
				wc_price( $tx->amount ),
				$alert_days,
				wc_price( $balance )
			);

			$wrapped_message = $mailer->wrap_message( $subject, wpautop( $message ) );
			$mailer->send( $user_info->user_email, $subject, $wrapped_message );
		}
	}
}

new WC_Store_Credit_Emails();
