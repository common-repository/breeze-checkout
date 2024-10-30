<?php
/**
 * Breeze 1-click checkout
 * Delete Plugin Data.
 *
 * @package breeze 1-click checkout
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
  exit;
}

// deleting multiple options
$options = array(
  'b1cco_script_url',
  'b1cco_btn_enable_utm_params',
  'b1cco_get_custom_cookie_data'
);

foreach ($options as $index => $key) {
  delete_option($key);
}