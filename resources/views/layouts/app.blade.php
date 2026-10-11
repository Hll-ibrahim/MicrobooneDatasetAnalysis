<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Gösterge Paneli') · {{ config('app.name', 'MicroBooNE H5 Portalı') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    {{-- Yazı tipleri, Bootstrap 5.3, ikonlar ve Chart.js yerel kopyadan (public/vendor) — internet gerekmez --}}
    <link href="{{ asset('vendor/fonts/fonts.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/royal.css') }}" rel="stylesheet">
    @stack('head')
</head>
<body>
@php $childTables = \App\Support\DataTables::children(); @endphp
<nav class="navbar navbar-expand-lg navbar-dark navbar-royal sticky-top">
    <div class="container-xl">
        <a class="navbar-brand" href="{{ route('dashboard') }}">
            <span class="brand-mark">⚜</span> MicroBooNE <span class="brand-sub">H5 Portalı</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Menü">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('dashboard')) active @endif" href="{{ route('dashboard') }}">
                        <i class="bi bi-speedometer2"></i> Gösterge Paneli
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('files.*')) active @endif" href="{{ route('files.index') }}">
                        <i class="bi bi-file-earmark-binary"></i> H5 Dosyaları
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('events.*')) active @endif" href="{{ route('events.index') }}">
                        <i class="bi bi-bullseye"></i> Olaylar
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle @if(request()->routeIs('tables.*')) active @endif" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-table"></i> Tablolar
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">HDF5 grubu → PostgreSQL tablosu</h6></li>
                        <li>
                            <a class="dropdown-item" href="{{ route('events.index') }}">
                                <i class="bi bi-bullseye me-2 text-gold"></i>Olaylar <small class="text-muted font-mono">event_table</small>
                            </a>
                        </li>
                        @foreach($childTables as $t => $def)
                            <li>
                                <a class="dropdown-item @if(request()->route('table') === $t) active @endif" href="{{ route('tables.index', $t) }}">
                                    <i class="bi {{ $def['icon'] }} me-2 text-gold"></i>{{ $def['label'] }}
                                    <small class="text-muted font-mono">{{ $def['group'] }}</small>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('analysis')) active @endif" href="{{ route('analysis') }}">
                        <i class="bi bi-graph-up"></i> Grafikler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('glossary.*')) active @endif" href="{{ route('glossary.index') }}">
                        <i class="bi bi-journal-bookmark"></i> Sözlük
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<header class="page-header">
    <div class="container-xl">
        @yield('header')
    </div>
</header>

<main class="container-xl py-4">
    @include('partials.flash')
    @yield('content')
</main>

<footer class="footer-royal">
    <div class="container-xl d-flex flex-wrap justify-content-between gap-2">
        <span>⚜ MicroBooNE H5 Portalı · Laravel {{ app()->version() }} · PostgreSQL</span>
        <span>
            Veri: MicroBooNE İşbirliği,
            <a href="https://zenodo.org/records/7261921" target="_blank" rel="noopener">Zenodo 7261921</a> (CC BY 4.0)
        </span>
    </div>
</footer>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
<script src="{{ asset('js/royal-charts.js') }}"></script>
<script>
    // Silme formlarında onay sor
    document.addEventListener('submit', function (e) {
        const msg = e.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) e.preventDefault();
    });
    // Bootstrap ipuçları
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
</script>
@stack('scripts')
</body>
</html>
