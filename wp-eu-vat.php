<?php

/**
 * Plugin Name: WordPress EU VAT
 * Plugin URI: https://github.com/Open-WP-Club/WP-eu-vat
 * Description: Collect VAT numbers at checkout and remove the VAT charge for eligible EU businesses.
 * Version: 1.0.0
 * Author: Open WP Club
 * Author URI: https://openwpclub.com
 * License: GPL-2.0 License
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-eu-vat
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.4
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

// Declare HPOS compatibility
add_action('before_woocommerce_init', function () {
  if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
  }
});

class EU_VAT_Number_WooCommerce
{

  private $eu_countries = array('AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE');

  public function __construct()
  {
    add_action('woocommerce_init', array($this, 'init'));
    add_action('init', array($this, 'load_textdomain'));
  }

  /**
   * Load plugin text domain for translations
   */
  public function load_textdomain()
  {
    load_plugin_textdomain('wp-eu-vat', false, dirname(plugin_basename(__FILE__)) . '/languages');
  }

  public function init()
  {
    add_action('woocommerce_after_checkout_billing_form', array($this, 'add_vat_number_field'));
    add_action('woocommerce_checkout_process', array($this, 'validate_vat_number'));
    add_action('woocommerce_checkout_update_order_meta', array($this, 'save_vat_number'));
    add_filter('woocommerce_calc_tax', array($this, 'maybe_exempt_vat'), 10, 5);
    add_filter('woocommerce_customer_get_billing_country', array($this, 'validate_user_location'), 10, 2);
    add_action('woocommerce_checkout_update_order_review', array($this, 'handle_digital_goods_tax'));
    add_action('woocommerce_after_checkout_validation', array($this, 'validate_location'), 10, 2);

    // Admin settings
    add_filter('woocommerce_get_settings_tax', array($this, 'add_vat_settings'), 10, 2);
    add_action('admin_init', array($this, 'register_clear_cache_action'));
  }

  public function add_vat_number_field($checkout)
  {
    woocommerce_form_field('vat_number', array(
      'type' => 'text',
      'class' => array('form-row-wide'),
      'label' => __('VAT Number', 'wp-eu-vat'),
      'placeholder' => __('Enter VAT Number', 'wp-eu-vat'),
    ), $checkout->get_value('vat_number'));
  }

  public function validate_vat_number()
  {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checkout handles nonce verification
    if (!empty($_POST['vat_number'])) {
      $vat_number = sanitize_text_field(wp_unslash($_POST['vat_number']));
      if (!$this->is_valid_vat_number($vat_number)) {
        wc_add_notice(__('Invalid VAT number. Please check and try again.', 'wp-eu-vat'), 'error');
      }
    }
  }

  public function save_vat_number($order_id)
  {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checkout handles nonce verification
    if (!empty($_POST['vat_number'])) {
      $vat_number = sanitize_text_field(wp_unslash($_POST['vat_number']));
      $order = wc_get_order($order_id);

      if ($order) {
        // HPOS compatible way to save order meta
        $order->update_meta_data('_vat_number', $vat_number);
        $order->save();
      }
    }
  }

  public function maybe_exempt_vat($taxes, $price, $rates, $price_includes_tax, $suppress_rounding)
  {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checkout handles nonce verification
    if (!empty($_POST['vat_number'])) {
      $vat_number = sanitize_text_field(wp_unslash($_POST['vat_number']));
      if ($this->is_valid_vat_number($vat_number)) {
        return array(); // Return empty array to remove VAT
      }
    }
    return $taxes;
  }

  public function validate_user_location($country, $customer)
  {
    $geolocated_country = $this->get_user_country_by_ip();

    if ($geolocated_country && $geolocated_country !== $country) {
      wc_add_notice(__('Your billing country does not match your detected location. Please update your billing information or confirm your location.', 'wp-eu-vat'), 'notice');
    }

    return $country; // Return the original country to avoid overriding user input
  }

  public function handle_digital_goods_tax($posted_data)
  {
    $cart = WC()->cart;
    $has_digital_goods = false;

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
      $product = $cart_item['data'];
      if ($this->is_digital_good($product)) {
        $has_digital_goods = true;
        break;
      }
    }

    if ($has_digital_goods) {
      $customer_country = WC()->customer->get_billing_country();
      if (in_array($customer_country, $this->eu_countries)) {
        // Apply VAT based on customer's country for digital goods
        add_filter('woocommerce_rate_percent', array($this, 'apply_digital_goods_vat'), 10, 3);
      }
    }
  }

  public function apply_digital_goods_vat($rate, $tax_rate_id, $tax_rate_class)
  {
    $customer_country = WC()->customer->get_billing_country();
    $vat_rates = $this->get_eu_vat_rates();

    if (isset($vat_rates[$customer_country])) {
      return $vat_rates[$customer_country];
    }

    return $rate;
  }

  private function is_valid_vat_number($vat_number)
  {
    $country_code = substr($vat_number, 0, 2);
    $vat_number = substr($vat_number, 2);

    if (!in_array($country_code, $this->eu_countries)) {
      return false;
    }

    // Basic format validation
    $formats = array(
      'AT' => '/^U[0-9]{8}$/',
      'BE' => '/^0[0-9]{9}$/',
      'BG' => '/^[0-9]{9,10}$/',
      'HR' => '/^[0-9]{11}$/',
      'CY' => '/^[0-9]{8}[A-Z]$/',
      'CZ' => '/^[0-9]{8,10}$/',
      'DK' => '/^[0-9]{8}$/',
      'EE' => '/^[0-9]{9}$/',
      'FI' => '/^[0-9]{8}$/',
      'FR' => '/^[0-9A-Z]{2}[0-9]{9}$/',
      'DE' => '/^[0-9]{9}$/',
      'GR' => '/^[0-9]{9}$/',
      'HU' => '/^[0-9]{8}$/',
      'IE' => '/^[0-9]{7}[A-Z]{1,2}$/',
      'IT' => '/^[0-9]{11}$/',
      'LV' => '/^[0-9]{11}$/',
      'LT' => '/^[0-9]{9}|[0-9]{12}$/',
      'LU' => '/^[0-9]{8}$/',
      'MT' => '/^[0-9]{8}$/',
      'NL' => '/^[0-9]{9}B[0-9]{2}$/',
      'PL' => '/^[0-9]{10}$/',
      'PT' => '/^[0-9]{9}$/',
      'RO' => '/^[0-9]{2,10}$/',
      'SK' => '/^[0-9]{10}$/',
      'SI' => '/^[0-9]{8}$/',
      'ES' => '/^[0-9A-Z][0-9]{7}[0-9A-Z]$/',
      'SE' => '/^[0-9]{12}$/'
    );

    if (!isset($formats[$country_code]) || !preg_match($formats[$country_code], $vat_number)) {
      return false;
    }

    // For a production environment, you should use a VAT validation service or API
    // This is a placeholder for that service
    return $this->validate_vat_with_service($country_code, $vat_number);
  }

  private function validate_vat_with_service($country_code, $vat_number)
  {
    // Check if VIES validation is enabled
    $vies_enabled = get_option('eu_vat_enable_vies', 'yes') === 'yes';

    if (!$vies_enabled) {
      // VIES validation is disabled, rely on format validation only
      return true;
    }

    // Check cache first
    $cache_key = 'vat_validation_' . md5($country_code . $vat_number);
    $cached_result = get_transient($cache_key);

    if ($cached_result !== false) {
      return $cached_result === 'valid';
    }

    try {
      // VIES SOAP endpoint
      $wsdl = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

      // Check if SOAP extension is available
      if (!class_exists('SoapClient')) {
        error_log('WP EU VAT: SOAP extension not available. Falling back to format validation only.');
        return true; // Fallback to format validation
      }

      $client = new SoapClient($wsdl, array(
        'connection_timeout' => 10,
        'exceptions' => true,
        'cache_wsdl' => WSDL_CACHE_BOTH
      ));

      $params = array(
        'countryCode' => $country_code,
        'vatNumber' => $vat_number
      );

      $response = $client->checkVat($params);

      // Get cache duration from settings (in hours)
      $cache_duration = absint(get_option('eu_vat_cache_duration', 24));
      $cache_duration_seconds = $cache_duration * HOUR_IN_SECONDS;

      // Cache the result
      $is_valid = isset($response->valid) && $response->valid === true;
      set_transient($cache_key, $is_valid ? 'valid' : 'invalid', $cache_duration_seconds);

      return $is_valid;

    } catch (SoapFault $e) {
      // Log the error
      error_log('WP EU VAT: VIES validation error - ' . $e->getMessage());

      // If VIES service is unavailable, fallback to format validation
      // This prevents blocking legitimate transactions when the service is down
      return true;

    } catch (Exception $e) {
      error_log('WP EU VAT: Unexpected error during VAT validation - ' . $e->getMessage());
      return true; // Fallback to format validation
    }
  }

  private function get_user_country_by_ip()
  {
    if (class_exists('WC_Geolocation')) {
      $geolocation = WC_Geolocation::geolocate_ip();
      return $geolocation['country'];
    }
    return false;
  }

  private function is_digital_good($product)
  {
    // Define your own logic to determine if a product is a digital good
    // This is a simple example based on product type
    $digital_types = array('digital', 'downloadable', 'virtual');
    return in_array($product->get_type(), $digital_types);
  }

  private function get_eu_vat_rates()
  {
    // These rates should be updated regularly
    return array(
      'AT' => 20,
      'BE' => 21,
      'BG' => 20,
      'HR' => 25,
      'CY' => 19,
      'CZ' => 21,
      'DK' => 25,
      'EE' => 20,
      'FI' => 24,
      'FR' => 20,
      'DE' => 19,
      'GR' => 24,
      'HU' => 27,
      'IE' => 23,
      'IT' => 22,
      'LV' => 21,
      'LT' => 21,
      'LU' => 17,
      'MT' => 18,
      'NL' => 21,
      'PL' => 23,
      'PT' => 23,
      'RO' => 19,
      'SK' => 20,
      'SI' => 22,
      'ES' => 21,
      'SE' => 25
    );
  }

  public function validate_location($fields, $errors)
  {
    $billing_country = $fields['billing_country'];
    $ip_country = $this->get_user_country_by_ip();

    if ($ip_country && $billing_country !== $ip_country) {
      $errors->add('validation', __('Your billing country does not match your detected location. Please verify your information or confirm your location.', 'wp-eu-vat'));
    }
  }

  /**
   * Add VAT settings to WooCommerce Tax settings
   */
  public function add_vat_settings($settings, $current_section)
  {
    if ($current_section === '') {
      $vat_settings = array(
        array(
          'title' => __('EU VAT Validation', 'wp-eu-vat'),
          'type' => 'title',
          'desc' => __('Configure EU VAT number validation settings', 'wp-eu-vat'),
          'id' => 'eu_vat_validation_options'
        ),
        array(
          'title' => __('Enable VIES Validation', 'wp-eu-vat'),
          'desc' => __('Validate VAT numbers against the European VIES system in real-time', 'wp-eu-vat'),
          'id' => 'eu_vat_enable_vies',
          'default' => 'yes',
          'type' => 'checkbox'
        ),
        array(
          'title' => __('Cache Duration', 'wp-eu-vat'),
          'desc' => __('How long to cache validation results (in hours)', 'wp-eu-vat'),
          'id' => 'eu_vat_cache_duration',
          'default' => '24',
          'type' => 'number',
          'custom_attributes' => array(
            'min' => '1',
            'max' => '168'
          )
        ),
        array(
          'title' => __('Clear VAT Cache', 'wp-eu-vat'),
          'desc' => __('Clear all cached VAT validation results', 'wp-eu-vat'),
          'id' => 'eu_vat_clear_cache',
          'type' => 'button',
          'desc_tip' => true,
        ),
        array(
          'type' => 'sectionend',
          'id' => 'eu_vat_validation_options'
        )
      );

      // Insert VAT settings after the standard options
      $settings = array_merge($settings, $vat_settings);
    }

    return $settings;
  }

  /**
   * Register clear cache action handler
   */
  public function register_clear_cache_action()
  {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification happens below
    if (isset($_GET['clear_vat_cache']) && current_user_can('manage_woocommerce')) {
      check_admin_referer('clear_vat_cache');
      $this->clear_vat_cache();
      wp_redirect(admin_url('admin.php?page=wc-settings&tab=tax&cache_cleared=1'));
      exit;
    }

    // Show admin notice after clearing cache
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only, no action performed
    if (isset($_GET['cache_cleared']) && '1' === $_GET['cache_cleared']) {
      add_action('admin_notices', function () {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('VAT validation cache has been cleared.', 'wp-eu-vat') . '</p></div>';
      });
    }

    // Add clear cache button functionality
    add_action('woocommerce_admin_field_button', array($this, 'render_clear_cache_button'));
  }

  /**
   * Render clear cache button in WooCommerce settings
   */
  public function render_clear_cache_button($value)
  {
    if ('eu_vat_clear_cache' !== $value['id']) {
      return;
    }

    $clear_url = wp_nonce_url(
      admin_url('admin.php?page=wc-settings&tab=tax&clear_vat_cache=1'),
      'clear_vat_cache'
    );
    ?>
    <tr valign="top">
      <th scope="row" class="titledesc">
        <label for="<?php echo esc_attr($value['id']); ?>"><?php echo esc_html($value['title']); ?></label>
      </th>
      <td class="forminp forminp-<?php echo esc_attr(sanitize_title($value['type'])); ?>">
        <a href="<?php echo esc_url($clear_url); ?>" class="button button-secondary">
          <?php esc_html_e('Clear Cache Now', 'wp-eu-vat'); ?>
        </a>
        <p class="description"><?php echo esc_html($value['desc']); ?></p>
      </td>
    </tr>
    <?php
  }

  /**
   * Clear all VAT validation cache
   */
  public function clear_vat_cache()
  {
    global $wpdb;

    // Delete all transients that start with 'vat_validation_'
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
      $wpdb->prepare(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE %s
         OR option_name LIKE %s",
        $wpdb->esc_like('_transient_vat_validation_') . '%',
        $wpdb->esc_like('_transient_timeout_vat_validation_') . '%'
      )
    );

    return true;
  }
}

new EU_VAT_Number_WooCommerce();
