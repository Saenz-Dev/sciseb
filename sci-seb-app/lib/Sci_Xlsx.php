<?php
/**
 * Generador mínimo de .xlsx (un solo hoja, encabezado en negrita, columnas con ancho fijo, fila
 * congelada) sin ninguna librería — construye a mano las partes XML del formato OOXML y las
 * empaqueta con Sci_Zip. Usa "inline strings" (t="inlineStr") en vez de una tabla de cadenas
 * compartidas: más simple de generar, a costa de un archivo algo más pesado — aceptable para los
 * volúmenes de esta app (máximo 5000 filas por exportación).
 */
class Sci_Xlsx
{
    private static function escaparXml($s)
    {
        return str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), (string) $s);
    }

    private static function columna($indiceCero)
    {
        // Esta app solo tiene 26 columnas (A-Z); si alguna vez hubiera más, haría falta manejar
        // columnas de dos letras (AA, AB, ...), pero no es el caso aquí.
        return chr(65 + $indiceCero);
    }

    /**
     * @param array $encabezados lista de nombres de columna (fila 1, en negrita)
     * @param array $filas       array de arrays indexados, mismo orden/cantidad que $encabezados
     * @return string contenido binario del .xlsx
     */
    public static function generar($encabezados, $filas)
    {
        $numCols = count($encabezados);

        $filasXml = '';
        $filasXml .= '<row r="1">';
        foreach ($encabezados as $i => $texto) {
            $ref = self::columna($i) . '1';
            $filasXml .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t xml:space="preserve">'
                . self::escaparXml($texto) . '</t></is></c>';
        }
        $filasXml .= '</row>';

        $numFila = 2;
        foreach ($filas as $fila) {
            $filasXml .= '<row r="' . $numFila . '">';
            foreach ($fila as $i => $texto) {
                if ($i >= $numCols) break;
                $ref = self::columna($i) . $numFila;
                $filasXml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                    . self::escaparXml($texto) . '</t></is></c>';
            }
            $filasXml .= '</row>';
            $numFila++;
        }

        $cols = '<cols><col min="1" max="' . $numCols . '" width="18" customWidth="1"/></cols>';
        $sheet1 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $cols
            . '<sheetViews><sheetView workbookViewId="0">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>'
            . '<sheetData>' . $filasXml . '</sheetData>'
            . '</worksheet>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $relsRaiz = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Casos" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        $relsWorkbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';

        $zip = new Sci_Zip();
        $zip->agregar('[Content_Types].xml', $contentTypes);
        $zip->agregar('_rels/.rels', $relsRaiz);
        $zip->agregar('xl/workbook.xml', $workbook);
        $zip->agregar('xl/_rels/workbook.xml.rels', $relsWorkbook);
        $zip->agregar('xl/styles.xml', $styles);
        $zip->agregar('xl/worksheets/sheet1.xml', $sheet1);
        return $zip->construir();
    }
}
