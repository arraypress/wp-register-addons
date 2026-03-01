# WordPress Add-ons Page

A declarative system for registering WordPress admin add-ons showcase pages. Display available extensions, pro features,
and premium add-ons in a responsive card grid with automatic plugin status detection.

## Installation

```bash
composer require arraypress/wp-register-addons
```

## Quick Start

```php
register_addons_page( 'my-plugin-addons', [
    'page_title'  => __( 'Add-ons', 'myplugin' ),
    'menu_title'  => __( 'Add-ons', 'myplugin' ),
    'menu_slug'   => 'my-plugin-addons',
    'parent_slug' => 'my-plugin',
    'capability'  => 'manage_options',
    'logo'        => plugin_dir_url( __FILE__ ) . 'assets/logo.png',
    'pricing_url' => 'https://yoursite.com/pricing/',

    'categories' => [
        'payments'  => __( 'Payments', 'myplugin' ),
        'marketing' => __( 'Marketing', 'myplugin' ),
    ],

    'addons' => [
        'stripe' => [
            'title'       => __( 'Stripe Connect', 'myplugin' ),
            'description' => __( 'Accept payments with Stripe Connect.', 'myplugin' ),
            'image'       => plugin_dir_url( __FILE__ ) . 'assets/addons/stripe.png',
            'plugin'      => 'myplugin-stripe/myplugin-stripe.php',
            'url'         => 'https://yoursite.com/addons/stripe/',
            'category'    => 'payments',
            'badge'       => 'popular',
        ],
    ],
] );
```

That's it — the menu page, card grid, category filters, search, and plugin status detection are handled automatically.

## Configuration Reference

```php
register_addons_page( 'page_id', [
    // Menu Registration
    'page_title'  => 'Add-ons',              // Page title tag text
    'menu_title'  => 'Add-ons',              // Menu item text
    'menu_slug'   => 'my-addons',            // Admin page slug
    'parent_slug' => '',                     // Parent menu slug (empty = top-level)
    'capability'  => 'manage_options',       // Capability required to view
    'icon'        => 'dashicons-admin-plugins', // Dashicon (top-level only)
    'position'    => null,                   // Menu position (top-level only)

    // Conditional Display
    'show_if' => callable|null,              // Return false to hide the menu entirely

    // Header
    'logo'         => '',                    // URL to logo image
    'header_title' => '',                    // Override header title
    'header_badge' => '',                    // Badge next to title

    // Pro Upsell Banner
    'pricing_url'         => '',             // URL to pricing page (empty = no banner)
    'pricing_text'        => '',             // Banner headline
    'pricing_description' => '',             // Banner subtext

    // Categories
    'categories' => [],                      // key => label pairs for filter tabs

    // Add-ons
    'addons' => [],                          // Add-on definitions (see below)

    // Display
    'searchable' => true,                    // Show search box
    'columns'    => 3,                       // Grid columns (2, 3, or 4)
    'body_class' => '',                      // Additional CSS body class

    // Labels
    'labels' => [
        'search'    => 'Search add-ons...',
        'all'       => 'All',
        'active'    => 'Active',
        'installed' => 'Activate',
        'available' => 'Get This Add-on',
        'feature'   => 'Learn More',
        'not_found' => 'No add-ons found matching your search.',
    ],
] );
```

## Conditional Visibility

Hide the add-ons page when a pro license is active:

```php
register_addons_page( 'my-plugin-addons', [
    'show_if' => function() {
        return ! my_plugin_has_pro_license();
    },
    // ...
] );
```

When the callback returns `false`, the menu page is never registered.

## Add-on Definitions

Each add-on supports the following options:

