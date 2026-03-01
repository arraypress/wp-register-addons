<?php
/**
 * Add-ons Page Registration Manager
 *
 * Central manager class for registering and rendering WordPress admin add-ons
 * showcase pages. Provides a configuration-driven approach to creating add-on
 * grid pages with support for:
 * - Automatic menu page registration with conditional visibility
 * - Responsive card grid with images, badges, and status detection
 * - Category filtering with client-side search
 * - Auto-detection of installed/active plugins
 * - Plugin activation from the add-ons page
 * - Pro/pricing banner integration
 * - Modern EDD-style headers (matching wp-register-tables)
 *
 * @package     ArrayPress\RegisterAddons
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterAddons;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class Manager
 *
 * Static manager class for add-ons page registration and rendering.
 *
 * @since 1.0.0
 */
class Manager {

    /* =========================================================================
     * PROPERTIES
     * ========================================================================= */

    /**
     * Registered pages storage
     *
     * @since 1.0.0
     * @var array<string, array>
     */
    private static array $pages = [];

    /**
     * Asset enqueue flag
     *
     * @since 1.0.0
     * @var bool
     */
    private static bool $assets_enqueued = false;

    /**
     * Initialization flag
     *
     * @since 1.0.0
     * @var bool
     */
    private static bool $initialized = false;

    /* =========================================================================
     * REGISTRATION
     * ========================================================================= */

    /**
     * Register an add-ons page
     *
     * @param string $id     Unique page identifier.
     * @param array  $config Page configuration array.
     *
     * @return void
     * @since 1.0.0
     */
    public static function register( string $id, array $config ): void {
        self::init();

        $defaults = [
                // Menu registration
                'page_title'          => '',
                'menu_title'          => '',
                'menu_slug'           => '',
                'parent_slug'         => '',
                'icon'                => 'dashicons-admin-plugins',
                'position'            => null,
                'capability'          => 'manage_options',

                // Conditional display
                'show_if'             => null,

                // Header
                'logo'                => '',
                'header_title'        => '',
                'header_badge'        => '',

                // Pro upsell
                'pricing_url'         => '',
                'pricing_text'        => '',
                'pricing_description' => '',

                // Categories
                'categories'          => [],

                // Add-ons
                'addons'              => [],

                // Display
                'searchable'          => true,
                'columns'             => 3,
                'body_class'          => '',

                // Labels
                'labels'              => [],
        ];

        $config = wp_parse_args( $config, $defaults );

        // Ensure menu_slug
        if ( empty( $config['menu_slug'] ) ) {
            $config['menu_slug'] = sanitize_key( $id );
        }

        // Parse labels
        $config['labels'] = wp_parse_args( $config['labels'], [
                'search'    => __( 'Search add-ons...', 'arraypress' ),
                'all'       => __( 'All', 'arraypress' ),
                'active'    => __( 'Active', 'arraypress' ),
                'installed' => __( 'Activate', 'arraypress' ),
                'available' => __( 'Get This Add-on', 'arraypress' ),
                'feature'   => __( 'Learn More', 'arraypress' ),
                'not_found' => __( 'No add-ons found matching your search.', 'arraypress' ),
        ] );

        // Auto-generate titles
        if ( empty( $config['page_title'] ) ) {
            $config['page_title'] = __( 'Add-ons', 'arraypress' );
        }
        if ( empty( $config['menu_title'] ) ) {
            $config['menu_title'] = $config['page_title'];
        }
        if ( empty( $config['header_title'] ) ) {
            $config['header_title'] = $config['page_title'];
        }

        // Normalize add-ons
        foreach ( $config['addons'] as &$addon ) {
            $addon = wp_parse_args( $addon, [
                    'title'       => '',
                    'description' => '',
                    'image'       => '',
                    'icon'        => '',
                    'plugin'      => '',
                    'url'         => '',
                    'category'    => '',
                    'badge'       => '',
                    'type'        => 'plugin',
                    'status'      => null,
            ] );
        }
        unset( $addon );

        self::$pages[ $id ] = $config;
    }

    /* =========================================================================
     * INITIALIZATION
     * ========================================================================= */

    /**
     * Initialize the manager
     *
     * @return void
     * @since 1.0.0
     */
    private static function init(): void {
        if ( self::$initialized ) {
            return;
        }
        self::$initialized = true;

        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_filter( 'admin_body_class', [ __CLASS__, 'add_body_class' ] );
        add_action( 'admin_init', [ __CLASS__, 'process_activation' ] );

        // Fix menu highlight
        add_filter( 'parent_file', [ __CLASS__, 'fix_parent_menu_highlight' ] );
        add_filter( 'submenu_file', [ __CLASS__, 'fix_submenu_highlight' ] );
    }

