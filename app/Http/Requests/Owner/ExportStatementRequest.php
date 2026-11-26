<?php

namespace App\Http\Requests\Owner;

use Illuminate\Support\Facades\Gate;

class ExportStatementRequest extends StatementRequest
{
    /** Downloading a statement also needs the right to view that statement. */
    public function authorize(): bool
    {
        return Gate::allows('export-statements') && Gate::allows("view-{$this->route('ledger')}-statement");
    }
}
