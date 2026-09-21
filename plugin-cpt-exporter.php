<?php
/*
 * Plugin Name:      (Evan) - CPT Exporter Plugin
 * Description:      Exports custom post types.
 * Version:          0.0.1
 * Author:           Evan BOMBART
 * Text Domain:      plugin-cpt-exporter
 * Domain Path:      /languages
 */

if (!defined('ABSPATH')) exit;

// Outside wordpress.org, WordPress only looks for translations in wp-content/languages: point it to ours.
add_action('init', function () {
  load_plugin_textdomain('plugin-cpt-exporter', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

require_once __DIR__ . '/includes/cpt-exporter-settings.php';
require_once __DIR__ . '/includes/cpt-exporter-order.php';
require_once __DIR__ . '/includes/cpt-exporter-button.php';
require_once __DIR__ . '/includes/cpt-exporter-export.php';
require_once __DIR__ . '/includes/cpt-exporter-csv.php';
require_once __DIR__ . '/includes/cpt-exporter-xlsx.php';