```php
'addons' => [
    'stripe-connect' => [
        'title'       => 'Stripe Connect',              // Display name (required)
        'description' => 'Accept marketplace payments.', // Card description
        'image'       => 'https://.../stripe.png',       // Card image URL
        'icon'        => 'dashicons-cart',                // Fallback dashicon (if no image)
        'plugin'      => 'my-stripe/my-stripe.php',       // Plugin basename for auto-detection
        'url'         => 'https://yoursite.com/...',       // Purchase/info URL
        'category'    => 'payments',                      // Category key for filtering
        'badge'       => 'popular',                       // Badge label (see Badges)
        'type'        => 'plugin',                        // 'plugin' or 'feature'
        'status'      => null,                            // Override: 'active', 'installed', 'available'
    ],
],
```

### Auto-Detection

When a `plugin` basename is provided, the library automatically detects the add-on's status:

| Status        | Condition                         | Card Action       |
|---------------|-----------------------------------|-------------------|
| **Active**    | Plugin is installed and activated | Green checkmark   |
| **Installed** | Plugin exists but is not active   | "Activate" button |
| **Available** | Plugin is not installed           | "Get This Add-on" |

The "Activate" button works directly from the add-ons page with nonce verification.

### Feature Type

For pro features gated within the core plugin (not separate plugins):

```php
'advanced-reporting' => [
    'title'       => __( 'Advanced Reporting', 'myplugin' ),
    'description' => __( 'Unlock detailed analytics.', 'myplugin' ),
    'type'        => 'feature',
    'url'         => 'https://yoursite.com/pricing/',
    'category'    => 'reporting',
],
```

Feature-type add-ons always show as "available" with a "Learn More" link.

### Status Override

Force a specific status regardless of plugin detection:

```php
'coming-soon-addon' => [
    'title'  => __( 'Coming Soon', 'myplugin' ),
    'status' => 'available',
    'badge'  => 'coming_soon',
],
```

## Badges

Badges appear in the top-right corner of add-on cards:

| Badge Key     | Display Text | Color |
|---------------|--------------|-------|
| `popular`     | Popular      | Amber |
| `new`         | New          | Green |
| `recommended` | Recommended  | Blue  |
| `coming_soon` | Coming Soon  | Gray  |
| Custom string | Auto-labeled | Gray  |

## Categories

Categories render as pill-shaped filter buttons above the grid:

```php
'categories' => [
    'payments'  => __( 'Payments', 'myplugin' ),
    'marketing' => __( 'Marketing', 'myplugin' ),
    'reporting' => __( 'Reporting', 'myplugin' ),
],
```

An "All" button is automatically included. Categories with zero add-ons are hidden. Filtering is instant (client-side)
with no page reload.

## Search

When `searchable` is enabled (default), a search box appears in the toolbar. Search is client-side with debounce and
matches against add-on titles, descriptions, category labels, and badge text. Multi-word queries require all words to
match. Press Escape to clear.

Both category and search state are preserved in the URL (`?category=payments&s=stripe`) so filtered views are
shareable and survive page refreshes.

## Pro Banner

The pro upsell banner displays at the top when `pricing_url` is configured:

```php
register_addons_page( 'my-plugin-addons', [
    'pricing_url'         => 'https://yoursite.com/pricing/',
    'pricing_text'        => __( 'Unlock Everything with Pro', 'myplugin' ),
    'pricing_description' => __( 'Get every add-on and priority support.', 'myplugin' ),
] );
```

Omit `pricing_url` to hide the banner entirely.

## Grid Columns

```php
'columns' => 3,  // 2, 3, or 4
```

The grid is responsive — columns reduce automatically on smaller screens.

## Header

