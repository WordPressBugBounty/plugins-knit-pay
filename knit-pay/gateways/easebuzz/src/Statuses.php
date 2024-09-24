<?php

namespace KnitPay\Gateways\Easebuzz;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Easebuzz Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   1.2.0
 */
class Statuses {
	/**
	 * INITIATED
	 *
	 * @var string
	 */
	const INITIATED = 'initiated';

	/**
	 * PENDING
	 *
	 * @var string
	 */
	const PENDING = 'pending';

	/**
	 * SUCCESSFUL
	 *
	 * @var string
	 */
	const SUCCESSFUL = 'success';

	/**
	 * FAILED.
	 *
	 * @var string
	 */
	const FAILED = 'failure';

	/**
	 * CANCELLED.
	 *
	 * @var string
	 */
	const CANCELLED = 'userCancelled';

	/**
	 * DROPPED.
	 *
	 * @var string
	 */
	const DROPPED = 'dropped';

	/**
	 * BOUNCED.
	 *
	 * @var string
	 */
	const BOUNCED = 'bounced';

	/**
	 * Transform an Easebuzz status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::SUCCESSFUL:
				return Core_Statuses::SUCCESS;

			case self::FAILED:
				return Core_Statuses::FAILURE;

			case self::CANCELLED:
				return Core_Statuses::CANCELLED;

			case self::BOUNCED:
			case self::DROPPED:
				return Core_Statuses::EXPIRED;

			case self::INITIATED:
			case self::PENDING:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