    /* =========================================================================
     * MENU REGISTRATION
     * ========================================================================= */

    /**
     * Register admin menu pages for all add-on pages
     *
     * @return void
     * @since 1.0.0
     */
    public static function register_menus(): void {
        foreach ( self::$pages as $id => $config ) {
            // Check conditional visibility
            if ( is_callable( $config['show_if'] ) ) {
                if ( ! call_user_func( $config['show_if'] ) ) {
                    continue;
                }
            }

            self::register_menu( $id, $config );
        }
    }

    /**
     * Register a single admin menu page
     *
     * @param string $id     Page identifier.
     * @param array  $config Page configuration.
     *
     * @return void
     * @since 1.0.0
     */
    private static function register_menu( string $id, array $config ): void {
        $render_callback = function () use ( $id ) {
            self::render_page( $id );
        };

        if ( ! empty( $config['parent_slug'] ) ) {
            add_submenu_page(
                    $config['parent_slug'],
                    $config['page_title'],
                    $config['menu_title'],
                    $config['capability'],
                    $config['menu_slug'],
                    $render_callback
            );
        } else {
            add_menu_page(
                    $config['page_title'],
                    $config['menu_title'],
                    $config['capability'],
                    $config['menu_slug'],
                    $render_callback,
                    $config['icon'],
                    $config['position']
            );
        }
    }

    /**
     * Fix parent menu highlight
     *
     * @param string $parent_file The parent file.
     *
     * @return string
     * @since 1.0.0
     */
    public static function fix_parent_menu_highlight( string $parent_file ): string {
        global $plugin_page;

        foreach ( self::$pages as $id => $config ) {
            if ( empty( $config['parent_slug'] ) ) {
                continue;
            }
            if ( $plugin_page === $config['menu_slug'] ) {
                return $config['parent_slug'];
            }
        }

        return $parent_file;
    }

    /**
     * Fix submenu highlight
     *
     * @param string|null $submenu_file The submenu file.
     *
     * @return string|null
     * @since 1.0.0
     */
    public static function fix_submenu_highlight( ?string $submenu_file ): ?string {
        global $plugin_page;

        foreach ( self::$pages as $id => $config ) {
            if ( empty( $config['parent_slug'] ) ) {
                continue;
            }
            if ( $plugin_page === $config['menu_slug'] ) {
                return $config['menu_slug'];
            }
        }

        return $submenu_file;
    }

    /* =========================================================================
     * ASSETS
     * ========================================================================= */

    /**
     * Enqueue assets
     *
     * @param string $hook Current admin page hook.
     *
     * @return void
     * @since 1.0.0
     */
    public static function enqueue_assets( string $hook ): void {
        $page = $_GET['page'] ?? '';

        if ( empty( $page ) ) {
            return;
        }

        foreach ( self::$pages as $id => $config ) {
            if ( ( $config['menu_slug'] ?? '' ) === $page ) {
                self::do_enqueue_assets( $config );
                break;
            }
        }
    }

    /**
     * Actually enqueue the assets
     *
     * @param array $config Page configuration.
     *
     * @return void
     * @since 1.0.0
     */
    private static function do_enqueue_assets( array $config ): void {
        if ( self::$assets_enqueued ) {
            return;
        }
        self::$assets_enqueued = true;

        wp_enqueue_composer_style(
                'addons-page-styles',
                __FILE__,
                'css/addons-page.css'
        );

        wp_enqueue_composer_script(
                'addons-page-scripts',
                __FILE__,
                'js/addons-page.js',
                [],
                true
        );
    }

    /* =========================================================================
     * PLUGIN ACTIVATION
     * ========================================================================= */

