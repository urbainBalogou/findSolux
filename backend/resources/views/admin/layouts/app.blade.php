<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Administration') — MecaDispo</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-950 antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
            <aside class="border-b border-slate-200 bg-slate-950 px-5 py-5 text-white lg:sticky lg:top-0 lg:h-screen lg:border-r lg:border-b-0 lg:px-6 lg:py-8">
                <div class="flex items-center justify-between gap-4 lg:block">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-xl bg-amber-400 font-black text-slate-950">fS</span>
                        <span>
                            <span class="block text-lg font-bold tracking-tight">MecaDispo</span>
                            <span class="block text-xs text-slate-400">Centre d’administration</span>
                        </span>
                    </a>

                    <form method="POST" action="{{ route('admin.logout') }}" class="lg:hidden">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-200">Quitter</button>
                    </form>
                </div>

                <nav class="mt-5 flex gap-2 overflow-x-auto pb-1 lg:mt-10 lg:flex-col lg:overflow-visible" aria-label="Navigation principale">
                    <a href="{{ route('admin.dashboard') }}" @class([
                        'whitespace-nowrap rounded-xl px-4 py-3 text-sm font-semibold transition',
                        'bg-amber-400 text-slate-950' => request()->routeIs('admin.dashboard'),
                        'text-slate-300 hover:bg-slate-900 hover:text-white' => ! request()->routeIs('admin.dashboard'),
                    ])>Vue d’ensemble</a>
                    <a href="{{ route('admin.commercial-offers.index') }}" @class([
                        'whitespace-nowrap rounded-xl px-4 py-3 text-sm font-semibold transition',
                        'bg-amber-400 text-slate-950' => request()->routeIs('admin.commercial-offers.*'),
                        'text-slate-300 hover:bg-slate-900 hover:text-white' => ! request()->routeIs('admin.commercial-offers.*'),
                    ])>Tarifs et offres</a>
                    <a href="{{ route('admin.business-promotions.index') }}" @class([
                        'whitespace-nowrap rounded-xl px-4 py-3 text-sm font-semibold transition',
                        'bg-amber-400 text-slate-950' => request()->routeIs('admin.business-promotions.*'),
                        'text-slate-300 hover:bg-slate-900 hover:text-white' => ! request()->routeIs('admin.business-promotions.*'),
                    ])>Garages en vedette</a>
                    <a href="{{ route('admin.businesses.index') }}" @class([
                        'whitespace-nowrap rounded-xl px-4 py-3 text-sm font-semibold transition',
                        'bg-amber-400 text-slate-950' => request()->routeIs('admin.businesses.*'),
                        'text-slate-300 hover:bg-slate-900 hover:text-white' => ! request()->routeIs('admin.businesses.*'),
                    ])>Garages et adhésions</a>
                    <a href="{{ route('admin.settings.edit') }}" @class([
                        'whitespace-nowrap rounded-xl px-4 py-3 text-sm font-semibold transition',
                        'bg-amber-400 text-slate-950' => request()->routeIs('admin.settings.*'),
                        'text-slate-300 hover:bg-slate-900 hover:text-white' => ! request()->routeIs('admin.settings.*'),
                    ])>Paramètres</a>
                </nav>

                <div class="absolute bottom-8 hidden w-[14rem] border-t border-slate-800 pt-5 lg:block">
                    <p class="truncate text-sm font-semibold">{{ auth('admin')->user()->name }}</p>
                    <p class="truncate text-xs text-slate-400">{{ auth('admin')->user()->email }}</p>
                    <form method="POST" action="{{ route('admin.logout') }}" class="mt-4">
                        @csrf
                        @method('DELETE')
                        <button class="text-sm font-semibold text-slate-300 transition hover:text-white">Se déconnecter</button>
                    </form>
                </div>
            </aside>

            <main class="min-w-0 px-4 py-7 sm:px-7 lg:px-10 lg:py-10">
                <div class="mx-auto max-w-7xl">
                    @if (session('success'))
                        <div class="mb-6 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">
                            <span class="grid size-6 place-items-center rounded-full bg-emerald-600 text-xs text-white">✓</span>
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-900" role="alert">
                            <p class="font-bold">Certaines informations sont à corriger.</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </body>
</html>
