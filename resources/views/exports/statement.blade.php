{{-- PDF template of a customer statement (rendered by mPDF, not a web page). --}}
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: cairo; font-size: 9.5pt; color: #3b2a1e; }
        h1 { font-size: 15pt; font-weight: bold; margin: 0 0 2mm; }
        .meta { width: 100%; margin-bottom: 5mm; border-collapse: collapse; }
        .meta td { padding: 0.6mm 0; vertical-align: top; }
        .meta .label { color: #7a6656; width: 24mm; }
        table.rows { width: 100%; border-collapse: collapse; }
        table.rows th { background: #f1e7d6; font-weight: bold; text-align: right; padding: 2mm 1.6mm; border-bottom: 0.3mm solid #c9b394; }
        table.rows td { padding: 1.6mm; border-bottom: 0.2mm solid #e6dccd; vertical-align: top; }
        table.rows tr.total td { background: #f7f1e7; font-weight: bold; }
        .num, table.rows th.num { text-align: left; white-space: nowrap; }
        .muted { color: #7a6656; }
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
                            {{-- Money: left-to-right isolate so it reads "-50.00 ₪" as on screen. Flour words stay right to left. --}}
                            <td class="num">{{ $money && $cell !== '' ? "\u{2066}{$cell}\u{2069}" : $cell }}</td>
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
