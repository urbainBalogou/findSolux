<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Connexion — Administration MecaDispo</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-950 antialiased">
        <main class="grid min-h-screen place-items-center px-4 py-10">
            <div class="w-full max-w-md">
                <div class="mb-7 text-center text-white">
                    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-amber-400 text-xl font-black text-slate-950 shadow-xl shadow-amber-500/20">fS</span>
                    <h1 class="mt-5 text-2xl font-bold tracking-tight">Administration MecaDispo</h1>
                    <p class="mt-2 text-sm text-slate-400">Gérez les offres, les tarifs et la visibilité des garages.</p>
                </div>

                <form method="POST" action="{{ route('admin.login.store') }}" class="rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                    @csrf
                    <div class="grid gap-5">
                        <div>
                            <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Adresse e-mail</label>
                            <input id="email" name="email" type="email" autocomplete="email" required autofocus value="{{ old('email') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                            @error('email')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Mot de passe</label>
                            <input id="password" name="password" type="password" autocomplete="current-password" required class="w-full rounded-xl border border-slate-300 px-4 py-3 outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                            @error('password')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <label class="flex items-center gap-3 text-sm text-slate-600">
                            <input type="hidden" name="remember" value="0">
                            <input name="remember" type="checkbox" value="1" class="size-4 rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                            Rester connecté sur cet appareil
                        </label>

                        <button class="mt-1 rounded-xl bg-amber-400 px-5 py-3 font-bold text-slate-950 transition hover:bg-amber-300 focus:outline-none focus:ring-4 focus:ring-amber-200">Se connecter</button>
                    </div>
                </form>
            </div>
        </main>
    </body>
</html>
