<?php
/**
 * Handles Admin UI for User Profiles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Store_Credit_Admin_Profile {

	public function __construct() {
		// Display in user profile
		add_action( 'show_user_profile', array( $this, 'store_credit_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'store_credit_profile_fields' ) );

		// Save/process via AJAX
		add_action( 'wp_ajax_wc_store_credit_adjust_profile', array( $this, 'ajax_adjust_credit' ) );
	}

	public function store_credit_profile_fields( $user ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$balance = WC_Store_Credit_Ledger::get_user_balance( $user->ID );
		
		?>
		<h3><?php _e( 'Crédito de Tienda (Store Credit)', 'wc-store-credit' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label><?php _e( 'Saldo Actual', 'wc-store-credit' ); ?></label></th>
				<td>
					<strong id="wc_store_credit_current_balance"><?php echo wc_price( $balance ); ?></strong>
				</td>
			</tr>
			<tr>
				<th><label for="wc_store_credit_adjust_amount"><?php _e( 'Ajustar Saldo', 'wc-store-credit' ); ?></label></th>
				<td>
					<input type="number" id="wc_store_credit_adjust_amount" step="0.01" min="0.01" class="regular-text" style="width:100px;" placeholder="0.00" />
					<select id="wc_store_credit_adjust_type">
						<option value="add"><?php _e( 'Añadir (Sumar)', 'wc-store-credit' ); ?></option>
						<option value="deduct"><?php _e( 'Restar (Descontar)', 'wc-store-credit' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="wc_store_credit_adjust_note"><?php _e( 'Comentario', 'wc-store-credit' ); ?></label></th>
				<td>
					<input type="text" id="wc_store_credit_adjust_note" class="regular-text" placeholder="<?php esc_attr_e( 'Razón del ajuste...', 'wc-store-credit' ); ?>" />
					<button type="button" class="button button-secondary" id="wc_store_credit_adjust_btn"><?php _e( 'Aplicar Ajuste', 'wc-store-credit' ); ?></button>
					<span class="spinner" id="wc_store_credit_spinner"></span>
					<p class="description"><?php _e( 'Este comentario será visible para el cliente.', 'wc-store-credit' ); ?></p>
				</td>
			</tr>
		</table>
		
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#wc_store_credit_adjust_btn').on('click', function(e) {
				e.preventDefault();
				var amount = $('#wc_store_credit_adjust_amount').val();
				var type = $('#wc_store_credit_adjust_type').val();
				var note = $('#wc_store_credit_adjust_note').val();
				
				if (!amount || amount <= 0) {
					alert('Por favor ingresa un monto válido.');
					return;
				}
				
				$('#wc_store_credit_spinner').addClass('is-active');
				$(this).prop('disabled', true);
				
				$.ajax({
					type: 'POST',
					url: ajaxurl,
					data: {
						action: 'wc_store_credit_adjust_profile',
						user_id: <?php echo intval( $user->ID ); ?>,
						amount: amount,
						type: type,
						note: note,
						security: '<?php echo wp_create_nonce( 'wc_store_credit_profile_update' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							// Update balance
							$('#wc_store_credit_current_balance').html(response.data.new_balance);
							
							// Prepend new row if table exists, or reload if it doesn't
							if ($('#wc_store_credit_history_table tbody').length) {
								$('#wc_store_credit_history_table tbody').prepend(response.data.new_row);
							} else {
								window.location.reload();
							}
							
							// Reset form
							$('#wc_store_credit_adjust_amount').val('');
							$('#wc_store_credit_adjust_note').val('');
							$('#wc_store_credit_spinner').removeClass('is-active');
							$('#wc_store_credit_adjust_btn').prop('disabled', false);
							
							// Flash success
							$('#wc_store_credit_adjust_btn').after('<span class="wc-store-credit-success" style="color:green; margin-left:10px;">¡Ajuste aplicado!</span>');
							setTimeout(function() {
								$('.wc-store-credit-success').fadeOut(function(){ $(this).remove(); });
							}, 3000);
							
						} else {
							alert('Error: ' + response.data);
							$('#wc_store_credit_spinner').removeClass('is-active');
							$('#wc_store_credit_adjust_btn').prop('disabled', false);
						}
					}
				});
			});
		});
		</script>
		
		<h4><?php _e( 'Historial Reciente', 'wc-store-credit' ); ?></h4>
		<?php
		global $wpdb;
		$table_name = $wpdb->prefix . 'store_credit_transactions';
		$transactions = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
			$user->ID
		) );

		if ( empty( $transactions ) ) {
			echo '<p>' . __( 'No hay transacciones.', 'wc-store-credit' ) . '</p>';
		} else {
			echo '<table class="wp-list-table widefat fixed striped" style="max-width:800px;" id="wc_store_credit_history_table">';
			echo '<thead><tr>';
			echo '<th>' . __( 'Fecha', 'wc-store-credit' ) . '</th>';
			echo '<th>' . __( 'Monto', 'wc-store-credit' ) . '</th>';
			echo '<th>' . __( 'Tipo', 'wc-store-credit' ) . '</th>';
			echo '<th>' . __( 'Estado', 'wc-store-credit' ) . '</th>';
			echo '<th>' . __( 'Nota', 'wc-store-credit' ) . '</th>';
			echo '</tr></thead><tbody>';
			
			foreach ( $transactions as $tx ) {
				$color = $tx->amount > 0 ? 'green' : 'red';
				echo '<tr>';
				echo '<td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $tx->created_at ) ) ) . '</td>';
				echo '<td style="color:' . $color . '; font-weight:bold;">' . wc_price( $tx->amount ) . '</td>';
				echo '<td>' . esc_html( ucfirst( $tx->type ) ) . '</td>';
				echo '<td>' . esc_html( ucfirst( $tx->status ) ) . '</td>';
				echo '<td>' . esc_html( $tx->comment ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
	}

	public function ajax_adjust_credit() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		check_ajax_referer( 'wc_store_credit_profile_update', 'security' );

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		if ( ! $user_id ) {
			wp_send_json_error( 'Invalid User' );
		}

		$amount = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
		$type   = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'add';
		$note   = isset( $_POST['note'] ) ? sanitize_text_field( $_POST['note'] ) : '';

		if ( $amount > 0 ) {
			if ( $type === 'deduct' ) {
				$amount = -$amount;
			}
			
			if ( empty( $note ) ) {
				$note = __( 'Ajuste manual del administrador', 'wc-store-credit' );
			}

			WC_Store_Credit_Ledger::add_transaction(
				$user_id,
				$amount,
				'admin_adjustment',
				'available',
				$note
			);
			
			// Build new row HTML for the response
			$new_balance = WC_Store_Credit_Ledger::get_user_balance( $user_id );
			$color = $amount > 0 ? 'green' : 'red';
			$new_row = '<tr>';
			$new_row .= '<td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) ) ) . '</td>';
			$new_row .= '<td style="color:' . $color . '; font-weight:bold;">' . wc_price( $amount ) . '</td>';
			$new_row .= '<td>Admin_adjustment</td>';
			$new_row .= '<td>Available</td>';
			$new_row .= '<td>' . esc_html( $note ) . '</td>';
			$new_row .= '</tr>';

			wp_send_json_success( array(
				'new_balance' => wc_price( $new_balance ),
				'new_row'     => $new_row
			) );
		}
		wp_send_json_error( 'Invalid Amount' );
	}
}

new WC_Store_Credit_Admin_Profile();