    /**
     * Process plugin activation requests
     *
     * Handles activation of installed-but-inactive add-on plugins
     * directly from the add-ons page.
     *
     * @return void
     * @since 1.0.0
     */
    public static function process_activation(): void {
        $action = sanitize_key( $_GET['addons_action'] ?? '' );
        $plugin = sanitize_text_field( $_GET['addons_plugin'] ?? '' );
        $page   = sanitize_key( $_GET['page'] ?? '' );

        if ( $action !== 'activate' || empty( $plugin ) || empty( $page ) ) {
            return;
        }

        // Verify nonce
        $nonce = $_GET['_wpnonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'activate_addon_' . $plugin ) ) {
            wp_die( __( 'Security check failed.', 'arraypress' ) );
        }

        // Check capability
        if ( ! current_user_can( 'activate_plugins' ) ) {
            wp_die( __( 'You do not have permission to activate plugins.', 'arraypress' ) );
        }

        // Activate the plugin
        $result = activate_plugin( $plugin );

        // Redirect back
        $redirect_url = add_query_arg( 'page', $page, admin_url( 'admin.php' ) );

        if ( is_wp_error( $result ) ) {
            $redirect_url = add_query_arg( 'addons_error', urlencode( $result->get_error_message() ), $redirect_url );
        } else {
            $redirect_url = add_query_arg( 'addons_activated', 1, $redirect_url );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /* =========================================================================
     * BODY CLASSES
     * ========================================================================= */

    /**
     * Add body classes
     *
     * @param string $classes Space-separated body classes.
     *
     * @return string
     * @since 1.0.0
     */
    public static function add_body_class( string $classes ): string {
        $page = $_GET['page'] ?? '';

        if ( empty( $page ) ) {
            return $classes;
        }

        foreach ( self::$pages as $id => $config ) {
            if ( $config['menu_slug'] === $page ) {
                $classes .= ' addons-page';
                $classes .= ' addons-page-' . sanitize_html_class( $id );

                if ( ! empty( $config['body_class'] ) ) {
                    $classes .= ' ' . sanitize_html_class( $config['body_class'] );
                }
                break;
            }
        }

        return $classes;
    }

    /* =========================================================================
     * RENDERING
     * ========================================================================= */

    /**
     * Render an add-ons page
     *
     * @param string $id Page identifier.
     *
     * @return void
     * @since 1.0.0
     */
    public static function render_page( string $id ): void {
        if ( ! isset( self::$pages[ $id ] ) ) {
            return;
        }

        $config = self::$pages[ $id ];

        // Resolve add-on statuses
        $addons = self::resolve_addon_statuses( $config['addons'] );

        // Count by category
        $category_counts = self::get_category_counts( $addons, $config['categories'] );

        // Render header (same pattern as wp-register-tables)
        self::render_header( $id, $config, $addons );

        ?>
        <div class="wrap addons-wrap">
            <?php self::render_admin_notices( $config ); ?>

            <?php
            /**
             * Fires before the add-ons grid renders
             *
             * @param string $id     Page identifier.
             * @param array  $config Page configuration.
             *
             * @since 1.0.0
             */
            do_action( 'arraypress_before_render_addons', $id, $config );
            do_action( "arraypress_before_render_addons_{$id}", $config );
            ?>

            <?php self::render_pro_banner( $config ); ?>
            <?php self::render_toolbar( $config, $category_counts ); ?>
            <?php self::render_grid( $id, $config, $addons ); ?>
            <?php self::render_no_results( $config ); ?>

            <?php
            /**
             * Fires after the add-ons grid renders
             *
             * @param string $id     Page identifier.
             * @param array  $config Page configuration.
             *
             * @since 1.0.0
             */
            do_action( 'arraypress_after_render_addons', $id, $config );
            do_action( "arraypress_after_render_addons_{$id}", $config );
            ?>
        </div>
        <?php
    }

    /**
     * Render the header
     *
     * @param string $id     Page identifier.
     * @param array  $config Page configuration.
     * @param array  $addons Resolved add-ons.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_header( string $id, array $config, array $addons ): void {
        $logo_url     = $config['logo'] ?? '';
        $header_title = $config['header_title'];
        $header_badge = $config['header_badge'] ?? '';
        $total_count  = count( $addons );

        ?>
        <div class="list-table-header">
            <div class="list-table-header__inner">
                <div class="list-table-header__branding">
                    <?php if ( $logo_url ) : ?>
                        <img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="list-table-header__logo">
                        <?php if ( ! empty( $header_title ) ) : ?>
                            <span class="list-table-header__separator">/</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ( ! empty( $header_title ) ) : ?>
                        <h1 class="list-table-header__title">
                            <?php echo esc_html( $header_title ); ?>
                            <span class="count">(<?php echo esc_html( number_format_i18n( $total_count ) ); ?>)</span>
                        </h1>
                    <?php endif; ?>
                    <?php if ( ! empty( $header_badge ) ) : ?>
                        <?php self::render_header_badge( $header_badge ); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <hr class="wp-header-end">
        <?php
    }

    /**
     * Render header badge
     *
     * @param string|array|callable $badge Badge configuration.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_header_badge( $badge ): void {
        if ( is_callable( $badge ) ) {
            echo call_user_func( $badge );

            return;
        }

        if ( is_array( $badge ) ) {
            $text  = $badge['text'] ?? '';
            $class = $badge['class'] ?? '';

            if ( empty( $text ) ) {
                return;
            }

            printf(
                    '<span class="list-table-header__badge %s">%s</span>',
                    esc_attr( $class ),
                    esc_html( $text )
            );

            return;
        }

        if ( is_string( $badge ) && ! empty( $badge ) ) {
            printf(
                    '<span class="list-table-header__badge">%s</span>',
                    esc_html( $badge )
            );
        }
    }

    /**
     * Render admin notices
     *
     * @param array $config Page configuration.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_admin_notices( array $config ): void {
        if ( isset( $_GET['addons_activated'] ) ) {
            printf(
                    '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                    esc_html__( 'Add-on activated successfully.', 'arraypress' )
            );
        }

        if ( isset( $_GET['addons_error'] ) ) {
            printf(
                    '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                    esc_html( sanitize_text_field( $_GET['addons_error'] ) )
            );
        }
    }

    /**
     * Render the pro upsell banner
     *
     * @param array $config Page configuration.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_pro_banner( array $config ): void {
        if ( empty( $config['pricing_url'] ) ) {
            return;
        }

        $text = $config['pricing_text'] ?: __( 'Get All Add-ons with Pro', 'arraypress' );
        $desc = $config['pricing_description'] ?: __( 'Unlock every add-on and get priority support with a single license.', 'arraypress' );

        ?>
        <div class="addons-pro-banner">
            <div class="addons-pro-banner__content">
                <div class="addons-pro-banner__text">
                    <h2 class="addons-pro-banner__title">
                        <span class="dashicons dashicons-star-filled"></span>
                        <?php echo esc_html( $text ); ?>
                    </h2>
                    <p class="addons-pro-banner__description"><?php echo esc_html( $desc ); ?></p>
                </div>
                <div class="addons-pro-banner__action">
                    <a href="<?php echo esc_url( $config['pricing_url'] ); ?>"
                       class="button button-primary button-hero"
                       target="_blank"
                       rel="noopener noreferrer">
                        <?php esc_html_e( 'View Pricing', 'arraypress' ); ?>
                        <span class="dashicons dashicons-external"></span>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the toolbar with search and category filters
     *
     * @param array $config          Page configuration.
     * @param array $category_counts Category => count pairs.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_toolbar( array $config, array $category_counts ): void {
        $has_categories = ! empty( $config['categories'] );
        $has_search     = $config['searchable'];

        if ( ! $has_categories && ! $has_search ) {
            return;
        }

        $total_count = array_sum( $category_counts );

        ?>
        <div class="addons-toolbar">
            <?php if ( $has_categories ) : ?>
                <div class="addons-toolbar__categories" role="tablist">
                    <button type="button"
                            class="addons-category-btn active"
                            data-category="all"
                            role="tab"
                            aria-selected="true">
                        <?php echo esc_html( $config['labels']['all'] ); ?>
                        <span class="count">(<?php echo esc_html( $total_count ); ?>)</span>
                    </button>
                    <?php foreach ( $config['categories'] as $key => $label ) : ?>
                        <?php if ( ( $category_counts[ $key ] ?? 0 ) > 0 ) : ?>
                            <button type="button"
                                    class="addons-category-btn"
                                    data-category="<?php echo esc_attr( $key ); ?>"
                                    role="tab"
                                    aria-selected="false">
                                <?php echo esc_html( $label ); ?>
                                <span class="count">(<?php echo esc_html( $category_counts[ $key ] ); ?>)</span>
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ( $has_search ) : ?>
                <div class="addons-toolbar__search">
                    <label for="addons-search" class="screen-reader-text">
                        <?php echo esc_html( $config['labels']['search'] ); ?>
                    </label>
                    <input type="search"
                           id="addons-search"
                           class="addons-search-input"
                           placeholder="<?php echo esc_attr( $config['labels']['search'] ); ?>">
                    <span class="dashicons dashicons-search addons-search-icon" aria-hidden="true"></span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the add-ons grid
     *
     * @param string $id     Page identifier.
     * @param array  $config Page configuration.
     * @param array  $addons Resolved add-ons.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_grid( string $id, array $config, array $addons ): void {
        $columns = $config['columns'];

        ?>
        <div class="addons-grid addons-grid--cols-<?php echo esc_attr( $columns ); ?>"
             id="addons-grid">
            <?php foreach ( $addons as $key => $addon ) : ?>
                <?php self::render_card( $key, $addon, $config ); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render a single add-on card
     *
     * @param string $key    Add-on identifier.
     * @param array  $addon  Add-on configuration with resolved status.
     * @param array  $config Page configuration.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_card( string $key, array $addon, array $config ): void {
        $status   = $addon['_status'];
        $category = $addon['category'] ?: 'uncategorized';
        $badge    = $addon['badge'];

        $card_classes = [
                'addon-card',
                'addon-card--' . $status,
        ];

        // Build searchable text: title + description + category label + badge
        $search_text = strtolower( $addon['title'] . ' ' . $addon['description'] );

        // Include category label in searchable text
        if ( ! empty( $addon['category'] ) && isset( $config['categories'][ $addon['category'] ] ) ) {
            $search_text .= ' ' . strtolower( $config['categories'][ $addon['category'] ] );
        }

        // Include badge label in searchable text
        if ( ! empty( $badge ) ) {
            $search_text .= ' ' . strtolower( self::get_badge_label( $badge ) );
        }

        ?>
        <div class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>"
             data-category="<?php echo esc_attr( $category ); ?>"
             data-status="<?php echo esc_attr( $status ); ?>"
             data-search="<?php echo esc_attr( $search_text ); ?>">

            <?php if ( ! empty( $badge ) ) : ?>
                <span class="addon-card__badge addon-card__badge--<?php echo esc_attr( sanitize_html_class( $badge ) ); ?>">
					<?php echo esc_html( self::get_badge_label( $badge ) ); ?>
				</span>
            <?php endif; ?>

            <div class="addon-card__image">
                <?php if ( ! empty( $addon['image'] ) ) : ?>
                    <img src="<?php echo esc_url( $addon['image'] ); ?>"
                         alt="<?php echo esc_attr( $addon['title'] ); ?>"
                         loading="lazy">
                <?php elseif ( ! empty( $addon['icon'] ) ) : ?>
                    <span class="dashicons <?php echo esc_attr( $addon['icon'] ); ?>"></span>
                <?php else : ?>
                    <span class="dashicons dashicons-admin-plugins"></span>
                <?php endif; ?>
            </div>

            <div class="addon-card__content">
                <h3 class="addon-card__title"><?php echo esc_html( $addon['title'] ); ?></h3>

                <?php if ( ! empty( $addon['description'] ) ) : ?>
                    <p class="addon-card__description"><?php echo esc_html( $addon['description'] ); ?></p>
                <?php endif; ?>
            </div>

            <div class="addon-card__footer">
                <?php self::render_card_action( $key, $addon, $config ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the card action button
     *
     * @param string $key    Add-on identifier.
     * @param array  $addon  Add-on configuration.
     * @param array  $config Page configuration.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_card_action( string $key, array $addon, array $config ): void {
        $status = $addon['_status'];
        $labels = $config['labels'];

        switch ( $status ) {
            case 'active':
                printf(
                        '<span class="addon-card__status addon-card__status--active">
						<span class="dashicons dashicons-yes-alt"></span> %s
					</span>',
                        esc_html( $labels['active'] )
                );
                break;

            case 'installed':
                if ( current_user_can( 'activate_plugins' ) && ! empty( $addon['plugin'] ) ) {
                    $activate_url = wp_nonce_url(
                            add_query_arg( [
                                    'page'          => $config['menu_slug'],
                                    'addons_action' => 'activate',
                                    'addons_plugin' => $addon['plugin'],
                            ], admin_url( 'admin.php' ) ),
                            'activate_addon_' . $addon['plugin']
                    );

                    printf(
                            '<a href="%s" class="button addon-card__btn addon-card__btn--activate">%s</a>',
                            esc_url( $activate_url ),
                            esc_html( $labels['installed'] )
                    );
                } else {
                    printf(
                            '<span class="addon-card__status addon-card__status--installed">%s</span>',
                            esc_html__( 'Installed', 'arraypress' )
                    );
                }
                break;

            case 'available':
            default:
                $label = ( $addon['type'] === 'feature' ) ? $labels['feature'] : $labels['available'];
                $url   = $addon['url'] ?: $config['pricing_url'];

                if ( ! empty( $url ) ) {
                    printf(
                            '<a href="%s" class="button button-primary addon-card__btn addon-card__btn--get" target="_blank" rel="noopener noreferrer">%s</a>',
                            esc_url( $url ),
                            esc_html( $label )
                    );
                }
                break;
        }
    }

    /**
     * Render the no results message (hidden by default, shown by JS)
     *
     * @param array $config Page configuration.
     *
     * @return void
     * @since 1.0.0
     */
    private static function render_no_results( array $config ): void {
        ?>
        <div class="addons-no-results" id="addons-no-results" style="display: none;">
            <span class="dashicons dashicons-admin-plugins"></span>
            <p><?php echo esc_html( $config['labels']['not_found'] ); ?></p>
        </div>
        <?php
    }

    /* =========================================================================
     * STATUS RESOLUTION
     * ========================================================================= */

    /**
     * Resolve add-on statuses
     *
     * Determines the status of each add-on by checking:
     * 1. Explicit 'status' override in config
     * 2. Plugin basename via is_plugin_active() and file existence
     * 3. Defaults to 'available'
     *
     * @param array $addons Add-on configurations.
     *
     * @return array Add-ons with '_status' key added.
     * @since 1.0.0
     */
    private static function resolve_addon_statuses( array $addons ): array {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach ( $addons as $key => &$addon ) {
            // Explicit status override
            if ( ! empty( $addon['status'] ) ) {
                $addon['_status'] = $addon['status'];
                continue;
            }

            // Feature type is always available
            if ( $addon['type'] === 'feature' ) {
                $addon['_status'] = 'available';
                continue;
            }

            // Check plugin basename
            if ( ! empty( $addon['plugin'] ) ) {
                if ( is_plugin_active( $addon['plugin'] ) ) {
                    $addon['_status'] = 'active';
                } elseif ( file_exists( WP_PLUGIN_DIR . '/' . $addon['plugin'] ) ) {
                    $addon['_status'] = 'installed';
                } else {
                    $addon['_status'] = 'available';
                }
                continue;
            }

            $addon['_status'] = 'available';
        }
        unset( $addon );

        return $addons;
    }

    /* =========================================================================
     * UTILITY METHODS
     * ========================================================================= */

    /**
     * Get category counts from resolved add-ons
     *
     * @param array $addons     Resolved add-ons.
     * @param array $categories Category definitions.
     *
     * @return array Category key => count pairs.
     * @since 1.0.0
     */
    private static function get_category_counts( array $addons, array $categories ): array {
        $counts = array_fill_keys( array_keys( $categories ), 0 );

        foreach ( $addons as $addon ) {
            $cat = $addon['category'] ?? '';
            if ( isset( $counts[ $cat ] ) ) {
                $counts[ $cat ] ++;
            }
        }

        return $counts;
    }

    /**
     * Get human-readable badge label
     *
     * @param string $badge Badge key.
     *
     * @return string Badge display text.
     * @since 1.0.0
     */
    private static function get_badge_label( string $badge ): string {
        $labels = [
                'popular'     => __( 'Popular', 'arraypress' ),
                'new'         => __( 'New', 'arraypress' ),
                'recommended' => __( 'Recommended', 'arraypress' ),
                'coming_soon' => __( 'Coming Soon', 'arraypress' ),
        ];

        return $labels[ $badge ] ?? ucfirst( str_replace( [ '_', '-' ], ' ', $badge ) );
    }

    /* =========================================================================
     * PAGE MANAGEMENT
     * ========================================================================= */

    /**
     * Get a registered page configuration
     *
     * @param string $id Page identifier.
     *
     * @return array|null
     * @since 1.0.0
     */
    public static function get_page( string $id ): ?array {
        return self::$pages[ $id ] ?? null;
    }

    /**
     * Check if a page is registered
     *
     * @param string $id Page identifier.
     *
     * @return bool
     * @since 1.0.0
     */
    public static function has_page( string $id ): bool {
        return isset( self::$pages[ $id ] );
    }

    /**
     * Unregister a page
     *
     * @param string $id Page identifier.
     *
     * @return bool True if removed.
     * @since 1.0.0
     */
    public static function unregister( string $id ): bool {
        if ( isset( self::$pages[ $id ] ) ) {
            unset( self::$pages[ $id ] );

            return true;
        }

        return false;
    }

    /**
     * Get all registered pages
     *
     * @return array
     * @since 1.0.0
     */
    public static function get_all_pages(): array {
        return self::$pages;
    }

}
