<?php

/**
 * Plugin Name: WordPress EU VAT
 * Plugin URI: https://github.com/Open-WP-Club/WP-eu-vat
 * Description: Collect VAT numbers at checkout and remove the VAT charge for eligible EU businesses.
 * Version: 0.0.1
 * Author: Open WP Club
 * Author URI: https://openwpclub.com
 * License: GPL-2.0 License
 * Requires Plugins: woocommerce
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.2.1
 */


if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class EU_VAT_Number_WooCommerce
{

  private $eu_countries = array('AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE');

  public function __construct()
  {
    add_action('woocommerce_init', array($this, 'init'));
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
  }

  public function add_vat_number_field($checkout)
  {
    woocommerce_form_field('vat_number', array(
      'type' => 'text',
      'class' => array('form-row-wide'),
      'label' => __('VAT Number', 'eu-vat-number-woo'),
      'placeholder' => __('Enter VAT Number', 'eu-vat-number-woo'),
    ), $checkout->get_value('vat_number'));
  }

  public function validate_vat_number()
  {
    if (!empty($_POST['vat_number'])) {
      $vat_number = sanitize_text_field($_POST['vat_number']);
      if (!$this->is_valid_vat_number($vat_number)) {
        wc_add_notice(__('Invalid VAT number. Please check and try again.', 'eu-vat-number-woo'), 'error');
      }
    }
  }

  public function save_vat_number($order_id)
  {
    if (!empty($_POST['vat_number'])) {
      update_post_meta($order_id, '_vat_number', sanitize_text_field($_POST['vat_number']));
    }
  }

  public function maybe_exempt_vat($taxes, $price, $rates, $price_includes_tax, $suppress_rounding)
  {
    if (!empty($_POST['vat_number']) && $this->is_valid_vat_number($_POST['vat_number'])) {
      return array(); // Return empty array to remove VAT
    }
    return $taxes;
  }

  public function validate_user_location($country, $customer)
  {
    $geolocated_country = $this->get_user_country_by_ip();

    if ($geolocated_country && $geolocated_country !== $country) {
      wc_add_notice(__('Your billing country does not match your detected location. Please update your billing information or confirm your location.', 'eu-vat-number-woo'), 'notice');
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
    // In a real-world scenario, you would make an API call to a VAT validation service here
    // For this example, we'll just return true
    return true;
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
      $errors->add('validation', __('Your billing country does not match your detected location. Please verify your information or confirm your location.', 'eu-vat-number-woo'));
    }
  }
}

new EU_VAT_Number_WooCommerce();
