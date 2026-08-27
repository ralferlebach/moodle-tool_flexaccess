<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_flexaccess\local;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders batch credentials as an XLSX workbook, a printable PDF list, and printable login cards.
 *
 * All renderers take a $credentials map (username => plain password), alphabetically keyed, so the
 * caller controls when plain passwords are materialised (never persisted).
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class batch_export {
    /** Margin to the page edge in millimetres; the gap between two cards is twice this value. */
    private const CARD_MARGIN = 8.0;
    /**
     * Build the XLSX bytes: username, password, (empty) first name, last name, email, profile fields.
     *
     * @param \stdClass $batch Batch record.
     * @param array $credentials Map of username => plain password.
     * @return string Binary XLSX content.
     */
    public static function excel(\stdClass $batch, array $credentials): string {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('FlexAccess');
        $headers = [
            get_string('batchcol_username', 'tool_flexaccess'),
            get_string('batchcol_password', 'tool_flexaccess'),
            get_string('batchcol_firstname', 'tool_flexaccess'),
            get_string('batchcol_lastname', 'tool_flexaccess'),
            get_string('batchcol_email', 'tool_flexaccess'),
            get_string('batchcol_profilefields', 'tool_flexaccess'),
            get_string('batchcol_newusername', 'tool_flexaccess'),
        ];
        // Machine-readable schema marker: the header labels are localised, so the importer must not
        // have to guess the language. Written outside the data columns (A-G).
        $sheet->setCellValueExplicit(
            'H1',
            batch_import::SCHEMA_VERSION,
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );
        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueExplicit(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1',
                $h,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $col++;
        }
        $row = 2;
        ksort($credentials);
        foreach ($credentials as $username => $password) {
            $sheet->setCellValueExplicit(
                'A' . $row,
                (string) $username,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $sheet->setCellValueExplicit(
                'B' . $row,
                (string) $password,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $row++;
        }
        foreach (range('A', 'G') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return (string) ob_get_clean();
    }

    /**
     * Build a printable PDF list: username (alphabetical), password, and blank Name / First-name
     * columns to fill by hand.
     *
     * @param \stdClass $batch Batch record.
     * @param array $credentials Map of username => plain password.
     * @return string Binary PDF content.
     */
    public static function pdf_list(\stdClass $batch, array $credentials): string {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        ksort($credentials);

        $pdf = new \pdf('P', 'mm', 'A4');
        $pdf->SetCreator('FlexAccess');
        $pdf->SetTitle(self::batch_title($batch));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, self::batch_title($batch), 0, 1, 'L');
        $pdf->Ln(2);

        $headers = [
            get_string('batchcol_username', 'tool_flexaccess'),
            get_string('batchcol_password', 'tool_flexaccess'),
            get_string('batchcol_lastname', 'tool_flexaccess'),
            get_string('batchcol_firstname', 'tool_flexaccess'),
        ];
        $widths = [55, 45, 45, 45];
        $pdf->SetFont('helvetica', 'B', 10);
        foreach ($headers as $i => $h) {
            $pdf->Cell($widths[$i], 8, $h, 1, 0, 'L');
        }
        $pdf->Ln();
        $pdf->SetFont('courier', '', 10);
        foreach ($credentials as $username => $password) {
            $pdf->Cell($widths[0], 8, (string) $username, 1, 0, 'L');
            $pdf->Cell($widths[1], 8, (string) $password, 1, 0, 'L');
            $pdf->Cell($widths[2], 8, '', 1, 0, 'L');
            $pdf->Cell($widths[3], 8, '', 1, 0, 'L');
            $pdf->Ln();
        }
        return $pdf->Output('', 'S');
    }

    /**
     * Render printable login cards, laid out so the sheet can be cut into equal cards.
     *
     * The grid is built around the cut lines: the gap between two cards is twice the page margin,
     * so halving the sheet and halving again leaves every card with the same border on all four
     * sides. Text areas are stacked without overlap, and the free-text area on the left of each
     * card carries whatever the requester supplied (a short link, a contact, ...).
     *
     * @param \stdClass $batch Batch record.
     * @param array $credentials username => plain password.
     * @param string $courseurl Course URL printed and encoded as a QR code.
     * @param string $freetext Optional text supplied with the batch request.
     * @return string PDF document.
     */
    public static function login_cards(
        \stdClass $batch,
        array $credentials,
        string $courseurl,
        string $freetext = ''
    ): string {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        ksort($credentials);

        $pdf = new \pdf('P', 'mm', 'A4');
        $pdf->SetCreator('FlexAccess');
        $pdf->SetTitle(self::batch_title($batch));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);

        $pagew = 210.0;
        $pageh = 297.0;
        $cols = 2;
        $rows = 4;
        $perpage = $cols * $rows;

        // Margin to the page edge; the gap between two cards is twice that, so a cut down the
        // middle leaves both halves with exactly this margin again.
        $margin = self::CARD_MARGIN;
        $gap = 2 * $margin;
        $cardw = ($pagew - 2 * $margin - ($cols - 1) * $gap) / $cols;
        $cardh = ($pageh - 2 * $margin - ($rows - 1) * $gap) / $rows;

        $qrstyle = [
            'border' => false,
            'padding' => 0,
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
        ];

        $i = 0;
        foreach ($credentials as $username => $password) {
            if ($i % $perpage === 0) {
                $pdf->AddPage();
            }
            $slot = $i % $perpage;
            $cx = $margin + ($slot % $cols) * ($cardw + $gap);
            $cy = $margin + intdiv($slot, $cols) * ($cardh + $gap);

            $pdf->RoundedRect($cx, $cy, $cardw, $cardh, 2, '1111', 'D');

            $pad = 5.0;
            $innerw = $cardw - 2 * $pad;
            $qrsize = 22.0;
            // The QR code sits bottom right; the text column keeps clear of it so nothing overlaps.
            $textw = $innerw - $qrsize - 3;
            $x = $cx + $pad;
            $y = $cy + $pad;

            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY($x, $y);
            $pdf->Cell($innerw, 5, self::batch_title($batch), 0, 0, 'L');
            $y += 7;

            // Label above the value rather than beside it: a long username then has the full width
            // and can never run into the value next to it.
            foreach (
                [
                    [get_string('batchcol_username', 'tool_flexaccess'), (string) $username],
                    [get_string('batchcol_password', 'tool_flexaccess'), (string) $password],
                ] as [$label, $value]
            ) {
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetXY($x, $y);
                $pdf->Cell($textw, 3.5, $label, 0, 0, 'L');
                $y += 3.5;
                $pdf->SetFont('courier', 'B', 11);
                $pdf->SetXY($x, $y);
                $pdf->Cell($textw, 5, $value, 0, 0, 'L');
                $y += 6.5;
            }

            if ($courseurl !== '') {
                $pdf->SetFont('helvetica', '', 6.5);
                $pdf->SetXY($x, $y);
                $pdf->MultiCell($textw, 3, $courseurl, 0, 'L');
                $y = $pdf->GetY() + 1;
            }

            if (trim($freetext) !== '') {
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetXY($x, $y);
                // Bounded to the space left above the card edge, so it cannot spill over.
                $available = ($cy + $cardh - $pad) - $y;
                $pdf->MultiCell(
                    $textw,
                    3.2,
                    $freetext,
                    0,
                    'L',
                    false,
                    1,
                    '',
                    '',
                    true,
                    0,
                    false,
                    true,
                    $available,
                    'T'
                );
            }

            if ($courseurl !== '') {
                $pdf->write2DBarcode(
                    $courseurl,
                    'QRCODE,M',
                    $cx + $cardw - $pad - $qrsize,
                    $cy + $cardh - $pad - $qrsize,
                    $qrsize,
                    $qrsize,
                    $qrstyle
                );
            }
            $i++;
        }
        if ($i === 0) {
            $pdf->AddPage();
        }
        return $pdf->Output('', 'S');
    }

    /**
     * A human title for a batch (its name and course).
     *
     * @param \stdClass $batch Batch record.
     * @return string
     */
    private static function batch_title(\stdClass $batch): string {
        return format_string($batch->name);
    }
}
