import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/app_router.dart';
import '../../auth/presentation/phone_format.dart';
import '../../reference/domain/reference_data.dart';
import '../domain/my_business.dart';
import 'formatters.dart';

/// Fiche inscrite : état, éléments à compléter et accès à chaque section.
class BusinessOverview extends StatelessWidget {
  const BusinessOverview({
    super.key,
    required this.business,
    required this.reference,
  });

  final MyBusiness business;
  final ReferenceData reference;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final district = reference.districtById(business.districtId)?.name;
    final missing = business.missingItems;
    final pricedServices = business.services.where((s) => s.hasPrice).length;
    final serviceCount = {
      for (final service in business.services) service.serviceId,
    }.length;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(business.name, style: theme.textTheme.headlineSmall),
        if (district != null) Text(district, style: theme.textTheme.bodyLarge),
        const SizedBox(height: 8),
        Align(
          alignment: Alignment.centerLeft,
          child: Chip(
            avatar: Icon(
              business.isPublished ? Icons.visibility : Icons.visibility_off,
              size: 18,
            ),
            label: Text(
              business.isPublished
                  ? 'Visible par les clients'
                  : 'Masquée par la modération',
            ),
          ),
        ),
        if (missing.isNotEmpty) ...[
          const SizedBox(height: 8),
          Card(
            color: theme.colorScheme.secondaryContainer,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Complétez votre fiche',
                    style: theme.textTheme.titleMedium,
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Les fiches complètes inspirent confiance et reçoivent '
                    'plus d’appels. Il manque :',
                  ),
                  const SizedBox(height: 8),
                  for (final item in missing) Text('•  $item'),
                ],
              ),
            ),
          ),
        ],
        const SizedBox(height: 8),
        _SectionTile(
          icon: Icons.info_outline,
          title: 'Informations',
          subtitle: [
            formatPhone(business.phone),
            if (business.isMobile) 'Se déplace',
            if (business.is24h) 'Dépannage 24 h/24',
          ].join(' · '),
          location: AppRoutes.proBusinessInfo,
        ),
        _SectionTile(
          icon: Icons.build_outlined,
          title: 'Services et prix',
          subtitle:
              '$serviceCount service${serviceCount > 1 ? 's' : ''} · '
              '$pricedServices avec prix',
          location: AppRoutes.proBusinessServices,
        ),
        _SectionTile(
          icon: Icons.schedule,
          title: 'Horaires',
          subtitle: formatOpeningHours(business.openingHours),
          location: AppRoutes.proBusinessHours,
        ),
        _SectionTile(
          icon: Icons.photo_library_outlined,
          title: 'Photos',
          subtitle: '${business.photos.length} / ${MyBusiness.maxPhotos}',
          location: AppRoutes.proBusinessPhotos,
        ),
      ],
    );
  }
}

class _SectionTile extends StatelessWidget {
  const _SectionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.location,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final String location;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: Icon(icon),
        title: Text(title),
        subtitle: Text(subtitle),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => context.go(location),
      ),
    );
  }
}
