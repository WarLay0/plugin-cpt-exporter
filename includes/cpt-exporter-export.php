<?php

if (!defined('ABSPATH')) exit;

// Stream the export of a post type in the requested format (links of the Export dropdown).
add_action('admin_post_cpt_exporter_export', function () {
  check_admin_referer('cpt_exporter_export');
  if (!current_user_can('export')) {
    wp_die(esc_html__('Sorry, you are not allowed to export content.', 'plugin-cpt-exporter'), 403);
  }
  $post_type = sanitize_key(wp_unslash($_GET['post_type'] ?? ''));
  $format = sanitize_key(wp_unslash($_GET['format'] ?? ''));
  if (!in_array($format, ['csv', 'xlsx'], true)) {
    wp_die(esc_html__('Unknown export format.', 'plugin-cpt-exporter'), 400);
  }
  $columns = cpt_exporter_columns($post_type);
  if (!$columns) {
    wp_die(esc_html__('Nothing to export: select this post type and at least one field in Settings > CPT Exporter.', 'plugin-cpt-exporter'), 400);
  }

  // Any buffered output (notices, stray whitespace) would corrupt the file.
  while (ob_get_level()) {
    ob_end_clean();
  }
  $filename = $post_type . '-' . wp_date('Y-m-d') . '.' . $format;
  $header = ['#', ...array_column($columns, 'label')];
  $rows = cpt_exporter_rows($post_type, $columns);
  match ($format) {
    'csv'  => cpt_exporter_send_csv($filename, $header, $rows),
    'xlsx' => cpt_exporter_send_xlsx($filename, get_post_type_object($post_type)->label, $header, $rows),
  };
  exit;
});

// Columns of a selected post type: its checked items, in the order they were dragged into on the settings page.
// Each column has a label and a callback turning a post into the cell text. Empty when nothing is selected.
function cpt_exporter_columns(string $post_type): array {
  $selection = get_option('cpt_exporter_settings', [])[$post_type] ?? [];
  $cpt = require_cpts()[$post_type] ?? null;
  if (!$cpt || empty($selection['enabled'])) {
    return [];
  }

  $native = [
    'title'     => fn(WP_Post $post) => cpt_exporter_text($post->post_title),
    'editor'    => fn(WP_Post $post) => cpt_exporter_text($post->post_content),
    'excerpt'   => fn(WP_Post $post) => cpt_exporter_text($post->post_excerpt),
    'thumbnail' => fn(WP_Post $post) => (string) get_the_post_thumbnail_url($post, 'full'),
    'author'    => fn(WP_Post $post) => (string) get_the_author_meta('display_name', $post->post_author),
  ];
  $columns = [];
  foreach (cpt_exporter_items($cpt, $selection['order'] ?? []) as ['group' => $group, 'key' => $key, 'label' => $label]) {
    if (!in_array($key, $selection[$group] ?? [], true)) {
      continue;
    }
    if ($group === 'taxonomies') {
      $columns[] = ['label' => $label, 'value' => function (WP_Post $post) use ($key) {
        $terms = get_the_terms($post, $key);
        return is_array($terms) ? implode(', ', array_map(fn(WP_Term $term) => cpt_exporter_text($term->name), $terms)) : '';
      }];
    } elseif ($group === 'acf') {
      $columns[] = ['label' => $label, 'value' => fn(WP_Post $post) => cpt_exporter_acf_text(get_field($key, $post->ID))];
    } elseif ($key === 'page-attributes') {
      // Two values behind one support: parent and menu order.
      $columns[] = ['label' => __('Parent', 'plugin-cpt-exporter'), 'value' => fn(WP_Post $post) => $post->post_parent ? cpt_exporter_text(get_post_field('post_title', $post->post_parent)) : ''];
      $columns[] = ['label' => __('Order', 'plugin-cpt-exporter'), 'value' => fn(WP_Post $post) => (string) $post->menu_order];
    } else {
      $columns[] = ['label' => $label, 'value' => $native[$key]];
    }
  }
  return $columns;
}

// One row of cell texts per published post, newest first, read in batches to keep memory flat on large sites.
// Each row starts with its number, counted from 1 across batches.
function cpt_exporter_rows(string $post_type, array $columns): Generator {
  $batch = 200;
  $number = 0;
  for ($page = 1; ; $page++) {
    $posts = get_posts([
      'post_type'      => $post_type,
      'post_status'    => 'publish',
      'posts_per_page' => $batch,
      'paged'          => $page,
      // ID breaks date ties, so no post is skipped or repeated across batches.
      'orderby'        => ['date' => 'DESC', 'ID' => 'DESC'],
    ]);
    foreach ($posts as $post) {
      yield [(string) ++$number, ...array_map(fn(array $column) => $column['value']($post), $columns)];
    }
    if (count($posts) < $batch) {
      return;
    }
    // Drop the meta and term caches of the batch just written.
    if (wp_cache_supports('flush_runtime')) {
      wp_cache_flush_runtime();
    }
  }
}

// Plain text from a stored WordPress value: shortcodes, tags and block comments removed, entities decoded,
// lines trimmed and at most one blank line in a row.
function cpt_exporter_text(string $html): string {
  $text = html_entity_decode(wp_strip_all_tags(strip_shortcodes($html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $text = preg_replace('/^[ \t]+|[ \t]+$/m', '', str_replace(["\r\n", "\r"], "\n", $text));
  return trim(preg_replace("/\n{3,}/", "\n\n", $text));
}

// ACF formatted value as text: posts, terms and users by name, image, file and link arrays by URL,
// "both" choices by label, lists joined with commas, other structures (group, repeater row) as JSON.
function cpt_exporter_acf_text(mixed $value): string {
  if ($value instanceof WP_Post) {
    return cpt_exporter_text($value->post_title);
  }
  if ($value instanceof WP_Term) {
    return cpt_exporter_text($value->name);
  }
  if ($value instanceof WP_User) {
    return $value->display_name;
  }
  if (is_array($value)) {
    foreach (['url', 'label', 'display_name'] as $key) {
      if (isset($value[$key]) && is_scalar($value[$key])) {
        return (string) $value[$key];
      }
    }
    $items = array_map('cpt_exporter_acf_text', $value);
    return array_is_list($value)
      ? implode(', ', array_filter($items, 'strlen'))
      : (string) wp_json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
  if (is_bool($value)) {
    return $value ? '1' : '0';
  }
  return is_scalar($value) ? (string) $value : '';
}

// Download headers shared by every format.
function cpt_exporter_send_headers(string $filename, string $content_type, ?int $length = null): void {
  nocache_headers();
  header('Content-Type: ' . $content_type);
  // RFC 5987: encode filename for non-ASCII characters and special chars; provide ASCII fallback.
  $ascii_filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
  $encoded_filename = rawurlencode($filename);
  header("Content-Disposition: attachment; filename=\"{$ascii_filename}\"; filename*=UTF-8''{$encoded_filename}");
  header('X-Content-Type-Options: nosniff');
  if ($length !== null) {
    header('Content-Length: ' . $length);
  }
}
