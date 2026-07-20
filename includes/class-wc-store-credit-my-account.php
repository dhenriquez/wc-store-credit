<?php
/**
 * Handles My Account Store Credit Tab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Store_Credit_My_Account {

	public function __construct() {
		// Register endpoint
		add_action( 'init', array( $this, 'add_endpoint' ) );
		
		// Add query vars
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Insert into My Account menu
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );

		// Render the tab
		add_action( 'woocommerce_account_store-credit_endpoint', array( $this, 'endpoint_content' ) );
		
		// Flush rewrite rules on activation is better, but doing it in a hook is simpler for now
		// In a real production plugin we'd hook into register_activation_hook for flush_rewrite_rules
	}

	public function add_endpoint() {
		add_rewrite_endpoint( 'store-credit', EP_ROOT | EP_PAGES );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'store-credit';
		return $vars;
	}

	public function add_menu_item( $items ) {
		// Insert before logout
		$logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : __( 'Logout', 'woocommerce' );
		unset( $items['customer-logout'] );
		
		$items['store-credit'] = __( 'Crédito de Tienda', 'wc-store-credit' );
		$items['customer-logout'] = $logout;
		
		return $items;
	}

	public function endpoint_content() {
		$user_id = get_current_user_id();
		$balance = WC_Store_Credit_Ledger::get_user_balance( $user_id );
		
		echo '<h3>' . __( 'Mi Crédito de Tienda', 'wc-store-credit' ) . '</h3>';
		echo '<p>' . sprintf( __( 'Tu saldo actual disponible es de <strong>%s</strong>.', 'wc-store-credit' ), wc_price( $balance ) ) . '</p>';

		global $wpdb;
		$table_name = $wpdb->prefix . 'store_credit_transactions';
		$transactions = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC",
			$user_id
		) );

		if ( empty( $transactions ) ) {
			echo '<p>' . __( 'Aún no tienes movimientos de crédito.', 'wc-store-credit' ) . '</p>';
		} else {
			echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">';
			echo '<thead><tr>';
			echo '<th class="woocommerce-orders-table__header"><span class="nobr">' . __( 'Fecha', 'wc-store-credit' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header"><span class="nobr">' . __( 'Monto', 'wc-store-credit' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header"><span class="nobr">' . __( 'Estado', 'wc-store-credit' ) . '</span></th>';
			echo '<th class="woocommerce-orders-table__header"><span class="nobr">' . __( 'Detalle', 'wc-store-credit' ) . '</span></th>';
			echo '</tr></thead><tbody>';
			
			foreach ( $transactions as $tx ) {
				$color = $tx->amount > 0 ? 'green' : 'red';
				echo '<tr class="woocommerce-orders-table__row">';
				echo '<td class="woocommerce-orders-table__cell" data-title="' . esc_attr__( 'Fecha', 'wc-store-credit' ) . '">' . esc_html( wp_date( get_option( 'date_format' ), strtotime( $tx->created_at ) ) ) . '</td>';
				echo '<td class="woocommerce-orders-table__cell" data-title="' . esc_attr__( 'Monto', 'wc-store-credit' ) . '" style="color:' . $color . '; font-weight:bold;">' . wc_price( $tx->amount ) . '</td>';
				
				// Translate status
				$status_text = $tx->status === 'available' ? __( 'Disponible', 'wc-store-credit' ) : ( $tx->status === 'pending' ? __( 'Pendiente', 'wc-store-credit' ) : __( 'Cancelado/Expirado', 'wc-store-credit' ) );
				echo '<td class="woocommerce-orders-table__cell" data-title="' . esc_attr__( 'Estado', 'wc-store-credit' ) . '">' . esc_html( $status_text ) . '</td>';
				
				echo '<td class="woocommerce-orders-table__cell" data-title="' . esc_attr__( 'Detalle', 'wc-store-credit' ) . '">' . esc_html( $tx->comment ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
	}
}

new WC_Store_Credit_My_Account();