Uses the same EDD-style header as
[wp-register-tables](https://github.com/arraypress/wp-register-tables):

```php
register_addons_page( 'my-plugin-addons', [
    'logo'         => plugin_dir_url( __FILE__ ) . 'assets/logo.png',
    'header_title' => __( 'Extensions', 'myplugin' ),
    'header_badge' => [
        'text'  => 'Beta',
        'class' => 'badge-warning',
    ],
] );
```

## Body Classes

- `addons-page` — added to all add-ons pages
- `addons-page-{id}` — page-specific class
- Custom class from the `body_class` config option

## Hooks

### Actions

```php
// Before/after grid renders
add_action( 'arraypress_before_render_addons', fn( $id, $config ) => null, 10, 2 );
add_action( 'arraypress_before_render_addons_{page_id}', fn( $config ) => null, 10, 1 );
add_action( 'arraypress_after_render_addons', fn( $id, $config ) => null, 10, 2 );
add_action( 'arraypress_after_render_addons_{page_id}', fn( $config ) => null, 10, 1 );
```

## Complete Example

```php
register_addons_page( 'sugarcart-addons', [
    // Menu
    'page_title'  => __( 'Add-ons', 'sugarcart' ),
    'menu_title'  => __( 'Add-ons', 'sugarcart' ),
    'parent_slug' => 'sugarcart',
    'capability'  => 'manage_options',

    // Conditional visibility
    'show_if' => function() {
        return ! sugarcart_has_pro_license();
    },

    // Header
    'logo'         => plugin_dir_url( __FILE__ ) . 'assets/logo.png',
    'header_title' => __( 'Add-ons', 'sugarcart' ),

    // Pro banner
    'pricing_url'         => 'https://sugarcart.com/pricing/',
    'pricing_text'        => __( 'Get All Add-ons with SugarCart Pro', 'sugarcart' ),
    'pricing_description' => __( 'Every add-on, priority support, and all future extensions.', 'sugarcart' ),

    // Categories
    'categories' => [
        'payments' => __( 'Payments', 'sugarcart' ),
        'emails'   => __( 'Email', 'sugarcart' ),
        'tools'    => __( 'Tools', 'sugarcart' ),
    ],

    // Add-ons
    'addons' => [
        'stripe-connect' => [
            'title'       => __( 'Stripe Connect', 'sugarcart' ),
            'description' => __( 'Accept marketplace payments and split revenue.', 'sugarcart' ),
            'image'       => plugin_dir_url( __FILE__ ) . 'assets/addons/stripe-connect.png',
            'plugin'      => 'sugarcart-stripe-connect/sugarcart-stripe-connect.php',
            'url'         => 'https://sugarcart.com/addons/stripe-connect/',
            'category'    => 'payments',
            'badge'       => 'popular',
        ],
        'mailchimp' => [
            'title'       => __( 'Mailchimp', 'sugarcart' ),
            'description' => __( 'Sync customers with your Mailchimp audience.', 'sugarcart' ),
            'image'       => plugin_dir_url( __FILE__ ) . 'assets/addons/mailchimp.png',
            'plugin'      => 'sugarcart-mailchimp/sugarcart-mailchimp.php',
            'url'         => 'https://sugarcart.com/addons/mailchimp/',
            'category'    => 'emails',
            'badge'       => 'new',
        ],
        'pdf-invoices' => [
            'title'       => __( 'PDF Invoices', 'sugarcart' ),
            'description' => __( 'Generate and attach PDF invoices to order emails.', 'sugarcart' ),
            'image'       => plugin_dir_url( __FILE__ ) . 'assets/addons/pdf-invoices.png',
            'plugin'      => 'sugarcart-pdf-invoices/sugarcart-pdf-invoices.php',
            'url'         => 'https://sugarcart.com/addons/pdf-invoices/',
            'category'    => 'tools',
        ],
        'advanced-analytics' => [
            'title'       => __( 'Advanced Analytics', 'sugarcart' ),
            'description' => __( 'Detailed revenue charts, customer insights, and exports.', 'sugarcart' ),
            'type'        => 'feature',
            'url'         => 'https://sugarcart.com/pricing/',
            'category'    => 'tools',
            'image'       => plugin_dir_url( __FILE__ ) . 'assets/addons/analytics.png',
            'badge'       => 'recommended',
        ],
    ],
] );
```

## Requirements

- PHP 7.4+
- WordPress 5.0+
- arraypress/wp-composer-assets

## License

GPL-2.0-or-later

## Credits

Created by [David Sherlock](https://davidsherlock.com) at [ArrayPress](https://arraypress.com).
