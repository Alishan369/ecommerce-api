<?php

namespace App\Reports;

use RuntimeException;
use ZipArchive;

/**
 * Minimal streaming .xlsx writer (one sheet, bold frozen header, numbers stored
 * as numbers). Rows are written to a temp file as they come, so large reports
 * never sit in memory. Needs PHP's zip extension.
 */
final class XlsxWriter
{
    /** @var resource */
    private $sheet;

    private string $sheetPath;

    private int $row = 0;

    /** @var list<int> widest value per column, for column widths */
    private array $widths = [];

    public function __construct(private readonly string $title = 'Report')
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP zip extension is required for Excel export.');
        }

        $this->sheetPath = tempnam(sys_get_temp_dir(), 'xlsx-sheet');
        $this->sheet = fopen($this->sheetPath, 'w');
    }

    /** @param  list<string>  $columns */
    public function header(array $columns): void
    {
        $this->write($columns, true);
    }

    /** @param  list<mixed>  $values */
    public function row(array $values): void
    {
        $this->write($values, false);
    }

    /** Finishes the workbook and returns the path of the .xlsx temp file (caller deletes it). */
    public function finish(): string
    {
        fclose($this->sheet);
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';

        $cols = '';
        foreach ($this->widths as $i => $width) {
            $cols .= sprintf('<col min="%1$d" max="%1$d" width="%2$d" customWidth="1"/>', $i + 1, min(max($width + 2, 9), 60));
        }

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->escape(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $this->title), 0, 31)).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        // Style 1 = bold header on a light fill.
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF2E8D8"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="2"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .($cols ? "<cols>{$cols}</cols>" : '').'<sheetData>'
            .file_get_contents($this->sheetPath)
            .'</sheetData></worksheet>');
        $zip->close();
        @unlink($this->sheetPath);

        return $path;
    }

    private function write(array $values, bool $header): void
    {
        $this->row++;
        $cells = '';

        foreach (array_values($values) as $i => $value) {
            $ref = $this->column($i).$this->row;
            $text = (string) ($value ?? '');
            $this->widths[$i] = max($this->widths[$i] ?? 0, mb_strlen($text));

            if (! $header && (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) && ! preg_match('/^0\d/', $value)))) {
                $cells .= "<c r=\"{$ref}\"><v>{$text}</v></c>";
            } elseif ($text !== '') {
                $style = $header ? ' s="1"' : '';
                $cells .= "<c r=\"{$ref}\" t=\"inlineStr\"{$style}><is><t xml:space=\"preserve\">{$this->escape($text)}</t></is></c>";
            }
        }

        fwrite($this->sheet, "<row r=\"{$this->row}\">{$cells}</row>");
    }

    private function column(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26).$name;
        }

        return $name;
    }

    private function escape(string $value): string
    {
        // Strip characters XML 1.0 can't hold, then escape.
        return htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
