<?php

if (!defined('ABSPATH')) exit;

// Stream a CSV that Excel opens straight into columns in French locales:
// UTF-8 with BOM (accents), semicolon separators, RFC 4180 quoting and CRLF line endings.
function cpt_exporter_send_csv(string $filename, array $header, iterable $rows): void {
  cpt_exporter_send_headers($filename, 'text/csv; charset=utf-8');
  $output = fopen('php://output', 'w');
  fwrite($output, "\xEF\xBB\xBF");
  fputcsv($output, array_map('cpt_exporter_csv_cell', $header), ';', '"', '', "\r\n");
  foreach ($rows as $row) {
    fputcsv($output, array_map('cpt_exporter_csv_cell', $row), ';', '"', '', "\r\n");
  }
  fclose($output);
}

// Drop invisible control characters (tab and line breaks are kept), then neutralise CSV injection (OWASP):
// a cell starting with = + - @, a tab or a carriage return would run as a formula, so it gets an apostrophe.
function cpt_exporter_csv_cell(string $value): string {
  $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
  return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}
