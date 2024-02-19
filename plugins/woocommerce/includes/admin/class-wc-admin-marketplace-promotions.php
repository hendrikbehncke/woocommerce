<?php
/**
 * Addons Page
 *
 * @package  WooCommerce\Admin
 * @version  2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Admin_Marketplace_Promotions class.
 */
class WC_Admin_Marketplace_Promotions {

	const TRANSIENT_NAME = 'wc_addons_marketplace_promotions';
	public static string $locale;

	/**
	 * On selected WooCommerce admin pages, fetch promotions from transient or API.
	 * On pages in the main WooCommerce menu, show promotions if there are any.
	 *
	 * @return void
	 */
	public static function init_marketplace_promotions() {
		if ( self::is_targeted_page_to_fetch_promotions() ) {
			self::fetch_marketplace_promotions();
		}

		if ( self::is_targeted_page_to_show_bubble_promotions() ) {
			self::$locale = ( self::$locale ?? get_user_locale() ) ?? 'en_US';
			self::show_bubble_promotions();
		}
	}

	/**
	 * Check if the request is for one of the pages we will run fetch_marketplace_promotions
	 * on: the WooCommerce Home and Extensions pages.
	 *
	 * @return bool
	 */
	public static function is_targeted_page_to_fetch_promotions(): bool {
		if (
			defined( 'DOING_AJAX' ) && DOING_AJAX
			|| ! is_admin()
			|| ! isset( $_GET['page'] )
		) {
			return false;
		}

		$targeted_page = 'wc-admin';
		$targeted_path = '/extensions';

		if ( $_GET['page'] === $targeted_page ) {
			// WooCommerce home has page param but no path param
			return ! isset( $_GET['path'] ) || $_GET['path'] === $targeted_path;
		}

		return false;
	}

	/**
	 * Check if the request is for one of the pages we will show menu item bubbles
	 * on: the pages in the main WooCommerce menu, excluding Analytics and Marketing.
	 *
	 * We need to cover all the pages in the WooCommerce menu, because the Extensions item
	 * is visible on any of these pages, and we want to show the bubble there.
	 *
	 * @return bool
	 */
	public static function is_targeted_page_to_show_bubble_promotions(): bool {
		if (
			defined( 'DOING_AJAX' ) && DOING_AJAX
			|| ! is_admin()
			|| ! isset( $_GET['page'] )
		) {
			return false;
		}

		// We also target wc-admin, but that has more variations
		$targeted_pages = array(
			'wc-orders',
			'wc-reports',
			'wc-settings',
			'wc-status',
		);

		if ( in_array( $_GET['page'], $targeted_pages ) ) {
			return true;
		}

		$targeted_wc_admin_paths = array(
			'/customers',
			'/extensions',
		);

		if ( 'wc-admin' === $_GET['page'] ) {
			// WooCommerce home has page param but no path param
			return ! isset( $_GET['path'] ) || in_array( $_GET['path'], $targeted_wc_admin_paths );
		}

		return false;
	}

	/**
	 * Get promotions to show in the Woo in-app marketplace.
	 * Only run on selected pages in the main WooCommerce menu in wp-admin.
	 * Loads promotions in transient with one day life.
	 *
	 * @return void
	 */
	private static function fetch_marketplace_promotions() {
		$url        = 'https://woocommerce.com/wp-json/wccom-extensions/3.0/promotions';
		$promotions = get_transient( self::TRANSIENT_NAME );

		if ( false === $promotions ) {
			$fetch_options  = array(
				'auth'    => true,
				'country' => true,
			);
			$raw_promotions = WC_Admin_Addons::fetch( $url, $fetch_options );

			if ( is_wp_error( $raw_promotions ) ) {
				do_action( 'woocommerce_page_wc-addons_connection_error', $raw_promotions->get_error_message() );
			}

			$response_code = (int) wp_remote_retrieve_response_code( $raw_promotions );
			if ( 200 !== $response_code ) {
				do_action( 'woocommerce_page_wc-addons_connection_error', $response_code );
			}

			$promotions = json_decode( wp_remote_retrieve_body( $raw_promotions ), true );
			if ( empty( $promotions ) || ! is_array( $promotions ) ) {
				do_action( 'woocommerce_page_wc-addons_connection_error', 'Empty or malformed response' );
			}

			if ( $promotions ) {
				// Filter out any expired promotions
				$promotions = WC_Admin_Marketplace_Promotions::get_active_promotions( $promotions );
				set_transient( self::TRANSIENT_NAME, $promotions, DAY_IN_SECONDS );
			}
		}
	}

