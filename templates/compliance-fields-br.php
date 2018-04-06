<?php
	$order_id = get_query_var( 'order-pay' );

if ( $order_id ) {
	$order    = wc_get_order( $order_id );
	$document = $order ? get_user_meta( $order->get_user_id(), '_ebanx_document', true ) : false;
	$address  = $order->get_address();

	$fields        = array(
		'ebanx_billing_brazil_document' => array(
			'label' => 'CPF',
			'value' => $document,
		),
		'billing_postcode'              => array(
			'label' => 'Postcode / ZIP',
			'value' => $address['postcode'],
		),
		'billing_address_1'             => array(
			'label' => __( 'Street address', 'woocommerce-gateway-ebanx' ),
			'value' => $address['address_1'],
		),
		'billing_city'                  => array(
			'label' => __( 'Town / City', 'woocommerce-gateway-ebanx' ),
			'value' => $address['city'],
		),
		'billing_country'               => array(
			'value' => $address['country'],
			'type'  => 'hidden',
		),
	);
	$countries_obj = new WC_Countries();
	$states        = $countries_obj->get_states( 'BR' );
}
?>

<?php if ( $order_id ) : ?>
	<div class="ebanx-compliance-fields ebanx-compliance-fiels-br">
		<?php foreach ( $fields as $name => $field ) : ?>
			<?php if ( isset( $field['type'] ) && 'hidden' === $field['type'] ) : ?>
				<input
					type="hidden"
					name="<?php echo "{$id}[{$name}]"; ?>"
					value="<?php echo isset( $field['value'] ) ? $field['value'] : null; ?>"
					class="input-text"
				/>
			<?php else : ?>
				<div class="ebanx-form-row ebanx-form-row-wide">
					<label for="<?php echo "{$id}[{$name}]"; ?>"><?php echo $field['label']; ?></label>
					<input
						type="<?php echo isset( $field['type'] ) ? $field['type'] : 'text'; ?>"
						name="<?php echo "{$id}[{$name}]"; ?>"
						id="<?php echo "{$id}[{$name}]"; ?>"
						value="<?php echo isset( $field['value'] ) ? $field['value'] : null; ?>"
						class="input-text"
					/>
				</div>
			<?php endif ?>
		<?php endforeach ?>
		<div class="ebanx-form-row ebanx-form-row-wide">
			<label for="<?php echo "{$id}[billing_state]"; ?>"><?php _e( 'State / County', 'woocommerce-gateway-ebanx' ); ?></label>
			<select name="<?php echo "{$id}[billing_state]"; ?>" id="<?php echo "{$id}[billing_state]"; ?>" class="ebanx-select-field">
				<option value="" selected>Select...</option>
				<?php foreach ( $states as $abbr => $name ) : ?>
					<option value="<?php echo $abbr; ?>"><?php echo $name; ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>
<?php endif ?>
