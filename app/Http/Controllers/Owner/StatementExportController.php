<?php

namespace App\Http\Controllers\Owner;

use App\Exports\StatementExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ExportStatementRequest;
use App\Models\Customer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Downloads a customer statement (money or flour) as PDF or Excel, for the period on screen. */
class StatementExportController extends Controller
{
    private const TYPES = [
        'pdf' => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __invoke(ExportStatementRequest $request, Customer $customer, string $ledger, string $format): StreamedResponse
    {
        abort_unless($customer->bakery_id === $request->user()->bakery_id, 404);

        ['from' => $from, 'to' => $to] = $request->range();
        $export = new StatementExport($customer, $ledger, $from, $to);
        $content = $format === 'pdf' ? $export->pdf() : $export->xlsx();

        return response()->streamDownload(fn () => print ($content), $export->filename($format), [
            'Content-Type' => self::TYPES[$format],
        ]);
    }
}
