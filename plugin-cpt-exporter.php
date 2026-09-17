<?php
/*
 * Plugin Name:      (Evan) - CPT Exporter Plugin
 * Description:      Exports custom post types.
 * Version:          0.0.1
 * Author:           Evan BOMBART
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/cpt-exporter-settings.php';
require_once __DIR__ . '/includes/cpt-exporter-button.php';
require_once __DIR__ . '/includes/cpt-exporter-export.php';
require_once __DIR__ . '/includes/cpt-exporter-csv.php';
require_once __DIR__ . '/includes/cpt-exporter-xlsx.php';
