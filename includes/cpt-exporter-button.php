<?php

if (!defined('ABSPATH')) exit;

// Add an Export dropdown to the list toolbar of post types with at least one field selected in the settings.
add_action('manage_posts_extra_tablenav', function ($which) {
  $post_type = get_current_screen()->post_type;
  if ($which !== 'top' || !current_user_can('export') || !cpt_exporter_columns($post_type)) {
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
        <?php foreach (['csv' => 'CSV', 'xlsx' => 'XLSX'] as $format => $label) : ?>
          <a class="button-link" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'cpt_exporter_export', 'post_type' => $post_type, 'format' => $format], admin_url('admin-post.php')), 'cpt_exporter_export')); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
      </div>
    </details>
  </div>
  <?php
});
