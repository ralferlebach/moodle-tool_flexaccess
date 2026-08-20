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
            get_string('batch:col_username', 'tool_flexaccess'),
            get_string('batch:col_password', 'tool_flexaccess'),
            get_string('batch:col_firstname', 'tool_flexaccess'),
            get_string('batch:col_lastname', 'tool_flexaccess'),
            get_string('batch:col_email', 'tool_flexaccess'),
            get_string('batch:col_profilefields', 'tool_flexaccess'),
            get_string('batch:col_newusername', 'tool_flexaccess'),
        ];
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
            get_string('batch:col_username', 'tool_flexaccess'),
            get_string('batch:col_password', 'tool_flexaccess'),
            get_string('batch:col_lastname', 'tool_flexaccess'),
            get_string('batch:col_firstname', 'tool_flexaccess'),
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
     * Build printable login cards (8 per A4 page): username, password, course URL and a QR code.
     *
     * @param \stdClass $batch Batch record.
     * @param array $credentials Map of username => plain password.
     * @param string $courseurl Absolute course/login URL for the QR code and caption.
     * @return string Binary PDF content.
     */
    public static function login_cards(\stdClass $batch, array $credentials, string $courseurl): string {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        ksort($credentials);

        $pdf = new \pdf('P', 'mm', 'A4');
        $pdf->SetCreator('FlexAccess');
        $pdf->SetTitle(self::batch_title($batch));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);

        // Grid: 2 columns x 4 rows = 8 cards per page.
        $cols = 2;
        $rows = 4;
        $perpage = $cols * $rows;
        $marginx = 10;
        $marginy = 10;
        $cardw = (210 - 2 * $marginx) / $cols;
        $cardh = (297 - 2 * $marginy) / $rows;
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
            $cx = $marginx + ($slot % $cols) * $cardw;
            $cy = $marginy + intdiv($slot, $cols) * $cardh;

            $pdf->RoundedRect($cx + 2, $cy + 2, $cardw - 4, $cardh - 4, 2, '1111', 'D');
            $pad = 6;
            $textx = $cx + $pad;
            $texty = $cy + $pad;

            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetXY($textx, $texty);
            $pdf->Cell($cardw - 2 * $pad, 6, self::batch_title($batch), 0, 2, 'L');

            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetXY($textx, $texty + 8);
            $pdf->Cell(20, 5, get_string('batch:col_username', 'tool_flexaccess') . ':', 0, 0, 'L');
            $pdf->SetFont('courier', 'B', 10);
            $pdf->Cell($cardw - 2 * $pad - 20, 5, (string) $username, 0, 2, 'L');

            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetXY($textx, $texty + 14);
            $pdf->Cell(20, 5, get_string('batch:col_password', 'tool_flexaccess') . ':', 0, 0, 'L');
            $pdf->SetFont('courier', 'B', 10);
            $pdf->Cell($cardw - 2 * $pad - 20, 5, (string) $password, 0, 2, 'L');

            if ($courseurl !== '') {
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetXY($textx, $texty + 21);
                $pdf->MultiCell($cardw - 2 * $pad - 26, 4, $courseurl, 0, 'L');
                // QR code bottom-right of the card.
                $qrsize = 24;
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
