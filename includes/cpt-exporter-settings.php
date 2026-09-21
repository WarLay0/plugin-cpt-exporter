<?php

if (!defined('ABSPATH')) exit;

// Add the CPT Exporter options page under the Settings menu.
add_action('admin_menu', function () {
  add_options_page(
    'CPT Exporter',
    'CPT Exporter',
    'manage_options',
    'cpt-exporter',
    function () {
      echo '<div class="wrap"><h1>' . esc_html(get_admin_page_title()) . '</h1>';
      print_form();
      echo '</div>';
    }
  );
});

// Store every selection in a single option, keeping only values the page actually offers.
add_action('admin_init', function () {
  register_setting('cpt_exporter', 'cpt_exporter_settings', [
    'type'              => 'array',
    'default'           => [],
    'sanitize_callback' => function ($input) {
      $clean = [];
      foreach (require_cpts() as $name => $cpt) {
        $saved = is_array($input[$name] ?? null) ? $input[$name] : [];
        $clean[$name] = ['enabled' => !empty($saved['enabled'])];
        foreach (['fields', 'taxonomies', 'acf'] as $group) {
          $clean[$name][$group] = array_values(array_intersect((array) ($saved[$group] ?? []), array_keys($cpt[$group])));
        }
        // Rows in the order they were dropped, each known row once.
        $clean[$name]['order'] = array_values(array_unique(array_intersect((array) ($saved['order'] ?? []), array_keys(cpt_exporter_items($cpt, [])))));
      }
      return $clean;
    },
  ]);
});

// Print the settings form: for each post type, one list of its native fields, taxonomies and ACF fields
// to check and drag into place. The list order is the column order of the exports.
function print_form(): void {
  $settings = get_option('cpt_exporter_settings', []);
  $groups = [
    'fields'     => __('Native field', 'plugin-cpt-exporter'),
    'taxonomies' => __('Taxonomy', 'plugin-cpt-exporter'),
    'acf'        => __('ACF field', 'plugin-cpt-exporter'),
  ];
  // A post type's sub-options only show once it is checked: CSS :has(), no JS needed.
  ?>
  <style>.cpt-exporter-type:not(:has(> label > input:checked)) > .cpt-exporter-details { display: none; }</style>
  <form method="post" action="options.php">
    <?php settings_fields('cpt_exporter'); ?>
    <?php foreach (require_cpts() as $name => $cpt) : $saved = $settings[$name] ?? []; $prefix = "cpt_exporter_settings[$name]"; ?>
      <div class="cpt-exporter-type">
        <label><input type="checkbox" name="<?php echo esc_attr("{$prefix}[enabled]"); ?>" value="1" <?php checked(!empty($saved['enabled'])); ?>> <strong><?php echo esc_html($cpt['label']); ?></strong></label>
        <ol class="cpt-exporter-details">
          <?php foreach (cpt_exporter_items($cpt, $saved['order'] ?? []) as $id => ['group' => $group, 'key' => $key, 'label' => $label]) : ?>
            <li>
              <input type="hidden" name="<?php echo esc_attr("{$prefix}[order][]"); ?>" value="<?php echo esc_attr($id); ?>">
              <button type="button" class="cpt-exporter-handle" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: field or taxonomy label. */ __('Move %s', 'plugin-cpt-exporter'), $label)); ?>"><span class="dashicons dashicons-menu" aria-hidden="true"></span></button>
              <label><input type="checkbox" name="<?php echo esc_attr("{$prefix}[{$group}][]"); ?>" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $saved[$group] ?? [], true)); ?>> <?php echo esc_html($label); ?> (<?php echo esc_html($groups[$group]); ?>)</label>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    <?php endforeach; ?>
    <?php submit_button(); ?>
  </form>
  <?php
}

// Posts, then custom post types that can be exported, each with the taxonomies,
// native fields and ACF fields it offers.
function require_cpts(): array {
  $post_types = get_post_types(['_builtin' => false, 'can_export' => true], 'objects');
  // No flag tells Elementor's internal types apart (elementor_library is public and has a menu),
  // so Elementor and ACF types are excluded by prefix. A custom type named e-* or e_* would be hidden too.
  $post_types = array_filter($post_types, fn($post_type) => !preg_match('/^(elementor_|e-|e_|acf-)/', $post_type->name));
  $post_types = ['post' => get_post_type_object('post')] + $post_types;

  // Supports that hold content; the others (revisions, autosave, elementor...) are behaviours.
  $native_fields = [
    'title'           => __('Title', 'plugin-cpt-exporter'),
    'editor'          => __('Content', 'plugin-cpt-exporter'),
    'excerpt'         => __('Excerpt', 'plugin-cpt-exporter'),
    'thumbnail'       => __('Featured image', 'plugin-cpt-exporter'),
    'author'          => __('Author', 'plugin-cpt-exporter'),
    'page-attributes' => __('Page attributes', 'plugin-cpt-exporter'),
  ];

  $cpts = [];
  foreach ($post_types as $name => $post_type) {
    // show_ui leaves out internal taxonomies such as post_format or Polylang's language.
    $taxonomies = array_filter(get_object_taxonomies($name, 'objects'), fn($taxonomy) => $taxonomy->show_ui);
    $acf_fields = [];
    // ACF is optional: without it, post types simply have no ACF fields.
    if (function_exists('acf_get_field_groups')) {
      foreach (acf_get_field_groups(['post_type' => $name]) as $group) {
        foreach (acf_get_fields($group) as $field) {
          // Layout fields hold no value.
          if (!in_array($field['type'], ['tab', 'message', 'accordion'], true)) {
            // The label set in ACF, or the field name when the label is left empty.
            $acf_fields[$field['key']] = trim($field['label']) !== '' ? $field['label'] : $field['name'];
          }
        }
      }
    }
    $cpts[$name] = [
      'label'      => $post_type->label,
      'fields'     => array_filter($native_fields, fn($support) => post_type_supports($name, $support), ARRAY_FILTER_USE_KEY),
      'taxonomies' => wp_list_pluck($taxonomies, 'label'),
      'acf'        => $acf_fields,
    ];
  }
  return $cpts;
}
