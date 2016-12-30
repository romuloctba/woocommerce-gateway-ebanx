<?php
/**
 * Pagoefectivo - Payment instructions.
 *
 * @author  EBANX.com
 * @package WooCommerce_EBANX/Templates
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="ebanx-order__desc">
    <p>Acabamos de confirmar la operación y procesaremos tu orden. Imprime tu cupón y acércate a cualquier centro autorizado para realizar tu pago.</p>
    <p>Una copia del cupón fue enviada al correo electrónico:</p>
    <p><strong><?=$customer_email ?></strong></p>
    <p>Si tienes dudas, por favor escribe a <a href="mailto:soporte@ebanx.com">soporte@ebanx.com</a>.</p>
</div>

<hr>
<div class="banking-ticket__actions">
    <div class="ebanx-button--group ebanx-button--group-two">
        <a href="<?=$url_basic ?>" target="_blank" class="button banking-ticket__action">Imprimir mi cupón</a>
    </div>
</div>
<hr>

<div>
    <iframe src="<?=$url_basic; ?>" style="width: 100%; height: 1000px; border: 0px;"></iframe>
</div>
