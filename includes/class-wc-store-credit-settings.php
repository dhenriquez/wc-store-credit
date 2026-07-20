<?php
/**
 * WooCommerce Settings for Store Credit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Store_Credit_Settings {

	public function __construct() {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_store_credit', array( $this, 'settings_tab' ) );
		add_action( 'woocommerce_update_options_store_credit', array( $this, 'update_settings' ) );
	}

	public function add_settings_tab( $settings_tabs ) {
		$settings_tabs['store_credit'] = __( 'Store Credit', 'wc-store-credit' );
		return $settings_tabs;
	}

	public function settings_tab() {
		woocommerce_admin_fields( $this->get_settings() );
	}

	public function update_settings() {
		woocommerce_update_options( $this->get_settings() );
	}

	public function get_settings() {
		$settings = array(
			'section_title' => array(
				'name'     => __( 'Store Credit Settings', 'wc-store-credit' ),
				'type'     => 'title',
				'desc'     => '',
				'id'       => 'wc_store_credit_section_title'
			),
			'cashback_percentage' => array(
				'name' => __( 'Cashback Percentage', 'wc-store-credit' ),
				'type' => 'number',
				'desc' => __( 'Porcentaje de la compra que se devuelve como crédito (ej: 1).', 'wc-store-credit' ),
				'id'   => 'wc_store_credit_cashback_percentage',
				'default' => 1,
				'custom_attributes' => array(
					'step' => '0.01',
					'min' => '0',
					'max' => '100'
				)
			),
			'expiration_days' => array(
				'name' => __( 'Expiration Days', 'wc-store-credit' ),
				'type' => 'number',
				'desc' => __( 'Días hasta que el crédito expira (ej: 365). 0 para nunca.', 'wc-store-credit' ),
				'id'   => 'wc_store_credit_expiration_days',
				'default' => 365,
				'custom_attributes' => array(
					'min' => '0'
				)
			),
			'pending_days' => array(
				'name' => __( 'Pending Days', 'wc-store-credit' ),
				'type' => 'number',
				'desc' => __( 'Días que el crédito permanece "Pendiente" antes de estar disponible (ej: 14).', 'wc-store-credit' ),
				'id'   => 'wc_store_credit_pending_days',
				'default' => 14,
				'custom_attributes' => array(
					'min' => '0'
				)
			),
			'exclude_sale_items' => array(
				'name' => __( 'Exclude Sale Items', 'wc-store-credit' ),
				'type' => 'checkbox',
				'desc' => __( 'No otorgar cashback por productos en oferta.', 'wc-store-credit' ),
				'id'   => 'wc_store_credit_exclude_sale_items',
				'default' => 'yes'
			),
			'exclude_categories' => array(
				'name' => __( 'Exclude Categories', 'wc-store-credit' ),
				'type' => 'multiselect',
				'desc' => __( 'Selecciona categorías de productos excluidas del cashback y pago con crédito.', 'wc-store-credit' ),
				'id'   => 'wc_store_credit_exclude_categories',
				'class' => 'wc-enhanced-select',
				'options' => $this->get_product_categories(),
				'default' => '',
			),
			'msg_product' => array(
				'name' => __( 'Mensaje en Producto', 'wc-store-credit' ),
				'type' => 'text',
				'desc' => __( 'Mensaje a mostrar debajo del precio. Usa {amount} para el monto a ganar.', 'wc-store-credit' ),
				'id'   => 'wc_store_credit_msg_product',
				'default' => '🎁 Gana {amount} de crédito con esta compra',
				'css' => 'min-width:300px;',
			),
			'msg_excluded' => array(
				'name' => __( 'Mensaje Producto Excluido', 'wc-store-credit' ),
				'type' => 'text',
				'desc' => __( 'Mensaje a mostrar cuando un producto no aplica para cashback.', 'wc-store-credit' ),
				'id'   => 'wc_store_credit_msg_excluded',
				'default' => 'Este producto no aplica para cashback',
				'css' => 'min-width:300px;',
			),
			'msg_guest' => array(
				'name' => __( 'Mensaje Invitados (Checkout/Producto)', 'wc-store-credit' ),
				'type' => 'textarea',
				'desc' => __( 'Mensaje a invitados. Usa {amount}, {login_url}, {register_url}.', 'wc-store-credit' ),
				'id'   => 'wc_store_credit_msg_guest',
				'default' => 'Crea una cuenta o inicia sesión para ganar {amount} de cashback.',
				'css' => 'min-width:300px; min-height:80px;',
			),
			'section_end' => array(
				'type' => 'sectionend',
				'id' => 'wc_store_credit_section_end'
			)
		);
		return apply_filters( 'wc_store_credit_settings', $settings );
	}

	private function get_product_categories() {
		$categories = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		) );
		$options = array();
		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			foreach ( $categories as $category ) {
				$options[ $category->term_id ] = $category->name;
			}
		}
		return $options;
	}
}

new WC_Store_Credit_Settings();
