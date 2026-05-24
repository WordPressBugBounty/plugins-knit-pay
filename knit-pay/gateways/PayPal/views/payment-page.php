<?php
/**
 * PayPal Payment Page
 *
 * @copyright 2020-2026 Knit Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gateway_dir_url = KNITPAY_URL . '/gateways/PayPal';
$gateway_dir     = KNITPAY_DIR . 'gateways/PayPal';

$use_original_asset = $paypal_page_data['debug'] && file_exists( $gateway_dir . '/js/paypal-v6.js' );
$js_file            = $use_original_asset ? '/js/paypal-v6.js' : '/js/paypal-v6.min.js';
$css_file           = $use_original_asset ? '/css/payment.css' : '/css/payment.min.css';

?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $paypal_page_data['customer_locale'] ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="theme-color" content="#003087">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $paypal_page_data['merchant_name'] ); ?> - PayPal Checkout</title>

	<?php if ( $paypal_page_data['sandbox'] ) : ?>
	<link rel="preconnect" href="https://www.sandbox.paypal.com">
	<link rel="preconnect" href="https://api-m.sandbox.paypal.com">
	<link rel="dns-prefetch" href="https://www.sandbox.paypal.com">
	<link rel="dns-prefetch" href="https://api-m.sandbox.paypal.com">
	<?php else : ?>
	<link rel="preconnect" href="https://www.paypal.com">
	<link rel="preconnect" href="https://api-m.paypal.com">
	<link rel="dns-prefetch" href="https://www.paypal.com">
	<link rel="dns-prefetch" href="https://api-m.paypal.com">
	<?php endif; ?>
	<link rel="preconnect" href="https://www.paypalobjects.com">
	<link rel="dns-prefetch" href="https://www.paypalobjects.com">
	<link rel="stylesheet" href="<?php echo esc_url( $gateway_dir_url . $css_file ); ?>">

	<script>
		var paypalPageData = <?php echo wp_json_encode( $paypal_page_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
	</script>
	<script
		async
		crossorigin="anonymous"
		src="<?php echo esc_url( $paypal_page_data['sdk_url'] ); ?>"
		onload="if(typeof onPayPalWebSdkLoaded==='function')onPayPalWebSdkLoaded()"
		onerror="document.getElementById('pp-loading').style.display='none';document.getElementById('pp-error').style.display='flex';document.getElementById('pp-error-message').textContent='Failed to load PayPal. Please check your internet connection and try again.';document.getElementById('pp-retry-btn').disabled=false;"
	></script>
</head>
<body>
	<noscript>
		<!-- Fallback block for users with JavaScript disabled. -->
		<div class="pp-noscript">
			<p>JavaScript is required to process payments. Please enable JavaScript in your browser settings and reload this page.</p>
		</div>
	</noscript>
	<div class="pp-page">
		<!-- Sticky navy header with PayPal branding and secure-checkout badge. -->
		<header class="pp-header">
			<div class="pp-header-inner">
				<div class="pp-logo">
					<img src="<?php echo esc_url( KNITPAY_URL . '/images/paypal/icon-white.svg' ); ?>" alt="PayPal" class="pp-logo-icon">
				</div>
				<div class="pp-header-secure">
					<svg class="pp-secure-icon" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
						<path d="M8 1a4 4 0 0 0-4 4v2H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1h-1V5a4 4 0 0 0-4-4zm2.5 6h-5V5a2.5 2.5 0 0 1 5 0v2z"/>
					</svg>
					<span>Secure Checkout</span>
				</div>
			</div>
		</header>

		<main class="pp-main">
			<?php if ( $paypal_page_data['sandbox'] ) : ?>
			<div class="pp-sandbox-banner" role="alert">
				<svg class="pp-sandbox-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
					<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
				</svg>
				<span><strong>Sandbox Mode</strong> — This is a test environment. No real payment will be processed.</span>
			</div>
			<?php endif; ?>

			<!-- Two-column layout: payment form (left) + sticky order summary (right). -->
			<div class="pp-layout">
				<div class="pp-payment-section">
					<div class="pp-card">
						<div class="pp-card-header">
							<h2>Choose a payment method</h2>
						</div>
						<div class="pp-card-body">
							<!-- Skeleton loader shown while PayPal SDK is initialising. -->
							<div id="pp-loading" class="pp-loading" role="status" aria-live="polite" aria-busy="true">
								<div class="pp-skeleton">
									<div class="pp-skeleton-btn pp-skeleton-btn--primary"></div>
									<div class="pp-skeleton-row">
										<div class="pp-skeleton-btn pp-skeleton-btn--secondary"></div>
										<div class="pp-skeleton-btn pp-skeleton-btn--secondary"></div>
									</div>
									<div class="pp-skeleton-divider"></div>
									<div class="pp-skeleton-grid">
										<div class="pp-skeleton-btn pp-skeleton-btn--apm"></div>
										<div class="pp-skeleton-btn pp-skeleton-btn--apm"></div>
										<div class="pp-skeleton-btn pp-skeleton-btn--apm"></div>
										<div class="pp-skeleton-btn pp-skeleton-btn--apm"></div>
									</div>
								</div>
								<p class="pp-loading-text">Loading payment options...</p>
							</div>

							<!-- Error state with retry button for SDK or network failures. -->
							<div id="pp-error" class="pp-error" style="display: none;">
								<svg class="pp-error-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
									<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
								</svg>
								<p id="pp-error-message">Unable to load payment options. Please try again.</p>
								<button id="pp-retry-btn" class="pp-btn pp-btn-secondary" type="button">Retry</button>
							</div>

							<div id="pp-buttons-container" style="display: none;">
								<div class="pp-wallet-primary"></div>
								<div class="pp-wallet-secondary"></div>

								<!-- Pay Later messaging rendered by PayPal SDK. -->
								<div id="pp-pay-later-message" class="pp-pay-later-message">
									<paypal-message
										id="pp-message"
										auto-bootstrap
										amount="<?php echo esc_attr( $paypal_page_data['amount'] ); ?>"
										currency-code="<?php echo esc_attr( $paypal_page_data['currency_code'] ); ?>"
										text-color="BLACK"
										logo-type="MONOGRAM"
										logo-position="LEFT"
									></paypal-message>
								</div>

								<!-- PayPal-hosted card fields: number, expiry, CVV. -->
								<div id="pp-card-fields-section" class="pp-card-fields-section" style="display: none;">
									<div class="pp-divider">
										<span>or pay with card</span>
									</div>
									<div class="pp-card-field-group">
										<span class="pp-field-label">Card Number</span>
										<div id="pp-card-number" class="pp-card-field"></div>
									</div>
									<div class="pp-card-fields-row">
										<div class="pp-card-field-group">
											<span class="pp-field-label">Expiry Date</span>
											<div id="pp-card-expiry" class="pp-card-field"></div>
										</div>
										<div class="pp-card-field-group">
											<span class="pp-field-label">CVV</span>
											<div id="pp-card-cvv" class="pp-card-field"></div>
										</div>
									</div>
									<button id="pp-card-pay-btn" class="pp-btn pp-btn-pay" type="button">Pay with Card</button>
								</div>

								<!-- Divider between wallet buttons and APM grid. -->
								<div id="pp-apm-divider" class="pp-divider">
									<span>or pay with</span>
								</div>

								<!-- Additional Payment Methods (APM) dynamically populated by JS. -->
								<div id="pp-apm-list" class="pp-apm-list"></div>
								<button id="pp-apm-toggle" class="pp-apm-toggle" type="button" style="display: none;" aria-expanded="false" aria-controls="pp-apm-list">Show more payment methods</button>

								<!-- Dynamic contact fields rendered by PayPal SDK for selected APM. -->
								<div id="pp-contact-section" class="pp-contact-section" style="display: none;">
									<h3 class="pp-contact-title">Contact information</h3>
									<div id="pp-dynamic-fields"></div>
									<button id="pp-apm-pay-btn" class="pp-btn pp-btn-pay" type="button" style="display: none;" disabled>Select a payment method above</button>
									<div id="pp-contact-status-slot" role="status" aria-live="polite"></div>
								</div>
							</div>

							<!-- Inline status messages (success / warning / error) moved dynamically by JS. -->
							<div id="pp-status-message" class="pp-status-message" role="status" aria-live="polite" style="display: none;"></div>
						</div>
					</div>

					<div class="pp-footer-security">
						<svg class="pp-lock-icon" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
							<path d="M8 1a4 4 0 0 0-4 4v2H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1h-1V5a4 4 0 0 0-4-4zm2.5 6h-5V5a2.5 2.5 0 0 1 5 0v2z"/>
						</svg>
						<span>Your payment information is encrypted and secure.</span>
					</div>
				</div>

				<!-- Sticky order summary sidebar with order details. -->
				<aside class="pp-summary-section" aria-label="Order summary">
					<div class="pp-card pp-summary-card">
						<div class="pp-card-header">
							<h2>Order Summary</h2>
						</div>
						<div class="pp-card-body">
							<div class="pp-summary-merchant">
								<svg class="pp-store-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
									<path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1h-.5v10a1 1 0 01-1 1h-9a1 1 0 01-1-1V7H4a1 1 0 01-1-1V4zm4 4v9h6V8H7zm-2-3v1h10V5H5z" clip-rule="evenodd"/>
								</svg>
								<span class="pp-summary-merchant-name"><?php echo esc_html( $paypal_page_data['merchant_name'] ); ?></span>
							</div>

							<?php if ( ! empty( $paypal_page_data['order_description'] ) ) : ?>
							<div class="pp-summary-row">
								<span class="pp-summary-label">Description</span>
								<span class="pp-summary-value"><?php echo esc_html( $paypal_page_data['order_description'] ); ?></span>
							</div>
							<?php endif; ?>

							<div class="pp-summary-row">
								<span class="pp-summary-label">Date</span>
								<span class="pp-summary-value"><?php echo esc_html( $paypal_page_data['payment_date'] ); ?></span>
							</div>

							<?php if ( ! empty( $paypal_page_data['customer_name'] ) ) : ?>
							<div class="pp-summary-row">
								<span class="pp-summary-label">Customer</span>
								<span class="pp-summary-value"><?php echo esc_html( $paypal_page_data['customer_name'] ); ?></span>
							</div>
							<?php endif; ?>

							<?php if ( ! empty( $paypal_page_data['customer_email'] ) ) : ?>
							<div class="pp-summary-row">
								<span class="pp-summary-label">Email</span>
								<span class="pp-summary-value pp-summary-email"><?php echo esc_html( $paypal_page_data['customer_email'] ); ?></span>
							</div>
							<?php endif; ?>

							<?php if ( ! empty( $paypal_page_data['shipping_address'] ) ) : ?>
							<div class="pp-summary-row">
								<span class="pp-summary-label">Ship to</span>
								<span class="pp-summary-value pp-summary-address">
									<?php
									$sa         = $paypal_page_data['shipping_address'];
									$addr_parts = array_filter(
										[
											$sa['line_1'],
											$sa['line_2'],
											$sa['city'],
											$sa['state'],
											$sa['postal_code'],
											$sa['country_code'],
										]
									);
									echo esc_html( implode( ', ', $addr_parts ) );
									?>
								</span>
							</div>
							<?php endif; ?>

							<div class="pp-summary-divider"></div>

							<div class="pp-summary-row pp-summary-total">
								<span class="pp-summary-label">Total</span>
								<span class="pp-summary-value pp-summary-amount"><?php echo esc_html( $paypal_page_data['formatted_amount'] ); ?></span>
							</div>
						</div>
					</div>

					<a href="<?php echo esc_url( $paypal_page_data['cancel_url'] ); ?>" class="pp-cancel-link pp-cancel-desktop">Cancel and return to <?php echo esc_html( $paypal_page_data['merchant_name'] ); ?></a>
				</aside>

				<!-- Cancel link for mobile layout repositions below the summary on narrow screens. -->
				<a href="<?php echo esc_url( $paypal_page_data['cancel_url'] ); ?>" class="pp-cancel-link pp-cancel-mobile" id="pp-cancel-link">Cancel and return to <?php echo esc_html( $paypal_page_data['merchant_name'] ); ?></a>
			</div>
		</main>

		<!-- Site-wide footer with PayPal branding and legal links. -->
		<footer class="pp-footer">
			<div class="pp-footer-inner">
				<div class="pp-footer-branding">
					<span>Powered by</span>
					<img src="<?php echo esc_url( KNITPAY_URL . '/images/paypal/paypal-black.svg' ); ?>" alt="PayPal" class="pp-footer-logo">
				</div>
				<div class="pp-footer-links">
					<a href="https://www.paypal.com/webapps/mpp/ua/privacy-full" target="_blank" rel="noopener noreferrer">Privacy</a>
					<a href="https://www.paypal.com/webapps/mpp/ua/legalhub-full" target="_blank" rel="noopener noreferrer">Legal</a>
				</div>
			</div>
		</footer>
	</div>

	<script src="<?php echo esc_url( $gateway_dir_url . $js_file ); ?>"></script>
</body>
</html>
