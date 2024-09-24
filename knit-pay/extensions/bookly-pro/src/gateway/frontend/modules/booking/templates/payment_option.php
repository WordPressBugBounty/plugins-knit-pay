<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/** @var Bookly\Lib\CartInfo $cart_info */
use Bookly\Lib\Utils;
?>
<div class="bookly-box bookly-list">
	<label>
		<input type="radio" class="bookly-js-payment" name="payment-method-<?php echo $form_id; ?>" value="<?php echo $payment_method; ?>"/>
		<span><?php echo Utils\Common::getTranslatedOption( 'bookly_l10n_label_pay_' . $payment_method ); ?>
			<?php if ( $show_price ) : ?>
				<span class="bookly-js-pay"><?php echo Utils\Price::format( $cart_info->getPayNow() ); ?></span>
			<?php endif ?>
		</span>
		<?php if ( ! empty( $icon_url ) ) : ?>
			<img src="<?php echo $icon_url; ?>" alt="<?php echo $payment_method; ?>" style="height: 24px;" />
		<?php endif ?>
	</label>
	<?php if ( is_array( $payment_status ) && $payment_status['gateway'] === $payment_method && $payment_status['status'] == 'error' ) : ?>
		<div class="bookly-label-error" style="padding-top: 5px;">* <?php echo $payment_status['data']; ?></div>
	<?php endif ?>
</div>
