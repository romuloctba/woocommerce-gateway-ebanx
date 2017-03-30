<?php

if (!defined('ABSPATH')) {
	exit;
}

abstract class WC_EBANX_Credit_Card_Gateway extends WC_EBANX_Gateway
{
	protected $instalment_rates = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->api_name = '_creditcard';

		parent::__construct();

		add_action('wp_ajax_capture_payment_action', array($this, 'capture_payment_action'));

		add_filter('woocommerce_admin_order_actions', array($this, 'add_capture_order_actions_button'), PHP_INT_MAX, 2);

		if ($this->get_setting_or_default('interest_rates_enabled', 'no') == 'yes') {
			$max_instalments = $this->configs->settings['credit_card_instalments'];
			for ($i=1; $i <= $max_instalments; $i++) {
				$field = 'interest_rates_' . sprintf("%02d", $i);
				$this->instalment_rates[$i] = 0;
				if (is_numeric($this->configs->settings[$field])) {
					$this->instalment_rates[$i] = $this->configs->settings[$field] / 100;
				}
			}
		}
	}

	/**
	 * Check the Auto Capture
	 *
	 * @param  array $actions
	 * @return array
	 */
	public function auto_capture($actions) {
		if (is_array($actions)) {
			$actions['custom_action'] = __('Capture by EBANX');
		}

		return $actions;
	}

	/**
	 * Check if the order can be captured
	 *
	 * @param $order
	 * @return bool
	 */
	public function enable_capture($order) {
		return $order->get_status() == 'on-hold' && $order->payment_method == $this->id;
	}

	/**
	 *  Add button for capture in the admin orders page
	 *
	 * @param $actions
	 * @param $order
	 * @return mixed
	 */
	public function add_capture_order_actions_button($actions, $order ) {
		if ($this->enable_capture($order) && current_user_can('administrator')) {
			$actions['ebanx_capture_payment'] = array(
				'url'       => wp_nonce_url( 'admin-ajax.php?action=capture_payment_action&order_id=' . $order->id),
				'name'      => __( 'Capture Now', 'woocommerce' ),
				'action'	=> 'capture_payment_action'
			);
		}

		return $actions;
	}

	/**
	 * Action to capture the payment
	 *
	 * @return void
	 */
	public function capture_payment_action() {
		$redirect = admin_url('edit.php?post_type=shop_order');
		$order = wc_get_order($_REQUEST['order_id']);
		$recapture = false;

		if (!$this->enable_capture($order)) {
			wp_redirect($redirect);
			die();
		}

		\Ebanx\Config::set(array(
			'integrationKey' => $this->private_key,
			'testMode' => $this->is_sandbox_mode,
			'directMode' => true,
		));

		$request = \Ebanx\Ebanx::doCapture(array('hash' => get_post_meta($order->id, '_ebanx_payment_hash', true)));

		if ($request->status === 'ERROR') {
			if ($request->status_code === 'BC-CAP-3') {
				$message = __('EBANX - Payment cannot be captured, changing it to Failed.', 'woocommerce-gateway-ebanx');

				$request->payment->status = 'CA';
			}
			else if ($request->status_code === 'BP-CAP-4') {
				$message = __('EBANX - Payment has already been captured, changing it to Processing.', 'woocommerce-gateway-ebanx');

				$request->payment->status = 'CO';
				$recapture = true;
			}
			else if ($request->status_code === 'BC-CAP-5') {
				$message = __('EBANX - Payment cannot be captured, changing it to Pending.', 'woocommerce-gateway-ebanx');

				$request->payment->status = 'OP';
			}
			else {
				$mssage = sprintf(__('EBANX - Unknown error, enter in contact with Ebanx and inform this error code: %s.', 'woocommerce-gateway-ebanx'), $request->payment->status_code);
			}

			WC_EBANX::log($message);
			WC_EBANX_Flash::add_message($message, 'warning', true);
		}

		if ($request->payment->status == 'CO') {
			$order->payment_complete();
			$order->update_status('processing');

			if (!$recapture) {
				$order->add_order_note(sprintf(__('EBANX: Transaction captured by %s', 'woocommerce-gateway-ebanx'), wp_get_current_user()->data->user_email));
			}
		}
		else if ($request->payment->status == 'CA') {
			$order->payment_complete();
			$order->update_status('failed');
			$order->add_order_note(__('EBANX: Transaction Failed', 'woocommerce-gateway-ebanx'));
		}
		else if ($request->payment->status == 'OP') {
			$order->update_status('pending');
			$order->add_order_note(__('EBANX: Transaction Pending', 'woocommerce-gateway-ebanx'));
		}

		wp_redirect($redirect);
		die();
	}

	/**
	 * Insert the necessary assets on checkout page
	 *
	 * @return void
	 */
	public function checkout_assets()
	{
		if (is_checkout()) {
			wp_enqueue_script('wc-credit-card-form');
			// Using // to avoid conflicts between http and https protocols
			wp_enqueue_script('ebanx', '//js.ebanx.com/ebanx-1.5.min.js', '', null, true);
			wp_enqueue_script('woocommerce_ebanx_jquery_mask', plugins_url('assets/js/jquery-mask.js', WC_EBANX::DIR), array('jquery'), WC_EBANX::VERSION, true);
			wp_enqueue_script('woocommerce_ebanx', plugins_url('assets/js/credit-card.js', WC_EBANX::DIR), array('jquery-payment', 'ebanx'), WC_EBANX::VERSION, true);

			// If we're on the checkout page we need to pass ebanx.js the address of the order.
			if (is_checkout_pay_page() && isset($_GET['order']) && isset($_GET['order_id'])) {
				$order_key = urldecode($_GET['order']);
				$order_id = absint($_GET['order_id']);
				$order = wc_get_order($order_id);

				if ($order->id === $order_id && $order->order_key === $order_key) {
					static::$ebanx_params['billing_first_name'] = $order->billing_first_name;
					static::$ebanx_params['billing_last_name'] = $order->billing_last_name;
					static::$ebanx_params['billing_address_1'] = $order->billing_address_1;
					static::$ebanx_params['billing_address_2'] = $order->billing_address_2;
					static::$ebanx_params['billing_state'] = $order->billing_state;
					static::$ebanx_params['billing_city'] = $order->billing_city;
					static::$ebanx_params['billing_postcode'] = $order->billing_postcode;
					static::$ebanx_params['billing_country'] = $order->billing_country;
				}
			}
		}

		parent::checkout_assets();
	}

	/**
	 * Mount the data to send to EBANX API
	 *
	 * @param WC_Order $order
	 * @return array
	 * @throws Exception
	 */
	protected function request_data($order)
	{
		if (empty($_POST['ebanx_token']) ||
			empty($_POST['ebanx_masked_card_number']) ||
			empty($_POST['ebanx_brand']) ||
			empty($_POST['ebanx_billing_cvv'])
		) {
			throw new Exception('MISSING-CARD-PARAMS');
		}

		if (empty($_POST['ebanx_is_one_click']) && empty($_POST['ebanx_device_fingerprint'])) {
			throw new Exception('MISSING-DEVICE-FINGERPRINT');
		}

		$data = parent::request_data($order);

		if (in_array($this->getTransactionAddress('country'), WC_EBANX_Constants::$CREDIT_CARD_COUNTRIES)) {
			$data['payment']['instalments'] = '1';

			if ($this->configs->settings['credit_card_instalments'] > 1 && isset($_POST['ebanx_billing_instalments'])) {
				$data['payment']['instalments'] = $_POST['ebanx_billing_instalments'];
			}
		}

		if (!empty($_POST['ebanx_device_fingerprint'])) {
			$data['device_id'] = $_POST['ebanx_device_fingerprint'];
		}

		$data['payment']['payment_type_code'] = $_POST['ebanx_brand'];
		$data['payment']['creditcard'] = array(
			'token' => $_POST['ebanx_token'],
			'card_cvv' => $_POST['ebanx_billing_cvv'],
			'auto_capture' => ($this->configs->settings['capture_enabled'] === 'yes'),
		);

		return $data;
	}

	/**
	 * Process the response of request from EBANX API
	 *
	 * @param  Object $request The result of request
	 * @param  WC_Order $order   The order created
	 * @return void
	 */
	protected function process_response($request, $order)
	{
		if ($request->status == 'ERROR' || !$request->payment->pre_approved) {
			return $this->process_response_error($request, $order);
		}

		parent::process_response($request, $order);
	}

	/**
	 * Save order's meta fields for future use
	 *
	 * @param  WC_Order $order The order created
	 * @param  Object $request The request from EBANX success response
	 * @return void
	 */
	protected function save_order_meta_fields($order, $request)
	{
		parent::save_order_meta_fields($order, $request);

		update_post_meta($order->id, '_cards_brand_name', $request->payment->payment_type_code);
		update_post_meta($order->id, '_instalments_number', $request->payment->instalments);
		update_post_meta($order->id, '_masked_card_number', $_POST['ebanx_masked_card_number']);
	}

	/**
	 * Save user's meta fields for future use
	 *
	 * @param  WC_Order $order The order created
	 * @return void
	 */
	protected function save_user_meta_fields($order)
	{
		parent::save_user_meta_fields($order);

		if ($this->userId && $this->configs->settings['save_card_data'] === 'yes' && isset($_POST['ebanx-save-credit-card']) && $_POST['ebanx-save-credit-card'] === 'yes') {
			$cards = get_user_meta($this->userId, '_ebanx_credit_card_token', true);
			$cards = !empty($cards) ? $cards : [];

			$card = new \stdClass();

			$card->brand = $_POST['ebanx_brand'];
			$card->token = $_POST['ebanx_token'];
			$card->masked_number = $_POST['ebanx_masked_card_number'];

			foreach ($cards as $cd) {
				if (empty($cd)) {
					continue;
				}

				if ($cd->masked_number == $card->masked_number && $cd->brand == $card->brand) {
					$cd->token = $card->token;
					unset($card);
				}
			}

			// TODO: Implement token due date
			if (isset($card)) {
				$cards[] = $card;
			}

			update_user_meta($this->userId, '_ebanx_credit_card_token', $cards);
		}
	}

	public function process_payment($order_id)
	{
		if ( isset( $_POST['ebanx_billing_instalments'] ) ) {
			$order = wc_get_order( $order_id );
			$total_price = $order->get_total();
			$tax_rate = 0;
			$instalments = $_POST['ebanx_billing_instalments'];
			if ( array_key_exists( $instalments, $this->instalment_rates ) ) {
				$tax_rate = $this->instalment_rates[$instalments];
			}
			$total_price += $total_price * $tax_rate;
			$order->set_total($total_price);
		}
		return parent::process_payment($order_id);
	}

	/**
	 * Calculates the max instalments allowed based on price, country and minimal instalment value
	 * given by the credit-card acquirer
	 *
	 * @param  $price double Product price used as base
	 * @return integer
	 */
	public function fetch_acquirer_max_installments_for_price($price, $country = null) {
		$max_instalments = WC_EBANX_Constants::MAX_INSTALMENTS;
		$country = $country ?: WC()->customer->get_country();

		switch (trim(strtolower($country))) {
			case 'br':
				$site_to_local_rate = $this->get_local_currency_rate_for_site(WC_EBANX_Constants::CURRENCY_CODE_BRL);
				$min_instalment_value = WC_EBANX_Constants::ACQUIRER_MIN_INSTALMENT_VALUE_BRL;
				break;
			case 'mx':
				$site_to_local_rate = $this->get_local_currency_rate_for_site(WC_EBANX_Constants::CURRENCY_CODE_MXN);
				$min_instalment_value = WC_EBANX_Constants::ACQUIRER_MIN_INSTALMENT_VALUE_MXN;
				break;
		}

		if (isset($site_to_local_rate) && isset($min_instalment_value)) {
			$local_value = $price * $site_to_local_rate;
			$max_instalments = floor($local_value / $min_instalment_value);
		}

		return $max_instalments;
	}

	/**
	 * The page of order received, we call them as "Thank you pages"
	 *
	 * @param  WC_Order $order The order created
	 * @return void
	 */
	public static function thankyou_page($order)
	{
		$order_amount = $order->get_total();
		$instalments_number = get_post_meta($order->id, '_instalments_number', true) ?: 1;

		$data = array(
			'data' => array(
				'card_brand_name' => get_post_meta($order->id, '_cards_brand_name', true),
				'order_amount' => $order_amount,
				'instalments_number' => $instalments_number,
				'instalments_amount' => round($order_amount / $instalments_number, 2),
				'masked_card' => substr(get_post_meta($order->id, '_masked_card_number', true), -4),
				'customer_email' => $order->billing_email,
				'customer_name' => $order->billing_first_name,
				'order_total' => $order->get_formatted_order_total(),
				'order_currency' => $order->get_order_currency()
			),
			'order_status' => $order->get_status(),
			'method' => $order->payment_method
		);

		parent::thankyou_page($data);
	}
}
