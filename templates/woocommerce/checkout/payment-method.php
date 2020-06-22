<?php
/**
 * Output a single payment method
 *
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @package     WooCommerce/Templates
 * @version     3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php /* Metodo de pago normal */  ?>
<?php if ( !property_exists($gateway, 'paymentOptions') ): ?>
	<li class="wc_payment_method payment_method_<?php echo esc_attr( $gateway->id ); ?>">
		<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio" class="input-radio" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $gateway->chosen, true ); ?> data-order_button_text="<?php echo esc_attr( $gateway->order_button_text ); ?>" />

		<label for="payment_method_<?php echo esc_attr( $gateway->id ); ?>">
			<?php echo $gateway->get_title(); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?> <?php echo $gateway->get_icon(); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?>
		</label>
		<?php if ( $gateway->has_fields() || $gateway->get_description() ) : ?>
			<div class="payment_box payment_method_<?php echo esc_attr( $gateway->id ); ?>" <?php if ( ! $gateway->chosen ) : /* phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace */ ?>style="display:none;"<?php endif; /* phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace */ ?>>
				<?php $gateway->payment_fields(); ?>
			</div>
		<?php endif; ?>
	</li>
<?php endif; ?>


<?php /* Metodo de pago con multiple opciones */  ?>
<?php if ( property_exists($gateway, 'paymentOptions') ): ?>
	<?php foreach ( $gateway->paymentOptions as $payOpt ) : ?>
		<input id="payment_option_<?php echo esc_attr( $gateway->id ); ?>_<?php echo esc_attr( $payOpt['codigo'] ); ?>" type="radio" name="payment_option" value="<?php echo esc_attr( $payOpt['codigo'] ); ?>" style="display: none;"/>
		<li class="wc_payment_method payment_method_<?php echo esc_attr( $gateway->id ); ?>_<?php echo esc_attr( $payOpt['codigo'] ); ?>">
			<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>_<?php echo esc_attr( $payOpt['codigo'] ); ?>" type="radio" class="input-radio" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" data-order_button_text="<?php echo esc_attr( $gateway->order_button_text ); ?>" />
			<label for="payment_method_<?php echo esc_attr( $gateway->id ); ?>_<?php echo esc_attr( $payOpt['codigo'] ); ?>">
				<?php echo $payOpt['nombre'] /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?> <?php echo $gateway->get_icon_opt($payOpt); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?>
			</label>
			<?php if ( $gateway->has_fields() || $gateway->get_description() ) : ?>
				<div class="payment_box payment_method_<?php echo esc_attr( $gateway->id ); ?>_<?php echo esc_attr( $payOpt['codigo'] ); ?>" <?php if ( ! $gateway->chosen ) : /* phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace */ ?>style="display:none;"<?php endif; /* phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace */ ?>>
					<?php $gateway->payment_fields_opt($payOpt); ?>
				</div>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
<?php endif; ?>
