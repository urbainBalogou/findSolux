@php
    use App\Support\Price;
    use Illuminate\Support\Str;

    $place = $business->district->name.', '.$business->city->name;
    $summary = Str::limit($business->description ?: "Garage à {$place}. Appelez ou écrivez sur WhatsApp en un clic.", 155);
    $pageUrl = route('garages.show', $business->slug);
    $days = [1 => 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $photo = $business->photos->first();
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $business->name }} — garage à {{ $place }} | findSolux</title>
    <meta name="description" content="{{ $summary }}">
    <link rel="canonical" href="{{ $pageUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:title" content="{{ $business->name }} — {{ $place }}">
    <meta property="og:description" content="{{ $summary }}">
    <meta property="og:url" content="{{ $pageUrl }}">
    @if ($photo)
        <meta property="og:image" content="{{ $photo->url() }}">
    @endif
    <style>
        :root { --primary: #1565c0; --text: #1b1b1f; --muted: #5f6368; --line: #e3e3e8; --open: #1e7d32; --bg: #f6f7fb; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--text); background: var(--bg); }
        main { max-width: 640px; margin: 0 auto; padding: 16px; background: #fff; min-height: 100vh; }
        .cover { display: block; width: 100%; height: auto; max-height: 280px; object-fit: cover; border-radius: 12px; }
        h1 { font-size: 1.5rem; margin: 16px 0 4px; }
        h2 { font-size: 1.05rem; margin: 24px 0 8px; }
        .place { color: var(--muted); margin: 0; }
        .badges { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0; padding: 0; }
        .badge { border: 1px solid var(--line); border-radius: 6px; padding: 2px 8px; font-size: .85rem; }
        .badge.open { border-color: var(--open); color: var(--open); }
        .badge.closed { color: var(--muted); }
        .actions { display: grid; gap: 10px; margin: 16px 0; }
        .btn { display: block; text-align: center; padding: 14px; border-radius: 12px; font-weight: 600; text-decoration: none; border: 2px solid var(--primary); color: var(--primary); }
        .btn.call { background: var(--primary); color: #fff; }
        .btn.whatsapp { background: #25d366; border-color: #25d366; color: #0b3d1e; }
        ul.services { list-style: none; margin: 0; padding: 0; }
        ul.services li { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--line); }
        ul.services li span:last-child { color: var(--muted); text-align: right; white-space: nowrap; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 6px 0; border-bottom: 1px solid var(--line); }
        td:last-child { text-align: right; color: var(--muted); }
        tr.today td { font-weight: 600; color: var(--text); }
        footer { margin-top: 32px; color: var(--muted); font-size: .9rem; }
        footer a { color: var(--primary); }
    </style>
</head>
<body>
<main>
    @if ($photo)
        <img class="cover" src="{{ $photo->url() }}" width="{{ $photo->width }}" height="{{ $photo->height }}" alt="Atelier de {{ $business->name }}">
    @endif

    <h1>{{ $business->name }}</h1>
    <p class="place">{{ $place }}@if ($business->landmark) · {{ $business->landmark }}@endif</p>

    <p class="badges">
        @if ($business->is_24_7)
            <span class="badge open">Dépannage 24 h/24</span>
        @elseif ($business->openingHours->isNotEmpty())
            <span class="badge {{ $isOpen ? 'open' : 'closed' }}">{{ $isOpen ? 'Ouvert maintenant' : 'Fermé en ce moment' }}</span>
        @endif
        @if ($business->is_mobile)
            <span class="badge">Se déplace{{ $business->service_radius_km ? " jusqu’à {$business->service_radius_km} km" : '' }}</span>
        @endif
    </p>

    <div class="actions">
        <a class="btn call" href="tel:{{ $business->phone }}" data-event="call">Appeler · {{ $phoneDisplay }}</a>
        @if ($whatsappUrl)
            <a class="btn whatsapp" href="{{ $whatsappUrl }}" data-event="whatsapp">Écrire sur WhatsApp</a>
        @endif
        <a class="btn" href="{{ $directionsUrl }}" data-event="directions">Itinéraire</a>
    </div>

    @if ($business->description)
        <h2>À propos</h2>
        <p>{{ $business->description }}</p>
    @endif

    @if ($business->services->isNotEmpty())
        <h2>Services et prix indicatifs</h2>
        <ul class="services">
            @foreach ($business->services as $item)
                <li>
                    <span>{{ $item->service->name }}@if ($item->vehicleType) · {{ $item->vehicleType->name }}@endif</span>
                    <span>{{ Price::range($item->price_min, $item->price_max) }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <h2>Horaires</h2>
    @if ($business->is_24_7)
        <p>Ouvert 24 h/24, 7 j/7.</p>
    @elseif ($business->openingHours->isEmpty())
        <p>Horaires non renseignés : appelez avant de passer.</p>
    @else
        <table>
            @foreach ($days as $day => $name)
                <tr @class(['today' => $day === $today])>
                    <td>{{ $name }}</td>
                    <td>{{ $hoursByDay[$day] ? implode(', ', $hoursByDay[$day]) : 'Fermé' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($business->vehicleTypes->isNotEmpty())
        <h2>Véhicules réparés</h2>
        <p>{{ $business->vehicleTypes->pluck('name')->join(', ') }}</p>
    @endif

    <footer>
        Fiche publiée sur findSolux, l’annuaire des garages de proximité.
        @if ($downloadUrl)
            <a href="{{ $downloadUrl }}">Installez l’application</a> pour trouver un garage près de chez vous.
        @endif
    </footer>
</main>
<script>
    // Vues et clics comptés pour les statistiques du garagiste : un appareil
    // compte une fois par jour et par action.
    (function () {
        var id;
        try {
            id = localStorage.getItem('findsolux-device');
            if (!id) {
                id = window.crypto && crypto.randomUUID ? crypto.randomUUID() : 'web-' + Date.now() + Math.random().toString(16).slice(2);
                localStorage.setItem('findsolux-device', id);
            }
        } catch (e) {
            id = 'web-' + Date.now();
        }
        function send(type) {
            try {
                fetch(@json(url('/api/v1/businesses/'.$business->slug.'/events')), {
                    method: 'POST',
                    keepalive: true,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ type: type, device_id: id })
                });
            } catch (e) {}
        }
        send('view');
        document.querySelectorAll('[data-event]').forEach(function (link) {
            link.addEventListener('click', function () { send(link.getAttribute('data-event')); });
        });
    })();
</script>
</body>
</html>
