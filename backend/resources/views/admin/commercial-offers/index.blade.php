@extends('admin.layouts.app')

@section('title', 'Tarifs et offres')

@section('content')
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-amber-600">Monétisation</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Tarifs et offres</h1>
            <p class="mt-3 max-w-3xl text-slate-600">Chaque offre possède son propre prix, sa durée et son état. Utilisez « Sans limite » pour une validité permanente.</p>
        </div>
        <span class="w-fit rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600">Montants en FCFA</span>
    </header>

    <div class="mt-9 grid gap-7">
        @foreach ($offerTypes as $type)
            @php($offers = $offersByType->get($type->value, collect()))
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-950 px-5 py-5 text-white sm:px-7">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-bold">{{ $type->label() }}</h2>
                            <p class="mt-1 text-sm text-slate-400">{{ $type->description() }}</p>
                        </div>
                        <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold">{{ $offers->count() }} offre{{ $offers->count() > 1 ? 's' : '' }}</span>
                    </div>
                </div>

                <div class="grid gap-4 p-4 sm:p-6">
                    @forelse ($offers as $offer)
                        <form method="POST" action="{{ route('admin.commercial-offers.update', $offer) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="type" value="{{ $type->value }}">

                            <div class="grid gap-4 xl:grid-cols-[minmax(12rem,1.5fr)_minmax(7rem,.7fr)_minmax(8rem,.8fr)_6rem]">
                                <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                    Nom de l’offre
                                    <input name="name" required maxlength="100" value="{{ $offer->name }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                                </label>
                                @if ($type->requiresQuantity())
                                    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                        Nombre de {{ $type->quantityLabel() }}
                                        <input name="quantity" type="number" required min="1" max="10000" value="{{ $offer->quantity }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                                    </label>
                                @endif
                                <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                    Prix (FCFA)
                                    <input name="price_xof" type="number" required min="0" max="100000000" value="{{ $offer->price_xof }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                                </label>
                                <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                    Ordre
                                    <input name="position" type="number" min="0" max="10000" value="{{ $offer->position }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                                </label>
                            </div>

                            <div class="mt-4 grid items-end gap-4 xl:grid-cols-[minmax(20rem,1fr)_auto_auto]">
                                <x-admin.duration-fields
                                    :id-prefix="'offer-'.$offer->id"
                                    :is-unlimited="$offer->is_unlimited"
                                    :duration-value="$offer->duration_value"
                                    :duration-unit="$offer->duration_unit?->value ?? 'month'"
                                />
                                <label class="flex h-11 items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700">
                                    <input type="hidden" name="is_active" value="0">
                                    <input name="is_active" type="checkbox" value="1" @checked($offer->is_active) class="size-4 rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                                    Active
                                </label>
                                <button class="h-11 rounded-xl bg-slate-950 px-5 text-sm font-bold text-white transition hover:bg-slate-800">Enregistrer</button>
                            </div>
                        </form>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 px-5 py-8 text-center text-sm text-slate-500">Aucune offre configurée pour le moment.</div>
                    @endforelse

                    <details class="group rounded-2xl border border-dashed border-amber-300 bg-amber-50/70">
                        <summary class="cursor-pointer list-none px-5 py-4 text-sm font-bold text-amber-900 marker:hidden">+ Ajouter une offre {{ mb_strtolower($type->label()) }}</summary>
                        <form method="POST" action="{{ route('admin.commercial-offers.store') }}" class="border-t border-amber-200 p-4 sm:p-5">
                            @csrf
                            <input type="hidden" name="type" value="{{ $type->value }}">

                            <div class="grid gap-4 xl:grid-cols-[minmax(12rem,1.5fr)_minmax(7rem,.7fr)_minmax(8rem,.8fr)_6rem]">
                                <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                    Nom de l’offre
                                    <input name="name" required maxlength="100" placeholder="Ex. Formule annuelle" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                                </label>
                                @if ($type->requiresQuantity())
                                    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                        Nombre de {{ $type->quantityLabel() }}
                                        <input name="quantity" type="number" required min="1" max="10000" value="1" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                                    </label>
                                @endif
                                <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                    Prix (FCFA)
                                    <input name="price_xof" type="number" required min="0" max="100000000" placeholder="5000" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                                </label>
                                <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                    Ordre
                                    <input name="position" type="number" min="0" max="10000" value="{{ $offers->count() + 1 }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                                </label>
                            </div>

                            <div class="mt-4 grid items-end gap-4 xl:grid-cols-[minmax(20rem,1fr)_auto_auto]">
                                <x-admin.duration-fields :id-prefix="'new-'.$type->value" />
                                <label class="flex h-11 items-center gap-3 rounded-xl border border-amber-200 bg-white px-4 text-sm font-semibold text-slate-700">
                                    <input type="hidden" name="is_active" value="0">
                                    <input name="is_active" type="checkbox" value="1" checked class="size-4 rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                                    Active
                                </label>
                                <button class="h-11 rounded-xl bg-amber-400 px-5 text-sm font-bold text-slate-950 transition hover:bg-amber-300">Créer l’offre</button>
                            </div>
                        </form>
                    </details>
                </div>
            </section>
        @endforeach

        {{-- Tarif unique, indépendant des offres souscrites : pas d'offre à créer. --}}
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-5 text-white sm:px-7">
                <h2 class="text-xl font-bold">Acceptation des demandes</h2>
                <p class="mt-1 text-sm text-slate-400">Prix unique prélevé sur le portefeuille du garage pour chaque signal de détresse ou demande de contact qu’il accepte, quelle que soit son offre. Une demande de contact d’un client déjà en contact avec le garage n’est pas facturée. À 0, l’acceptation est gratuite.</p>
            </div>

            <form method="POST" action="{{ route('admin.request-acceptance-price.update') }}" class="flex flex-wrap items-end gap-4 p-4 sm:p-6">
                @csrf
                @method('PUT')
                <label class="grid w-full gap-1.5 text-sm font-semibold text-slate-700 sm:w-72">
                    Prix d’une demande acceptée (FCFA)
                    <input name="request_acceptance_price_xof" type="number" required min="0" max="1000000" value="{{ old('request_acceptance_price_xof', $requestAcceptancePrice) }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                </label>
                <button class="h-11 rounded-xl bg-slate-950 px-5 text-sm font-bold text-white transition hover:bg-slate-800">Enregistrer</button>
            </form>
        </section>
    </div>
@endsection
