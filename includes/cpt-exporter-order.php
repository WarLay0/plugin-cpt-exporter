<?php

if (!defined('ABSPATH')) exit;

// Every native field, taxonomy and ACF field of a post type as one list keyed "group:key", in the saved order.
// Items the saved order does not know yet (a new ACF field...) follow in the default order: native fields, taxonomies, ACF fields.
function cpt_exporter_items(array $cpt, array $order): array {
  $items = [];
  foreach (['fields', 'taxonomies', 'acf'] as $group) {
    foreach ($cpt[$group] as $key => $label) {
      $items["$group:$key"] = ['group' => $group, 'key' => (string) $key, 'label' => $label];
    }
  }
  $sorted = [];
  foreach ($order as $id) {
    if (isset($items[$id])) {
      $sorted[$id] = $items[$id];
    }
  }
  return $sorted + $items;
}

// Drag and drop on the settings page, with the jQuery UI Sortable bundled in WordPress.
// Arrow keys on a handle move its row too, so the order can be set without a mouse.
add_action('admin_enqueue_scripts', function ($hook_suffix) {
  if ($hook_suffix !== 'settings_page_cpt-exporter') {
    return;
  }
  wp_enqueue_script('jquery-ui-sortable');
  wp_add_inline_script('jquery-ui-sortable', <<<'JS'
jQuery(function ($) {
  // The handle is a button (keyboard access), and buttons are in jQuery UI's default "cancel" list.
  $('.cpt-exporter-details').sortable({ handle: '.cpt-exporter-handle', cancel: '', axis: 'y', cursor: 'move' });
  $(document).on('keydown', '.cpt-exporter-handle', function (event) {
    const row = $(this).closest('li');
    if (event.key === 'ArrowUp' && row.prev().length) {
      row.insertBefore(row.prev());
    } else if (event.key === 'ArrowDown' && row.next().length) {
      row.insertAfter(row.next());
    } else {
      return;
    }
    event.preventDefault();
    // Moving the row in the DOM drops the focus.
    this.focus();
  });
});
JS);
});
