<?php
/**
 * Excel書き出しライブラリ（SpreadsheetML形式 - ZipArchive不要）
 * Excel 2003以降で開ける .xls XML形式で出力
 * 使い方:
 *   $wb = new KarteXlsx();
 *   $wb->addSheet('シート名', [['列A','列B'], ['値1','値2']]);
 *   $wb->download('ファイル名.xls');
 */

class KarteXlsx {
    private array $sheets = [];

    public function addSheet(string $name, array $rows): void {
        $this->sheets[] = ['name' => $name, 'rows' => $rows];
    }

    private function esc(string $v): string {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public function download(string $filename): never {
        // 拡張子を.xlsに強制
        $filename = preg_replace('/\.xlsx?$/i', '', $filename) . '.xls';

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Cache-Control: max-age=0');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
           . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
           . ' xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";

        // スタイル定義（ヘッダー行を太字に）
        echo '<Styles>'
           . '<Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#DDE0EE" ss:Pattern="Solid"/></Style>'
           . '<Style ss:ID="Default"></Style>'
           . '</Styles>' . "\n";

        foreach ($this->sheets as $sheet) {
            echo '<Worksheet ss:Name="' . $this->esc($sheet['name']) . '">' . "\n";
            echo '<Table>' . "\n";

            foreach ($sheet['rows'] as $ri => $row) {
                echo '<Row>' . "\n";
                foreach ($row as $cell) {
                    $val = (string)($cell ?? '');
                    $style = $ri === 0 ? ' ss:StyleID="Header"' : '';
                    if ($val !== '' && is_numeric($val) && !preg_match('/^0\d/', $val)) {
                        echo '<Cell' . $style . '><Data ss:Type="Number">' . $this->esc($val) . '</Data></Cell>' . "\n";
                    } else {
                        echo '<Cell' . $style . '><Data ss:Type="String">' . $this->esc($val) . '</Data></Cell>' . "\n";
                    }
                }
                echo '</Row>' . "\n";
            }

            echo '</Table>' . "\n";
            echo '</Worksheet>' . "\n";
        }

        echo '</Workbook>' . "\n";
        exit;
    }
}
