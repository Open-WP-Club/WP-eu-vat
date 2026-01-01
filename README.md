# WordPress EU VAT

## Description

WordPress EU VAT is a plugin that helps online store owners comply with EU VAT regulations. It collects and validates VAT numbers at checkout, removes VAT charges for eligible EU businesses, and handles EU tax requirements for digital goods.

## Features

- Collect and validate EU VAT numbers at checkout
- **Real-time VAT validation with European VIES system**
- Exempt eligible businesses from paying VAT
- Validate user location in B2C transactions
- Handle EU tax requirements for digital goods
- Smart caching to minimize API calls and improve performance
- Automatic fallback to format validation if VIES is unavailable

## Requirements

- WordPress 6.4 or higher
- WooCommerce 8.0 or higher (tested up to 9.4)
- PHP 7.4 or higher
- PHP SOAP extension (for VIES validation)

## Installation

1. Download the plugin zip file.
2. Log in to your WordPress admin panel.
3. Go to Plugins > Add New.
4. Click on the "Upload Plugin" button at the top of the page.
5. Select the plugin zip file and click "Install Now".
6. After installation, click "Activate Plugin".

## Usage

Once activated, the plugin will automatically:

1. Add a VAT number field to the WooCommerce checkout page.
2. Validate the format of entered VAT numbers.
3. Remove VAT charges for valid business VAT numbers.
4. Apply appropriate VAT rates for digital goods based on the customer's location.

## Configuration

The plugin integrates with WooCommerce Tax settings. Go to **WooCommerce > Settings > Tax** to configure:

### EU VAT Validation Settings

- **Enable VIES Validation**: Toggle real-time validation against the European VIES system
- **Cache Duration**: Set how long validation results are cached (1-168 hours, default: 24 hours)
- **Clear VAT Cache**: Manually clear all cached validation results

These settings allow you to balance between accuracy, performance, and API usage.

## Modern WordPress & WooCommerce Standards

This plugin is built following the latest WordPress and WooCommerce development standards:

### WooCommerce HPOS Compatibility
- **Full HPOS (High-Performance Order Storage) support** - Compatible with WooCommerce's modern order storage system
- Uses `wc_get_order()` and order object methods instead of deprecated `update_post_meta()`
- Declared compatibility with `custom_order_tables` feature

### Security Best Practices
- Proper nonce verification for all admin actions
- Input sanitization with `sanitize_text_field()` and `wp_unslash()`
- Output escaping with `esc_html()`, `esc_attr()`, `esc_url()`
- Prepared SQL statements for database queries
- WordPress Coding Standards (WPCS) compliant

### Internationalization (i18n)
- Translation-ready with proper text domain (`wp-eu-vat`)
- Includes `.pot` translation template
- Bulgarian translation included as example
- Translations loaded via `load_plugin_textdomain()`

## Frequently Asked Questions

**Q: Does this plugin validate VAT numbers in real-time?**
A: Yes! The plugin validates VAT numbers against the European VIES (VAT Information Exchange System) using real-time SOAP API calls. Results are cached to improve performance and reduce API load. If the VIES service is unavailable, the plugin automatically falls back to format validation to prevent blocking legitimate transactions.

**Q: What if my server doesn't have the SOAP extension?**
A: The plugin will automatically detect if the SOAP extension is missing and fall back to format validation only. While SOAP is recommended for full VIES validation, the plugin will continue to work without it by validating the VAT number format according to each EU country's standards.

**Q: How does the caching work?**
A: When a VAT number is validated against VIES, the result is cached using WordPress transients. By default, results are cached for 24 hours (configurable from 1-168 hours). This reduces API calls and improves checkout performance. You can clear the cache anytime from the settings page.

**Q: How does the plugin determine if a product is a digital good?**
A: The plugin considers products of type 'digital', 'downloadable', or 'virtual' as digital goods. You may need to adjust this logic based on your specific product categorization.

**Q: Does this plugin handle VAT MOSS (Mini One Stop Shop) reporting?**
A: The current version does not include VAT MOSS reporting features. This may be added in future updates.

## Support

For support, feature requests, or bug reports, please open an issue on the plugin's GitHub repository.

## Contributing

Contributions to improve WordPress EU VAT are welcome. Please feel free to submit pull requests or open issues on GitHub.

## License

This plugin is licensed under the GPL v2 or later.
