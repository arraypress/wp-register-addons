<?php
/**
 * Add-ons Page Helper Functions
 *
 * Global helper functions for registering add-ons showcase pages.
 * These functions provide a convenient procedural API for the Manager class.
 *
 * @package     ArrayPress\RegisterAddons
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\RegisterAddons\Manager;

if ( ! function_exists( 'register_addons_page' ) ) {
	/**
	 * Register an add-ons showcase page
	 *
	 * Registers a new admin page that displays available add-ons, extensions,
	 * and pro features in a responsive card grid. The admin menu page is
	 * automatically created — no manual add_menu_page() or add_submenu_page()
	 * calls are needed.
	 *
	 * The page can be conditionally hidden (e.g., when a pro license is active)
	 * using the 'show_if' callback.
	 *
	 * @param string $id     Unique page identifier. Used in hooks and internally.
	 * @param array  $config Page configuration array. See Manager class for options.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @example
	 * register_addons_page( 'my-plugin-addons', [
	 *     'page_title'  => 'Add-ons',
	 *     'menu_title'  => 'Add-ons',
	 *     'menu_slug'   => 'my-plugin-addons',
	 *     'parent_slug' => 'my-plugin',
	 *     'logo'        => plugin_dir_url( __FILE__ ) . 'logo.png',
	 *     'pricing_url' => 'https://yoursite.com/pricing/',
	 *     'addons'      => [
	 *         'stripe' => [
	 *             'title'       => 'Stripe Connect',
	 *             'description' => 'Accept payments with Stripe Connect.',
	 *             'image'       => plugin_dir_url( __FILE__ ) . 'addons/stripe.png',
	 *             'plugin'      => 'my-plugin-stripe/my-plugin-stripe.php',
	 *             'url'         => 'https://yoursite.com/addons/stripe/',
	 *         ],
	 *     ],
	 * ] );
	 */
	function register_addons_page( string $id, array $config ): void {
		Manager::register( $id, $config );
	}
}
