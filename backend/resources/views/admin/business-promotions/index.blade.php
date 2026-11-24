@extends('admin.layouts.app')

@section('title', 'Garages en vedette')

@section('content')
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-amber-600">Visibilité</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Garages en vedette</h1>
            <p class="mt-3 max-w-3xl text-slate-600">Le rang le plus petit apparaît en premier. La priorité s’applique uniquement entre la date de début et la fin configurée.</p>
        </div>
        <span class="w-fit rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600">{{ $promotions->count() }} programmation{{ $promotions->count() > 1 ? 's' : '' }}</span>
    </header>

    <section class="mt-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-950 px-5 py-5 text-white sm:px-7">
            <h2 class="text-xl font-bold">Classement programmé</h2>
            <p class="mt-1 text-sm text-slate-400">Les programmations futures et expirées restent visibles ici, sans influencer la recherche.</p>
        </div>

        <div class="grid gap-4 p-4 sm:p-6">
            @forelse ($promotions as $promotion)
                @php
                    $isActive = $promotion->starts_at->isPast() && ($promotion->ends_at === null || $promotion->ends_at->isFuture());
                    $isFuture = $promotion->starts_at->isFuture();
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="grid size-11 place-items-center rounded-xl bg-amber-400 text-lg font-black text-slate-950">{{ $promotion->position }}</span>
                            <div>
                                <h3 class="font-bold text-slate-950">{{ $promotion->business->name }}</h3>
                                <p class="text-sm text-slate-500">{{ $promotion->business->district->name }}</p>
                            </div>
                        </div>
                        <span @class([
                            'rounded-full px-3 py-1 text-xs font-bold',
                            'bg-emerald-100 text-emerald-800' => $isActive,
                            'bg-blue-100 text-blue-800' => $isFuture,
                            'bg-slate-200 text-slate-600' => ! $isActive && ! $isFuture,
                        ])>{{ $isActive ? 'En cours' : ($isFuture ? 'À venir' : 'Expirée') }}</span>
                    </div>

                    <form method="POST" action="{{ route('admin.business-promotions.update', $promotion) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="business_id" value="{{ $promotion->business_id }}">

                        <div class="grid items-end gap-4 xl:grid-cols-[6rem_minmax(12rem,.8fr)_minmax(20rem,1fr)_auto]">
                            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                Rang
                                <input name="position" type="number" required min="1" max="10000" value="{{ $promotion->position }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                            </label>
                            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                Début (heure de Lomé)
                                <input name="starts_at" type="datetime-local" required value="{{ $promotion->starts_at->timezone(config('findsolux.timezone'))->format('Y-m-d\TH:i') }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                            </label>
                            <x-admin.duration-fields
                                :id-prefix="'promotion-'.$promotion->id"
                                :is-unlimited="$promotion->is_unlimited"
                                :duration-value="$promotion->duration_value"
                                :duration-unit="$promotion->duration_unit?->value ?? 'month'"
                            />
                            <button class="h-11 rounded-xl bg-slate-950 px-5 text-sm font-bold text-white transition hover:bg-slate-800">Enregistrer</button>
                        </div>
                    </form>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-3 text-xs text-slate-500">
                        <p>
                            {{ $promotion->starts_at->timezone(config('findsolux.timezone'))->translatedFormat('d M Y à H:i') }}
                            @if ($promotion->ends_at)
                                → {{ $promotion->ends_at->timezone(config('findsolux.timezone'))->translatedFormat('d M Y à H:i') }}
                            @else
                                · sans date de fin
                            @endif
                        </p>
                        <form method="POST" action="{{ route('admin.business-promotions.destroy', $promotion) }}" onsubmit="return confirm('Retirer ce garage de la mise en vedette ?')">
                            @csrf
                            @method('DELETE')
                            <button class="font-bold text-red-600 transition hover:text-red-800">Retirer de la vedette</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 px-5 py-10 text-center">
                    <p class="font-semibold text-slate-700">Aucun garage n’est encore mis en vedette.</p>
                    <p class="mt-1 text-sm text-slate-500">Ajoutez le premier garage avec le formulaire ci-dessous.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section class="mt-7 rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm sm:p-7">
        <div>
            <h2 class="text-xl font-bold text-slate-950">Ajouter un garage au classement</h2>
            <p class="mt-1 text-sm text-slate-600">Seuls les garages publiés et non encore programmés sont proposés.</p>
        </div>

        @if ($availableBusinesses->isEmpty())
            <p class="mt-5 rounded-2xl bg-white px-4 py-4 text-sm font-medium text-slate-600">Tous les garages publiés sont déjà programmés.</p>
        @else
            <form method="POST" action="{{ route('admin.business-promotions.store') }}" class="mt-5 grid items-end gap-4 xl:grid-cols-[minmax(14rem,1fr)_6rem_minmax(12rem,.8fr)_minmax(20rem,1fr)_auto]">
                @csrf
                <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                    Garage
                    <select name="business_id" required class="rounded-xl border border-amber-200 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                        <option value="">Choisir un garage</option>
                        @foreach ($availableBusinesses as $business)
                            <option value="{{ $business->id }}">{{ $business->name }} — {{ $business->district->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                    Rang
                    <input name="position" type="number" required min="1" max="10000" value="{{ $promotions->count() + 1 }}" class="rounded-xl border border-amber-200 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                </label>
                <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                    Début (heure de Lomé)
                    <input name="starts_at" type="datetime-local" required value="{{ $defaultStartsAt }}" class="rounded-xl border border-amber-200 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                </label>
                <x-admin.duration-fields id-prefix="new-promotion" />
                <button class="h-11 rounded-xl bg-amber-400 px-5 text-sm font-bold text-slate-950 transition hover:bg-amber-300">Mettre en vedette</button>
            </form>
        @endif
    </section>
@endsection
