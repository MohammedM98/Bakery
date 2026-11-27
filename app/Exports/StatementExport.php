<?php

namespace App\Exports;

use App\Models\Customer;
use App\Models\Movement;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * A customer statement as a PDF or Excel file: the same rows, columns, "رصيد سابق" and closing
 * balance as the page (both come from Customer::statement()). Flour balances in words, money with its sign.
 */
class StatementExport
{
    /** @var array{opening: string, closing: string, rows: array<int, array<string, mixed>>} */
    private array $statement;

    public function __construct(private Customer $customer, private string $ledger, private ?string $from, private ?string $to)
    {
        $this->statement = $customer->statement($ledger, $from, $to);
    }

    public function filename(string $extension): string
    {
        $type = $this->money() ? 'كشف-مالي' : 'كشف-طحين';
        $period = ($this->from ?? 'البداية').'_'.($this->to ?? local_today()->toDateString());

        return str_replace(['/', '\\', ' '], '-', "{$type}-{$this->customer->name}-{$period}.{$extension}");
    }

    /** @return array<string, string> */
    public function header(): array
    {
        $date = fn (string $d) => date('d/m/Y', strtotime($d));

        return [
            'المخبز' => $this->customer->bakery->name,
            'الزبون' => $this->customer->name,
            'رقم الجوال' => $this->customer->mobile_number,
            'الكشف' => $this->money() ? 'كشف الحساب المالي' : 'كشف الطحين',
            'الفترة' => match (true) {
                $this->from && $this->to => 'من '.$date($this->from).' إلى '.$date($this->to),
                (bool) $this->from => 'من '.$date($this->from),
                (bool) $this->to => 'حتى '.$date($this->to),
                default => 'كل الحركات',
            },
            'تاريخ الإصدار' => now(config('app.local_timezone'))->format('d/m/Y H:i'),
        ];
    }

    /** @return array<int, string> */
    public function columns(): array
    {
        $unit = $this->money() ? '' : ' (كغ)';

        return [
            'رقم الحركة', 'التاريخ', 'البيان', ...($this->money() ? ['التحويل'] : []),
            'عليه'.$unit, 'له'.$unit, 'الرصيد', 'سجّلها',
        ];
    }

    /**
     * Every line as text, as the page shows it, "رصيد سابق" first and the closing balance last.
     *
     * @return array<int, array{cells: array<int, string>, total: bool}>
     */
    public function lines(): array
    {
        $amount = fn (?string $v) => $v === null ? '' : ($this->money() ? money($v) : kg($v));
        $date = fn (?string $d) => $d ? date('d/m/Y', strtotime($d)) : '';
        $summary = fn (string $label, ?string $d, string $balance) => ['total' => true, 'cells' => [
            '', $date($d), $label, ...($this->money() ? [''] : []), '', '', $this->balance($balance), '',
        ]];

        return [
            $summary('رصيد سابق', $this->from, $this->statement['opening']),
            ...array_map(fn (array $r) => ['total' => false, 'cells' => [
                (string) $r['number'],
                $date($r['date']),
                $r['description'].($r['note'] ? "\n".$r['note'] : ''),
                ...($this->money() ? [($r['transfer'] ?? '').($r['transfer_reference'] ? "\nحوالة ".$r['transfer_reference'] : '')] : []),
                $amount($r['debit']),
                $amount($r['credit']),
                $this->balance($r['balance']),
                (string) $r['recorded_by'],
            ]], $this->statement['rows']),
            $summary('الرصيد الختامي', $this->to, $this->statement['closing']),
        ];
    }

