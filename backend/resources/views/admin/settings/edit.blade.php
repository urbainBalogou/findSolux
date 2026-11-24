@extends('admin.layouts.app')

@section('title', 'Paramètres')

@section('content')
    <header>
        <p class="text-sm font-bold uppercase tracking-[0.18em] text-amber-600">Demandes des clients</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Paramètres</h1>
        <p class="mt-3 max-w-3xl text-slate-600">Ces délais s’appliquent aux nouvelles demandes. Les demandes déjà envoyées gardent leur délai d’origine.</p>
    </header>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-8 grid gap-5 lg:grid-cols-2">
        @csrf
        @method('PUT')

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold">Signal de détresse</h2>
            <p class="mt-1 text-sm text-slate-600">Temps laissé aux garages de la zone pour accepter. Passé ce délai, le signal se termine et le client peut en envoyer un nouveau.</p>
            <label class="mt-5 grid gap-1.5 text-sm font-semibold text-slate-700">
                Délai d’attente (minutes)
                <input name="distress_signal_wait_minutes" type="number" required min="1" max="120" value="{{ old('distress_signal_wait_minutes', $distressSignalWaitMinutes) }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
            </label>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold">Demande de contact</h2>
            <p class="mt-1 text-sm text-slate-600">Temps laissé au garage pour accepter de partager son numéro avec un client qui l’a sollicité depuis sa fiche.</p>
            <label class="mt-5 grid gap-1.5 text-sm font-semibold text-slate-700">
                Délai de réponse (heures)
                <input name="contact_request_wait_hours" type="number" required min="1" max="168" value="{{ old('contact_request_wait_hours', $contactRequestWaitHours) }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
            </label>
        </section>

        <div class="lg:col-span-2">
            <button class="h-11 rounded-xl bg-slate-950 px-6 text-sm font-bold text-white transition hover:bg-slate-800">Enregistrer les paramètres</button>
        </div>
    </form>
@endsection
