@extends('admin.layouts.app')

@section('title', 'Vue d’ensemble')

@section('content')
    <header>
        <p class="text-sm font-bold uppercase tracking-[0.18em] text-amber-600">Pilotage</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Vue d’ensemble</h1>
        <p class="mt-3 max-w-2xl text-slate-600">Configurez la monétisation de MecaDispo et choisissez les garages à mettre en avant.</p>
    </header>

    <section class="mt-8 grid gap-4 sm:grid-cols-3" aria-label="Indicateurs">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">Garages visibles (adhésion active)</p>
            <p class="mt-3 text-3xl font-black">{{ number_format($visibleBusinessesCount, 0, ',', ' ') }} <span class="text-base font-semibold text-slate-400">/ {{ number_format($publishedBusinessesCount, 0, ',', ' ') }} inscrits</span></p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">Signaux de détresse acceptés (7 jours)</p>
            <p class="mt-3 text-3xl font-black">{{ number_format($acceptedDistressSignalsCount, 0, ',', ' ') }} <span class="text-base font-semibold text-slate-400">/ {{ number_format($distressSignalsCount, 0, ',', ' ') }}</span></p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">Demandes de contact acceptées (7 jours)</p>
            <p class="mt-3 text-3xl font-black">{{ number_format($acceptedContactRequestsCount, 0, ',', ' ') }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">Offres actives</p>
            <p class="mt-3 text-3xl font-black">{{ number_format($activeOffersCount, 0, ',', ' ') }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">Garages en vedette</p>
            <p class="mt-3 text-3xl font-black">{{ number_format($featuredBusinessesCount, 0, ',', ' ') }}</p>
        </article>
    </section>

    <section class="mt-9 grid gap-5 lg:grid-cols-2">
        <a href="{{ route('admin.commercial-offers.index') }}" class="group rounded-3xl bg-slate-950 p-7 text-white shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">
            <span class="inline-flex rounded-full bg-amber-400 px-3 py-1 text-xs font-bold uppercase tracking-wider text-slate-950">Tarification</span>
            <h2 class="mt-5 text-2xl font-bold">Prix et offres commerciales</h2>
            <p class="mt-2 max-w-md text-sm leading-6 text-slate-300">Adhésion, services, collaborateurs et coût d’acceptation des demandes, avec une durée libre ou illimitée.</p>
            <p class="mt-6 text-sm font-bold text-amber-300 group-hover:text-amber-200">Configurer les offres →</p>
        </a>

        <a href="{{ route('admin.business-promotions.index') }}" class="group rounded-3xl border border-amber-200 bg-amber-50 p-7 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
            <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-800 shadow-sm">Visibilité</span>
            <h2 class="mt-5 text-2xl font-bold text-slate-950">Garages en vedette</h2>
            <p class="mt-2 max-w-md text-sm leading-6 text-slate-600">Classez les garages, programmez leur début de mise en avant et choisissez leur durée.</p>
            <p class="mt-6 text-sm font-bold text-amber-800 group-hover:text-amber-700">Gérer le classement →</p>
        </a>
    </section>
@endsection
