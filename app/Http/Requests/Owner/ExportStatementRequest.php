<?php

namespace App\Http\Requests\Owner;

use Illuminate\Support\Facades\Gate;

class ExportStatementRequest extends StatementRequest
{
    public function authorize(): bool
    {
        return Gate::allows('export-statements');
    }
}
