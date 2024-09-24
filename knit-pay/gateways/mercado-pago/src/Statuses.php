<?php

namespace KnitPay\Gateways\MercadoPago;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Mercado Pago Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.88.0.0
 * @since   8.88.0.0
 */
class Statuses {
	/**
	 * PENDING
	 *
	 * @var string
	 *
	 * @link https://www.mercadopago.com.br/developers/en/reference/payments/_payments_id/get
	 */
	const PENDING      = 'pending';
	const APPROVED     = 'approved';
	const AUTHORIZED   = 'authorized';
	const IN_PROCESS   = 'in_process';
	const IN_MEDIATION = 'in_mediation';
	const REJECTED     = 'rejected';
	const CANCELLED    = 'cancelled';
	const REFUNDED     = 'refunded';
	const CHARGED_BACK = 'charged_back';

	/**
	 * Transform an Mercado Pago status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::APPROVED:
				return Core_Statuses::SUCCESS;

			case self::REJECTED:
				return Core_Statuses::FAILURE;

			case self::CANCELLED:
				return Core_Statuses::CANCELLED;

			case self::PENDING:
			case self::AUTHORIZED:
			case self::IN_PROCESS:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