    /** A4, right to left, IBM Plex Sans Arabic embedded (as in the app); mPDF shapes the Arabic letters (OTL). */
    public function pdf(): string
    {
        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new FontVariables)->getDefaults()['fontdata'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => 'rtl',
            'tempDir' => storage_path('framework/cache/mpdf'),
            'fontDir' => [...$fontDirs, resource_path('fonts')],
            'fontdata' => $fontData + [
                'plex' => ['R' => 'IBMPlexSansArabic-Regular.ttf', 'B' => 'IBMPlexSansArabic-SemiBold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75],
            ],
            'default_font' => 'plex',
            // Characters missing from the font (← in the transfer column) come from mPDF's bundled DejaVu.
            'useSubstitutions' => true,
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 14,
        ]);
        $mpdf->SetTitle($this->header()['الكشف'].' - '.$this->customer->name);
        $mpdf->setFooter('{PAGENO} / {nbpg}');
        $mpdf->WriteHTML(view('exports.statement', [
            'header' => $this->header(),
            'columns' => $this->columns(),
            'lines' => $this->lines(),
            'numeric' => $this->numericColumns(),
            'money' => $this->money(),
        ])->render());

        return $mpdf->Output('', 'S');
    }

    /** One right-to-left sheet: the header block, then the table. Amounts are numbers; flour balances are words. */
    public function xlsx(): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setRightToLeft(true);
        $sheet->setTitle($this->money() ? 'الحساب المالي' : 'الطحين');

        $row = 1;
        foreach ($this->header() as $label => $value) {
            $sheet->setCellValue([1, $row], $label);
            $sheet->setCellValueExplicit([2, $row], $value, DataType::TYPE_STRING);
            $sheet->getStyle([1, $row])->getFont()->setBold(true);
            $row++;
        }
        $row++;

        $sheet->fromArray($this->columns(), null, 'A'.$row);
        $last = count($this->columns());
        $sheet->getStyle([1, $row, $last, $row])->getFont()->setBold(true);
        $headerRow = $row++;

        $numeric = $this->numericColumns();
        $format = $this->money() ? '#,##0.00 "شيكل";-#,##0.00 "شيكل"' : '#,##0.00';
        foreach ($this->rawLines() as $line) {
            foreach ($line['cells'] as $i => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                if (is_int($value)) {
                    $sheet->setCellValue([$i + 1, $row], $value);
                } elseif (in_array($i, $numeric, true) && is_numeric($value)) {
                    $sheet->setCellValue([$i + 1, $row], (float) $value);
                    $sheet->getStyle([$i + 1, $row])->getNumberFormat()->setFormatCode($format);
                } else {
                    $sheet->setCellValueExplicit([$i + 1, $row], (string) $value, DataType::TYPE_STRING);
                }
            }
            if ($line['total']) {
                $sheet->getStyle([1, $row, $last, $row])->getFont()->setBold(true);
            }
            $row++;
        }

        $sheet->getStyle([1, $headerRow, $last, $row - 1])->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $widths = $this->money() ? [11, 12, 36, 28, 14, 14, 16, 16] : [11, 12, 36, 14, 14, 18, 16];
        foreach ($widths as $i => $width) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
        }

        ob_start();
        (new Xlsx($book))->save('php://output');

        return (string) ob_get_clean();
    }

    /** Like lines(), but debit, credit and money balances as plain numbers for the spreadsheet. */
    private function rawLines(): array
    {
        $lines = $this->lines();
        $shift = $this->money() ? 1 : 0;
        $values = [
            ['debit' => null, 'credit' => null, 'balance' => $this->statement['opening']],
            ...$this->statement['rows'],
            ['debit' => null, 'credit' => null, 'balance' => $this->statement['closing']],
        ];

        foreach ($lines as $i => &$line) {
            $line['cells'][3 + $shift] = $values[$i]['debit'];
            $line['cells'][4 + $shift] = $values[$i]['credit'];
            if ($this->money()) {
                $line['cells'][6] = $values[$i]['balance'];
            }
            if (! $line['total']) {
                $line['cells'][0] = (int) $line['cells'][0];
            }
        }

        return $lines;
    }

    /** Indexes of the amount columns (debit, credit, balance): aligned to the end in the PDF. */
    private function numericColumns(): array
    {
        $shift = $this->money() ? 1 : 0;

        return [3 + $shift, 4 + $shift, 5 + $shift];
    }

    private function balance(string $value): string
    {
        return $this->money() ? money($value) : flour_balance($value);
    }

    private function money(): bool
    {
        return $this->ledger === Movement::LEDGER_MONEY;
    }
}
