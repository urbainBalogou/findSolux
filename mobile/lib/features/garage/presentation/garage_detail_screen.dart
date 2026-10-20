import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/formatting/distance_format.dart';
import '../../../core/formatting/formatters.dart';
import '../../../core/widgets/async_view.dart';
import '../../reference/data/reference_repository.dart';
import '../../reference/domain/reference_data.dart';
import '../../search/application/search_controller.dart';
import '../application/contact_launcher.dart';
import '../data/garage_repository.dart';
import '../domain/public_business.dart';
import 'widgets/garage_badges.dart';

/// Fiche publique d'un garage (C3) et contact direct (C4) : depuis la carte
/// des résultats, deux appuis suffisent pour appeler.
class GarageDetailScreen extends ConsumerStatefulWidget {
  const GarageDetailScreen({super.key, required this.slug});

  final String slug;

  @override
  ConsumerState<GarageDetailScreen> createState() => _GarageDetailScreenState();
}

class _GarageDetailScreenState extends ConsumerState<GarageDetailScreen> {
  @override
  void initState() {
    super.initState();
    ref.read(garageTrackerProvider).track(widget.slug, GarageEvent.view);
  }

  Future<void> _contact(
    GarageEvent event,
    Future<bool> Function(ContactLauncher launcher) open,
    String failure,
  ) async {
    ref.read(garageTrackerProvider).track(widget.slug, event);
    final opened = await open(ref.read(contactLauncherProvider));
    if (!opened && mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(failure)));
    }
  }

  void _share(PublicBusiness garage, String? district) {
    _contact(
      GarageEvent.share,
      (launcher) => launcher.share(
        subject: garage.name,
        text: [
          district == null
              ? garage.name
              : '${garage.name} — garage à $district',
          garage.shareUrl,
        ].join('\n'),
      ),
      'Impossible de partager cette fiche.',
    );
  }

  @override
  Widget build(BuildContext context) {
    final detail = ref.watch(garageDetailProvider(widget.slug));
    final reference = ref.watch(referenceDataProvider).value;
    final garage = detail.value;
    final district = garage == null
        ? null
        : reference?.districtById(garage.districtId)?.name;

    return Scaffold(
      appBar: AppBar(
        title: Text(garage?.name ?? 'Garage'),
        actions: [
          if (garage != null)
            IconButton(
              icon: const Icon(Icons.share),
              tooltip: 'Partager',
              onPressed: () => _share(garage, district),
            ),
        ],
      ),
      body: AsyncView(
        value: detail,
        onRetry: () => ref.invalidate(garageDetailProvider(widget.slug)),
        builder: (garage) => _GarageDetail(
          garage: garage,
          reference: reference,
          onCall: () => _contact(
            GarageEvent.call,
            (launcher) => launcher.call(garage.phone),
            'Impossible d’ouvrir le téléphone.',
          ),
          onWhatsApp: garage.whatsappPhone == null
              ? null
              : () => _contact(
                  GarageEvent.whatsapp,
                  (launcher) => launcher.whatsApp(
                    garage.whatsappPhone!,
                    'Bonjour ${garage.name}, je vous ai trouvé sur findSolux.',
                  ),
                  'WhatsApp ne s’est pas ouvert. Est-il installé sur ce téléphone ?',
                ),
          onDirections: () => _contact(
            GarageEvent.directions,
            (launcher) =>
                launcher.directions(garage.latitude, garage.longitude),
            'Impossible d’ouvrir l’itinéraire.',
          ),
        ),
      ),
    );
  }
}

class _GarageDetail extends ConsumerWidget {
  const _GarageDetail({
    required this.garage,
    required this.reference,
    required this.onCall,
    required this.onWhatsApp,
    required this.onDirections,
  });

