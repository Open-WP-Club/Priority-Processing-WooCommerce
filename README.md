# WooCommerce Priority Processing

A WordPress plugin that adds a priority processing and express shipping option to WooCommerce checkout, allowing customers to pay an additional fee for faster order handling.

Current version: **1.8.1**

## Features

- **Priority Processing Option**: Add a checkbox at checkout for priority processing
- **Configurable Fee**: Set custom fee amounts with automatic cart updates
- **Flexible Display**: Customizable labels, descriptions, and section titles
- **Admin Integration**: Settings panel integrated with WooCommerce settings
- **Order Management**: Visual indicators for priority orders in admin
- **Motivational Messages**: Randomly rotated upsell messages on the cart page and product pages, with optional minimum order thresholds
- **Customer Account Badge**: Confirmation badge shown on the order view page in My Account for orders with priority processing
- **Modern Compatibility**: Supports both classic and block-based checkout
- **HPOS Ready**: Compatible with WooCommerce High-Performance Order Storage
- **Server-side Access Control**: Feature status, user permissions, and minimum order amount are enforced for classic AJAX, Store API, fees, and order creation
- **Scheduled Statistics**: Daily WooCommerce statistics refresh with transient caching

## Requirements

- WordPress 6.9+
- WooCommerce 9.0+ (tested up to 11.1)
- PHP 8.1+

## Installation

1. Upload the plugin files to `/wp-content/plugins/woocommerce-priority-processing/`
2. Activate the plugin through the WordPress admin
3. Go to **WooCommerce > Priority Processing** to configure settings

## Configuration

Navigate to **WooCommerce > Priority Processing** to customize:

### Basic Settings

- **Enable/Disable**: Toggle the priority processing feature
- **Fee Amount**: Set the additional charge (e.g., 5.00)
- **Section Title**: Customize the checkout section heading

### Display Options

- **Checkbox Label**: Text shown next to the checkbox
- **Description**: Help text displayed below the option
- **Fee Label**: How the fee appears in cart/order totals

### Motivational Messages

- **Cart Page Message**: Enable/disable an upsell message shown above the products table on the classic cart page or above the Cart Block
- **Product Page Message**: Enable/disable an upsell message shown under the "Add to cart" button on single product pages
- **Display Mode**: Show each message always, or only once the cart subtotal reaches a configurable minimum
- **Message Lists**: Enter one message per line for each location; a random one is picked on every page load, so you can add several variants
- Supports classic product templates and WooCommerce Add to Cart blocks on single product pages
- Cart Block messages update when quantities change or the cart crosses the configured threshold
- Ships with example English messages, translated to Bulgarian out of the box

Messages are disabled by default. Enable the main feature and each message location,
check guest access and allowed roles, and enter at least one non-empty message.
In threshold mode the cart subtotal must reach the configured amount; the product
price alone does not count. Missing message options on upgraded stores use the
default copy; deliberately empty lists remain empty. Custom theme or builder templates
must retain the WooCommerce hooks or supported blocks. Exclude cart and checkout
from full-page caching; cached product pages can also retain visitor-specific or
threshold-dependent messages.

### Customer Account Badge

- **Enable/Disable**: Toggle a confirmation badge on the order details view in My Account
- **Badge Label & Message**: Fully editable text
- Shown only on orders with priority processing, when customers revisit the order later (the checkout thank-you page already shows its own confirmation message)

## How It Works

### For Customers

1. During checkout, customers see the priority processing option
2. When selected, the fee is immediately added to their order total
3. Order proceeds with priority status for faster handling

### For Store Owners

- Priority orders are marked with ⚡ lightning bolt indicators
- Easy identification in order lists and individual order pages
- All settings managed through familiar WooCommerce interface

## Technical Features

- **AJAX Updates**: Smooth checkout experience without page reloads
- **Session Management**: Reliable state handling across checkout process
- **Security**: Proper nonce verification and data sanitization
- **Compatibility**: Works with popular themes and checkout customizations
- **Performance**: Lightweight implementation with minimal overhead

## Local Testing

### Unit Tests

The PHPUnit suite covers settings sanitization, permissions, checkout state,
fees, shipping metadata, account messages, order operations, statistics, and
REST API permissions.

PHPUnit 13 requires PHP 8.4.1 or newer for development. This does not change
the plugin's PHP 8.1 runtime requirement.

```bash
composer install
composer test
```

### JavaScript Tests

Use Node.js 22 and pnpm 12.9.1 (pinned in `package.json`). The pnpm configuration
allows the Playground filesystem extension to use its prebuilt native binary.

```bash
ppnpm install --frozen-lockfile --frozen-lockfile
pnpm run test:js
```

The Node.js suite checks Cart Block Store API updates, threshold and permission
visibility, empty carts, plain-text rendering, DOM initialization, and single
AJAX requests after repeated classic checkout refreshes.

### WordPress and WooCommerce Integration Test

Docker, Node.js 22 and pnpm 12.9.1 are required. `wp-env` creates an isolated local
site using PHP 8.4 and installs the latest WordPress and WooCommerce releases.

```bash
pnpm install --frozen-lockfile
pnpm run env:start
pnpm run test:integration
```

The integration smoke test verifies:

- WooCommerce and Priority Processing activation
- Plugin service initialization
- Daily statistics cron scheduling
- Priority fee creation in a real WooCommerce cart
- WooCommerce order creation and CRUD persistence
- Priority and express-service order metadata
- Priority-order queries through the WooCommerce storage API, including HPOS-compatible access
- Real Cart Block message rendering and Store API message data
- Threshold changes after quantity updates and denied guest access
- Product block fallback messages and duplicate prevention
- Cleanup of temporary products, cart items, orders, session state, and modified options

Open the local site at `http://localhost:8888` and sign in with `admin` /
`password`.

```bash
pnpm run env:stop   # Stop the containers and preserve their data.
pnpm run env:clean  # Reset the development database and environment.
```

For a complete local check, run both suites:

```bash
composer test
pnpm run test:js
pnpm run test:integration
```

## Admin Features

- **Visual Indicators**: Priority orders clearly marked with ⚡ symbol
- **Order Integration**: Priority status shown in order details
- **Settings Integration**: Native WooCommerce settings interface
- **Fallback Support**: Separate admin page if needed

## Changelog

### 1.8.1

- Fix missing motivational messages in the WooCommerce Cart Block and block product templates.
- Refresh Cart Block messages through Store API data after cart changes, respecting permissions and thresholds.
- Use default message copy when options have not yet been created after an upgrade.
- Avoid duplicate product notices and duplicate classic checkout AJAX requests.
- Render AJAX errors as plain text.
- Migrate development tooling and CI from npm to pnpm 12.9.1.
- Update wp-env, PHPUnit and transitive dependencies; pin patched same-major overrides for dependencies pinned upstream.
- Verified on WordPress 7.1.2 and WooCommerce 11.1.2.

## License

Licensed under the Apache License 2.0. See [LICENSE](LICENSE) for details.

## Author

Created by [OpenWPClub.com](https://openwpclub.com)
