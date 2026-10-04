<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['title'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Helvetica, sans-serif; font-size: 9px; color: #0f172a; margin: 0; }
        .head { border-bottom: 3px solid #1d4ed8; padding-bottom: 8px; margin-bottom: 10px; }
        .head h1 { font-size: 16px; margin: 0 0 2px; color: #1e3a8a; }
        .head p { margin: 1px 0; font-size: 9px; color: #475569; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1d4ed8; color: #fff; font-size: 8.5px; text-align: left; padding: 5px 6px; border: 1px solid #1e40af; }
        td { padding: 4px 6px; border: 1px solid #cbd5e1; vertical-align: top; font-size: 8.5px; word-wrap: break-word; }
        tr:nth-child(even) td { background: #f8fafc; }
        .meta { margin-top: 10px; font-size: 8.5px; color: #475569; }
        .empty { padding: 20px; text-align: center; color: #64748b; font-size: 10px; }
    </style>
</head>
<body>
    <div class="head">
        <h1>{{ $meta['title'] }}</h1>
        <p>{{ $meta['app'] }} — Diskominfosandi Kabupaten Barito Utara</p>
        <p>Periode: {{ $meta['period'] }} · Rentang: {{ $meta['range'] }} · Jumlah data: {{ $meta['count'] }} baris</p>
        <p>Dicetak: {{ $meta['generated_at'] }}</p>
    </div>

    @if(count($rows) === 0)
        <div class="empty">Tidak ada data pada rentang waktu ini.</div>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($headers as $h)
                        <th>{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="meta">Dokumen dihasilkan otomatis oleh sistem NOC — mohon diperiksa kembali sebelum digunakan sebagai dokumen resmi.</p>
</body>
</html>
