<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 · {{ config('app.name', 'NOC Barito Utara') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased flex items-center justify-center p-6">
    <main class="text-center max-w-md">
        <p class="text-7xl font-black text-blue-700">404</p>
        <h1 class="mt-4 text-2xl font-bold">Halaman tidak ditemukan</h1>
        <p class="mt-2 text-slate-500">Halaman yang Anda tuju tidak tersedia atau sudah dipindahkan.</p>
        <div class="mt-8 flex gap-3 justify-center">
            <a href="{{ route('landing') }}" class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Ke Beranda</a>
            <a href="{{ route('lapor.index') }}" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold hover:bg-slate-100">Lapor Gangguan</a>
        </div>
    </main>
</body>
</html>
