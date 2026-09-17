<?php

if (!defined('ABSPATH')) exit;

// Build and send a valid XLSX (Office Open XML) with ZipArchive, without any library:
// one sheet, bold frozen header row, plain numbers as numbers and everything else as text.
function cpt_exporter_send_xlsx(string $filename, string $sheet_name, array $header, iterable $rows): void {
  if (!class_exists('ZipArchive')) {
    wp_die(esc_html__('XLSX export requires the PHP zip extension.', 'plugin-cpt-exporter'), 500);
  }
  // The sheet is written to disk row by row, so a large export never sits in memory.
  $sheet_path = wp_tempnam('cpt-exporter-sheet');
  $zip_path = wp_tempnam('cpt-exporter-xlsx');
  $sheet = fopen($sheet_path, 'w');
  if (!$sheet) {
    wp_delete_file($sheet_path);
    wp_delete_file($zip_path);
    wp_die(esc_html__('The XLSX file could not be created.', 'plugin-cpt-exporter'), 500);
  }
  fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
    . '<cols><col min="1" max="' . count($header) . '" width="30" customWidth="1"/></cols>'
    . '<sheetData>' . cpt_exporter_xlsx_row(1, $header, true));
  $number = 1;
  foreach ($rows as $row) {
    fwrite($sheet, cpt_exporter_xlsx_row(++$number, $row));
  }
  fwrite($sheet, '</sheetData></worksheet>');
  fclose($sheet);

  $zip = new ZipArchive();
  $opened = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true;
  if ($opened) {
    foreach (cpt_exporter_xlsx_parts($sheet_name) as $name => $xml) {
      $zip->addFromString($name, $xml);
    }
    $zip->addFile($sheet_path, 'xl/worksheets/sheet1.xml');
  }
  // The sheet is only read when the archive is closed: delete it afterwards.
  $built = $opened && $zip->close();
  wp_delete_file($sheet_path);
  if (!$built) {
    wp_delete_file($zip_path);
    wp_die(esc_html__('The XLSX file could not be created.', 'plugin-cpt-exporter'), 500);
  }

  cpt_exporter_send_headers($filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', filesize($zip_path));
  readfile($zip_path);
  wp_delete_file($zip_path);
}

// Package parts around the sheet: content types, relationships, workbook and the bold header style.
function cpt_exporter_xlsx_parts(string $sheet_name): array {
  $declaration = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
  // Excel refuses sheet names over 31 characters, containing [ ] : * ? / \ or wrapped in apostrophes.
  $sheet_name = trim(mb_substr(str_replace(['[', ']', ':', '*', '?', '/', '\\'], '', $sheet_name), 0, 31), " '") ?: 'Export';
  return [
    '[Content_Types].xml' => $declaration
      . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
      . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
      . '<Default Extension="xml" ContentType="application/xml"/>'
      . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
      . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
      . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
      . '</Types>',
    '_rels/.rels' => $declaration
      . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
      . '</Relationships>',
    'xl/workbook.xml' => $declaration
      . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
      . '<sheets><sheet name="' . cpt_exporter_xlsx_escape($sheet_name) . '" sheetId="1" r:id="rId1"/></sheets>'
      . '</workbook>',
    'xl/_rels/workbook.xml.rels' => $declaration
      . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
      . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
      . '</Relationships>',
    'xl/styles.xml' => $declaration
      . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
      . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font></fonts>'
      . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
      . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
      . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
      . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
      . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
      . '</styleSheet>',
  ];
}

// One sheet row. Plain numbers become number cells so they sort and sum; anything with a leading zero
// (phone numbers, postcodes), a trailing decimal zero or over 15 digits (Excel's precision) stays text.
function cpt_exporter_xlsx_row(int $number, array $values, bool $header = false): string {
  $cells = '';
  foreach (array_values($values) as $index => $value) {
    $reference = cpt_exporter_xlsx_column($index) . $number;
    if (!$header && preg_match('/^-?(0|[1-9]\d*)(\.\d*[1-9])?$/', $value) && strlen(preg_replace('/\D/', '', $value)) <= 15) {
      $cells .= '<c r="' . $reference . '"><v>' . $value . '</v></c>';
    } else {
      $cells .= '<c r="' . $reference . '" t="inlineStr"' . ($header ? ' s="1"' : '') . '><is><t xml:space="preserve">' . cpt_exporter_xlsx_escape($value) . '</t></is></c>';
    }
  }
  return '<row r="' . $number . '">' . $cells . '</row>';
}

// Column letters from a zero-based index: 0 => A, 25 => Z, 26 => AA.
function cpt_exporter_xlsx_column(int $index): string {
  $letters = '';
  for ($position = $index + 1; $position > 0; $position = intdiv($position - 1, 26)) {
    $letters = chr(65 + ($position - 1) % 26) . $letters;
  }
  return $letters;
}

// XML-safe text: invalid UTF-8 bytes become U+FFFD, the control characters XML 1.0 forbids are dropped,
// then the text is capped at Excel's limit of 32,767 characters per cell.
function cpt_exporter_xlsx_escape(string $value): string {
  $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', wp_check_invalid_utf8($value, true));
  return htmlspecialchars(mb_substr($value, 0, 32767), ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
