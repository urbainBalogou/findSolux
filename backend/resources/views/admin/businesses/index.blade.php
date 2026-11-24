@extends('admin.layouts.app')

@section('title', 'Garages et adhésions')

@section('content')
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-amber-600">Professionnels</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Garages et adhésions</h1>
            <p class="mt-3 max-w-3xl text-slate-600">Sans adhésion active, un garage est invisible et ne reçoit aucune demande. Une offre accordée ici n’est pas débitée du portefeuille (offre de lancement, geste commercial).</p>
        </div>
        <form method="GET" action="{{ route('admin.businesses.index') }}" class="flex gap-2">
            <input name="q" type="search" value="{{ $search }}" placeholder="Nom ou numéro" class="w-56 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
            <button class="rounded-xl bg-slate-950 px-4 text-sm font-bold text-white">Chercher</button>
        </form>
    </header>

    <section class="mt-8 grid gap-4">
        @forelse ($businesses as $business)
            @php
                $isMember = $business->hasActiveMembership();
            @endphp
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-slate-950">{{ $business->name }}</h2>
                        <p class="text-sm text-slate-500">{{ $business->district->name }} · {{ \App\Support\PhoneNumber::display($business->phone) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-bold">
                        <span @class([
                            'rounded-full px-3 py-1',
                            'bg-emerald-100 text-emerald-800' => $isMember,
                            'bg-slate-200 text-slate-600' => ! $isMember,
                        ])>
                            @if ($business->has_unlimited_membership)
                                Adhésion illimitée
                            @elseif ($isMember)
                                Adhésion jusqu’au {{ $business->membership_ends_at->timezone(config('findsolux.timezone'))->translatedFormat('d M Y') }}
                            @else
                                Sans adhésion · invisible
                            @endif
                        </span>
                        <span class="rounded-full bg-blue-100 px-3 py-1 text-blue-800">{{ $business->services_quota }} service{{ $business->services_quota > 1 ? 's' : '' }} publiable{{ $business->services_quota > 1 ? 's' : '' }}</span>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-900">Solde : {{ \App\Support\Price::format($business->owner->wallet?->balance_xof ?? 0) }}</span>
                    </div>
                </div>

                @if ($grantableOffers->isNotEmpty())
                    <form method="POST" action="{{ route('admin.businesses.grant', $business) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4" onsubmit="return confirm('Accorder cette offre gratuitement ?')">
                        @csrf
                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                            Accorder une offre
                            <select name="offer_id" required class="min-w-64 rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                                @foreach ($grantableOffers as $offer)
                                    <option value="{{ $offer->id }}">{{ $offer->name }} — {{ $offer->durationLabel() }}{{ $offer->is_active ? '' : ' (inactive)' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="h-11 rounded-xl bg-amber-400 px-5 text-sm font-bold text-slate-950 transition hover:bg-amber-300">Accorder</button>
                    </form>
                @endif
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 px-5 py-10 text-center">
                <p class="font-semibold text-slate-700">Aucun garage trouvé.</p>
            </div>
        @endforelse
    </section>

    <div class="mt-6">{{ $businesses->links() }}</div>
@endsection
