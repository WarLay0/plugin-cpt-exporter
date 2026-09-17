<?php

if (!defined('ABSPATH')) exit;

// Add an Export dropdown to the list toolbar of post types selected in the settings.
add_action('manage_posts_extra_tablenav', function ($which) {
  $settings = get_option('cpt_exporter_settings', []);
  if ($which !== 'top' || empty($settings[get_current_screen()->post_type]['enabled']) || !current_user_can('export')) {
    return;
  }
  // No "actions" class: WordPress hides those under 782px. The menu overlays the table instead of pushing the toolbar.
  ?>
  <style>
    .cpt-exporter-export > details { position: relative; }
    .cpt-exporter-export > details > div { position: absolute; z-index: 10; display: grid; min-width: 100%; padding: 4px 0; background: #fff; border: 1px solid #c3c4c7; }
    .cpt-exporter-export .button-link { padding: 4px 12px; text-decoration: none; }
  </style>
  <div class="alignleft cpt-exporter-export">
    <details>
      <summary class="button"><?php esc_html_e('Export', 'plugin-cpt-exporter'); ?> <span aria-hidden="true">▾</span></summary>
      <div>
        <?php foreach (['CSV', 'XLS'] as $format) : ?>
          <button type="button" class="button-link"><?php echo esc_html($format); ?></button>
        <?php endforeach; ?>
      </div>
    </details>
  </div>
  <?php
});
