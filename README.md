# WordPress EU VAT

## Description

WordPress EU VAT is a plugin that helps online store owners comply with EU VAT regulations. It collects and validates VAT numbers at checkout, removes VAT charges for eligible EU businesses, and handles EU tax requirements for digital goods.

## Features

- Collect and validate EU VAT numbers at checkout
- Exempt eligible businesses from paying VAT
- Validate user location in B2C transactions
- Handle EU tax requirements for digital goods
- Perform all operations locally without relying on third-party APIs

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.0 or higher

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

Currently, the plugin doesn't have a separate settings page. All functionality is handled automatically. Future versions may include configurable options.

## Frequently Asked Questions

**Q: Does this plugin validate VAT numbers in real-time?**
A: The current version includes basic format validation. For production use, you should implement a connection to the VIES (VAT Information Exchange System) service or a similar API for real-time validation.

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
