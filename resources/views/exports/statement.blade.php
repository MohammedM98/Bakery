{{-- PDF template of a customer statement (rendered by mPDF, not a web page). --}}
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: plex; font-size: 9.5pt; color: #18202b; }
        h1 { font-size: 15pt; font-weight: bold; margin: 0 0 2mm; }
        .meta { width: 100%; margin-bottom: 5mm; border-collapse: collapse; }
        .meta td { padding: 0.6mm 0; vertical-align: top; }
        .meta .label { color: #6b7685; width: 24mm; }
        table.rows { width: 100%; border-collapse: collapse; }
        table.rows th { background: #f3f5f8; font-weight: bold; text-align: right; padding: 2mm 1.6mm; border-bottom: 0.3mm solid #dde3ea; }
        table.rows td { padding: 1.6mm; border-bottom: 0.2mm solid #e5e9ef; vertical-align: top; }
        table.rows tr.total td { background: #f3f5f8; font-weight: bold; }
        .num, table.rows th.num { text-align: left; white-space: nowrap; }
        .muted { color: #6b7685; }
    </style>
</head>
<body>
    <h1>{{ $header['الكشف'] }}</h1>
    <table class="meta">
        @foreach ($header as $label => $value)
            @continue($label === 'الكشف')
            <tr>
                <td class="label">{{ $label }}</td>
                <td>{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    <table class="rows">
        <thead>
            <tr>
                @foreach ($columns as $i => $column)
                    <th @class(['num' => in_array($i, $numeric, true)])>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr @class(['total' => $line['total']])>
                    @foreach ($line['cells'] as $i => $cell)
                        @if (in_array($i, $numeric, true))
                            {{-- mPDF ignores the isolate marks money() puts around a negative number: a bdo keeps "-50.00 شيكل" as on screen. --}}
                            @if (preg_match('/^\x{2066}(.+)\x{2069} (.+)$/u', $cell, $m))
                                <td class="num"><bdo dir="ltr">{{ $m[1] }}</bdo> {{ $m[2] }}</td>
                            @else
                                <td class="num">{{ $cell }}</td>
                            @endif
                        @else
                            <td>{!! nl2br(e($cell)) !!}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