  final PublicBusiness garage;
  final ReferenceData? reference;
  final VoidCallback onCall;
  final VoidCallback? onWhatsApp;
  final VoidCallback onDirections;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final query = ref.watch(searchQueryProvider);
    final place = [
      ?reference?.districtById(garage.districtId)?.name,
      ?garage.landmark,
    ].join(' · ');
    final vehicles = [
      for (final id in garage.vehicleTypeIds)
        ?reference?.vehicleTypeById(id)?.name,
    ];

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
      children: [
        if (garage.photoUrls.isNotEmpty) ...[
          _Photos(urls: garage.photoUrls),
          const SizedBox(height: 16),
        ],
        Text(
          garage.name,
          style: theme.textTheme.headlineSmall?.copyWith(
            fontWeight: FontWeight.bold,
          ),
        ),
        if (place.isNotEmpty) Text(place, style: theme.textTheme.bodyLarge),
        const SizedBox(height: 8),
        GarageBadges(
          is24h: garage.is24h,
          isOpenNow: garage.isOpenNow,
          hasOpeningHours: garage.hasOpeningHours,
          isMobile: garage.isMobile,
        ),
        if (query != null) ...[
          const SizedBox(height: 8),
          Text(
            '${formatDistance(distanceInMeters(query.latitude, query.longitude, garage.latitude, garage.longitude).round())} '
            '${query.label == 'Ma position' ? 'de votre position' : 'de « ${query.label} »'}',
            style: theme.textTheme.bodyMedium,
          ),
        ],
        const SizedBox(height: 16),
        _ContactButtons(
          onCall: onCall,
          onWhatsApp: onWhatsApp,
          onDirections: onDirections,
        ),
        if (garage.description?.isNotEmpty == true)
          _Section(title: 'À propos', child: Text(garage.description!)),
        if (garage.services.isNotEmpty)
          _Section(
            title: 'Services et prix indicatifs',
            child: Column(
              children: [
                for (final item in garage.services)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    dense: true,
                    title: Text(
                      [
                        reference?.serviceById(item.serviceId)?.name ??
                            'Service',
                        if (item.vehicleTypeId != null)
                          ?reference
                              ?.vehicleTypeById(item.vehicleTypeId!)
                              ?.name,
                      ].join(' · '),
                    ),
                    trailing: Text(
                      item.hasPrice
                          ? formatPrice(item.priceMin, item.priceMax)
                          : 'Prix sur demande',
                    ),
                  ),
              ],
            ),
          ),
        _Section(
          title: 'Horaires',
          child: _OpeningHours(garage: garage),
        ),
        if (vehicles.isNotEmpty)
          _Section(
            title: 'Véhicules réparés',
            child: Text(vehicles.join(', ')),
          ),
        if (garage.isMobile)
          _Section(
            title: 'Intervention',
            child: Text(
              garage.serviceRadiusKm == null
                  ? 'Se déplace chez le client.'
                  : 'Se déplace chez le client, jusqu’à ${garage.serviceRadiusKm} km.',
            ),
          ),
      ],
    );
  }
}

class _ContactButtons extends StatelessWidget {
  const _ContactButtons({
    required this.onCall,
    required this.onWhatsApp,
    required this.onDirections,
  });

  final VoidCallback onCall;
  final VoidCallback? onWhatsApp;
  final VoidCallback onDirections;

  @override
  Widget build(BuildContext context) {
    const height = Size.fromHeight(52);
    return Row(
      children: [
        Expanded(
          child: FilledButton.icon(
            onPressed: onCall,
            icon: const Icon(Icons.call),
            label: const Text('Appeler'),
            style: FilledButton.styleFrom(minimumSize: height),
          ),
        ),
        if (onWhatsApp != null) ...[
          const SizedBox(width: 8),
          Expanded(
            child: FilledButton.tonalIcon(
              onPressed: onWhatsApp,
              icon: const Icon(Icons.chat),
              label: const Text('WhatsApp'),
              style: FilledButton.styleFrom(minimumSize: height),
            ),
          ),
        ],
        const SizedBox(width: 8),
        Expanded(
          child: OutlinedButton.icon(
            onPressed: onDirections,
            icon: const Icon(Icons.directions),
            label: const Text('Itinéraire'),
            style: OutlinedButton.styleFrom(minimumSize: height),
          ),
        ),
      ],
    );
  }
}

class _OpeningHours extends StatelessWidget {
  const _OpeningHours({required this.garage});

  final PublicBusiness garage;

  @override
  Widget build(BuildContext context) {
    if (garage.is24h) return const Text('Ouvert 24 h/24, 7 j/7.');
    if (!garage.hasOpeningHours) {
      return const Text('Horaires non renseignés : appelez avant de passer.');
    }

    // Lomé est à l'heure UTC, sans heure d'été.
    final today = DateTime.now().toUtc().weekday;
    return Column(
      children: [
        for (var day = 1; day <= 7; day++)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: DefaultTextStyle.merge(
              style: day == today
                  ? const TextStyle(fontWeight: FontWeight.bold)
                  : null,
              child: Row(
                children: [
                  Expanded(child: Text(dayNames[day - 1])),
                  Text(_rangesOf(day)),
                ],
              ),
            ),
          ),
      ],
    );
  }

  String _rangesOf(int day) {
    final ranges = [
      for (final range in garage.openingHours)
        if (range.day == day) '${range.opensAt}–${range.closesAt}',
    ];
    return ranges.isEmpty ? 'Fermé' : ranges.join(', ');
  }
}

class _Photos extends StatelessWidget {
  const _Photos({required this.urls});

  final List<String> urls;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: SizedBox(
        height: 200,
        child: PageView(
          children: [
            for (final url in urls)
              CachedNetworkImage(
                imageUrl: url,
                fit: BoxFit.cover,
                placeholder: (_, _) => ColoredBox(
                  color: Theme.of(context).colorScheme.surfaceContainerHighest,
                ),
                errorWidget: (_, _, _) =>
                    const Center(child: Icon(Icons.broken_image)),
              ),
          ],
        ),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          child,
        ],
      ),
    );
  }
}