	/**
	 * If there's an active promotion of the format `menu_bubble`,
	 * add a filter to show a bubble on the Extensions item in the
	 * WooCommerce menu.
	 *
	 * @return void
	 * @throws Exception
	 */
	private static function show_bubble_promotions() {
		if ( apply_filters( 'woocommerce_marketplace_suppress_menu_badges', false ) ) {
			return;
		}

		$promotions = get_transient( self::TRANSIENT_NAME );
		if ( ! $promotions ) {
			return;
		}

		$bubble_promotions        = self::get_promotions_of_format( $promotions, 'menu_bubble' );
		$now_date_time            = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

		// Let's make absolutely sure the promotion is still active
		foreach ( $bubble_promotions as $promotion ) {
			if ( ! isset( $promotion['date_to_gmt'] ) ) {
				continue;
			}

			try {
				$date_to_gmt = new DateTime( $promotion['date_to_gmt'], new DateTimeZone( 'UTC' ) );
			} catch ( \Exception $ex ) {
				continue;
			}

			if ( $now_date_time < $date_to_gmt ) {
				add_filter( 'woocommerce_marketplace_menu_items',
					function ( $marketplace_pages ) use ( $promotion ) {
						return self::filter_marketplace_menu_items( $marketplace_pages, $promotion );
					} );

				break;
			}
		}
	}

	/**
	 * From the array of promotions, select those of a given format.
	 *
	 * @param ? array  $promotions
	 * @param ? string $format
	 *
	 * @return array
	 */
	public static function get_promotions_of_format( $promotions = array(), $format = '' ): array {
		if ( empty( $promotions ) || empty( $format ) ) {
			return array();
		}

		$promotions_of_format = array();

		// Find promotions of a given format
		foreach ( $promotions as $promotion ) {
			if ( ! isset( $promotion['format'] ) ) {
				continue;
			}
			if ( $format === $promotion['format'] ) {
				$promotions_of_format[] = $promotion;
			}
		}

		return $promotions_of_format;
	}

	/**
	 * Find promotions that are still active – they have a date range that
	 * includes the current date.
	 *
	 * @param ?array $promotions
	 *
	 * @return array
	 */
	public static function get_active_promotions( $promotions = array() ) {
		$now_date_time     = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$active_promotions = array();

		foreach ( $promotions as $promotion ) {
			if ( ! isset( $promotion['date_from_gmt'] ) || ! isset( $promotion['date_to_gmt'] ) ) {
				continue;
			}

			try {
				$date_from_gmt = new DateTime( $promotion['date_from_gmt'], new DateTimeZone( 'UTC' ) );
				$date_to_gmt   = new DateTime( $promotion['date_to_gmt'], new DateTimeZone( 'UTC' ) );
			} catch ( \Exception $ex ) {
				continue;
			}

			if ( $now_date_time >= $date_from_gmt && $now_date_time <= $date_to_gmt ) {
				$active_promotions[] = $promotion;
			}
		}

		// Sort promotions so the ones starting more recently are at the top
		usort( $active_promotions, function ( $a, $b ) {
			return $b['date_from_gmt'] <=> $a['date_from_gmt'];
		} );

		return $active_promotions;
	}

	/**
	 * Callback for the `woocommerce_marketplace_menu_items` filter
	 * in `Automattic\WooCommerce\Internal\Admin\Marketplace::get_marketplace_pages`.
	 * At the moment, the Extensions page is the only page in `$menu_items`.
	 * Adds a bubble to the menu item.
	 *
	 * @param array  $menu_items
	 * @param ?array $promotion
	 *
	 * @return array
	 */
	public static function filter_marketplace_menu_items( $menu_items, $promotion = array() ) {
		if ( ! isset( $promotion['menu_item_id'] ) || ! isset( $promotion['content'] ) ) {
			return $menu_items;
		}
		foreach ( $menu_items as $index => $menu_item ) {
			if (
				'woocommerce' === $menu_item['parent']
				&& $promotion['menu_item_id'] === $menu_item['id']
			) {
				$bubble_text = $promotion['content'][ self::$locale ] ?? ( $promotion['content']['en_US'] ?? __( 'Sale', 'woocommerce' ) );
				$menu_items[ $index ]['title'] = $menu_item['title'] . self::append_bubble( $bubble_text );

				break;
			}
		}

		return $menu_items;
	}

	/**
	 * Return the markup for a menu item bubble with a given text.
	 *
	 * @param string $bubble_text
	 *
	 * @return string
	 */
	private static function append_bubble( $bubble_text ) {
		return ' <span class="awaiting-mod update-plugins remaining-tasks-badge woocommerce-task-list-remaining-tasks-badge">' . esc_html( $bubble_text ) . '</span>';
	}

}
