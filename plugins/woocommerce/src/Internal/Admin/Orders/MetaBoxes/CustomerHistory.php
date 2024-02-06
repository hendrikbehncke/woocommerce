<?php

namespace Automattic\WooCommerce\Internal\Admin\Orders\MetaBoxes;

use Automattic\WooCommerce\Internal\Traits\OrderAttributionMeta;
use WC_Order;

/**
 * Class CustomerHistory
 *
 * @since 8.5.0
 */
class CustomerHistory {

	use OrderAttributionMeta;

	/**
	 * Output the customer history template for the order.
	 *
	 * @param WC_Order $order The order object.
	 *
	 * @return void
	 */
	public function output( WC_Order $order ): void {
		// No history displayed when adding a new order.
		if ( 'auto-draft' === $order->get_status() ) {
			return;
		}

		$template_params = array(
			'wc_analytics_enabled' => $this->is_wc_analytics_enabled(),
			'orders_count'         => 0,
			'total_spend'          => 0,
			'avg_order_value'      => 0,
		);

		// Try to populate the customer history data if WooCommerce Analytics is enabled.
		if ( $this->is_wc_analytics_enabled() ) {
			$customer_history = $this->get_customer_history( $order->get_report_customer_id() );

			if ( $customer_history ) {
				$template_params = array_merge( $template_params, $customer_history );
			}
		}

		wc_get_template( 'order/customer-history.php', $template_params );
	}

	/**
	 * Whether WooCommerce Analytics is enabled.
	 *
	 * @return bool
	 *
	 * @since 8.7.0
	 */
	private function is_wc_analytics_enabled() {
		return 'yes' === get_option( 'woocommerce_analytics_enabled' );
	}

}
