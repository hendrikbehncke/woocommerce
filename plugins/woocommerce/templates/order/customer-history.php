<?php
/**
 * Display the Customer History metabox.
 *
 * This template is used to display the customer history metabox on the edit order screen.
 *
 * @see     Automattic\WooCommerce\Internal\Admin\Orders\MetaBoxes\CustomerHistory
 * @package WooCommerce\Templates
 * @version 8.7.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Variables used in this file.
 *
 * @var int   $orders_count         The number of paid orders placed by the current customer.
 * @var float $total_spend          The total money spent by the current customer.
 * @var float $avg_order_value      The average money spent by the current customer.
 * @var bool  $wc_analytics_enabled Whether WooCommerce Analytics is enabled.
 */
?>

<div class="customer-history order-attribution-metabox">
	<?php if ( ! $wc_analytics_enabled ) : ?>
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: URL to the WooCommerce Analytics settings page */
					__( '<a href="%s">Enable WooCommerce Analytics</a> to see customer history.', 'woocommerce' ),
					esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=features#woocommerce_analytics_enabled' ) )
				)
			);
			?>
		</p>
	<?php else : ?>
		<h4>
			<?php
			esc_html_e( 'Total orders', 'woocommerce' );
			echo wp_kses_post(
				wc_help_tip(
					__( 'Total number of non-cancelled, non-failed orders for this customer, including the current one.', 'woocommerce' )
				)
			);
			?>
		</h4>
		<span class="order-attribution-total-orders">
			<?php echo esc_html( $orders_count ); ?>
		</span>

		<h4>
			<?php
			esc_html_e( 'Total revenue', 'woocommerce' );
			echo wp_kses_post(
				wc_help_tip(
					__( "This is the Customer Lifetime Value, or the total amount you have earned from this customer's orders.", 'woocommerce' )
				)
			);
			?>
		</h4>
		<span class="order-attribution-total-spend">
			<?php echo wp_kses_post( wc_price( $total_spend ) ); ?>
		</span>

		<h4><?php esc_html_e( 'Average order value', 'woocommerce' ); ?></h4>
		<span class="order-attribution-average-order-value">
			<?php echo wp_kses_post( wc_price( $avg_order_value ) ); ?>
		</span>
	<?php endif; ?>
</div>
