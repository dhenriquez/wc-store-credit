<?php
/**
 * Admin List Table for Store Credit Ledger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WC_Store_Credit_Ledger_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'Transaction', 'wc-store-credit' ),
			'plural'   => __( 'Transactions', 'wc-store-credit' ),
			'ajax'     => false
		) );
	}

	public function get_columns() {
		$columns = array(
			'id'         => __( 'ID', 'wc-store-credit' ),
			'user_id'    => __( 'Usuario', 'wc-store-credit' ),
			'amount'     => __( 'Monto', 'wc-store-credit' ),
			'type'       => __( 'Tipo', 'wc-store-credit' ),
			'status'     => __( 'Estado', 'wc-store-credit' ),
			'order_id'   => __( 'Orden', 'wc-store-credit' ),
			'comment'    => __( 'Detalle', 'wc-store-credit' ),
			'created_at' => __( 'Fecha', 'wc-store-credit' ),
		);
		return $columns;
	}

	public function get_sortable_columns() {
		$sortable_columns = array(
			'id'         => array( 'id', true ),
			'created_at' => array( 'created_at', false ),
		);
		return $sortable_columns;
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
			case 'type':
			case 'status':
			case 'comment':
			case 'created_at':
				return esc_html( $item[ $column_name ] );
			case 'amount':
				$color = $item['amount'] > 0 ? 'green' : 'red';
				return '<strong style="color:'.$color.'">' . wc_price( $item['amount'] ) . '</strong>';
			case 'user_id':
				$user = get_userdata( $item['user_id'] );
				if ( $user ) {
					return '<a href="' . esc_url( get_edit_user_link( $item['user_id'] ) ) . '">' . esc_html( $user->display_name ) . '</a>';
				}
				return $item['user_id'];
			case 'order_id':
				if ( ! empty( $item['order_id'] ) ) {
					return '<a href="' . esc_url( admin_url( 'post.php?post=' . absint( $item['order_id'] ) . '&action=edit' ) ) . '">#' . esc_html( $item['order_id'] ) . '</a>';
				}
				return '-';
			default:
				return print_r( $item, true );
		}
	}

	public function prepare_items() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'store_credit_transactions';

		$per_page = 20;
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? esc_sql( $_REQUEST['orderby'] ) : 'id';
		$order = ( ! empty( $_REQUEST['order'] ) ) ? esc_sql( $_REQUEST['order'] ) : 'DESC';
		$current_page = $this->get_pagenum();
		$offset = ( $current_page - 1 ) * $per_page;

		$total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" );
		
		$this->items = $wpdb->get_results( 
			"SELECT * FROM $table_name ORDER BY $orderby $order LIMIT $per_page OFFSET $offset", 
			ARRAY_A 
		);

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page )
		) );
	}
}

class WC_Store_Credit_Admin_List {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
	}

	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Store Credit Ledger', 'wc-store-credit' ),
			__( 'Store Credit', 'wc-store-credit' ),
			'manage_woocommerce',
			'wc-store-credit-ledger',
			array( $this, 'render_list_page' )
		);
	}

	public function render_list_page() {
		$list_table = new WC_Store_Credit_Ledger_List_Table();
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h2><?php _e( 'Store Credit Ledger', 'wc-store-credit' ); ?></h2>
			<form method="get">
				<input type="hidden" name="page" value="wc-store-credit-ledger" />
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}
}

new WC_Store_Credit_Admin_List();
