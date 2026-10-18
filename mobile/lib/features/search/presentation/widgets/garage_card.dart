import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../../../reference/domain/reference_data.dart';
import '../../domain/search_models.dart';
import '../distance_format.dart';

/// Carte d'un garage, en surimpression en bas de la carte. La distance est
/// affichée tout en bas.
class GarageCard extends StatelessWidget {
  const GarageCard({
    super.key,
    required this.garage,
    required this.reference,
    required this.selected,
    this.onTap,
  });

  final GarageResult garage;
  final ReferenceData? reference;
  final bool selected;
  final VoidCallback? onTap;

  String get _place => [
    ?reference?.districtById(garage.districtId)?.name,
    ?garage.landmark,
  ].join(' · ');

  String get _services {
    final names = [
      for (final id in garage.serviceIds) ?reference?.serviceById(id)?.name,
    ];
    if (names.length <= 2) return names.join(', ');
    return '${names.take(2).join(', ')} +${names.length - 2}';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Card(
      elevation: selected ? 6 : 2,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: selected
            ? BorderSide(color: colors.primary, width: 2)
            : BorderSide.none,
      ),
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _Thumbnail(url: garage.photoThumbnailUrl),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            garage.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          if (_place.isNotEmpty)
                            Text(
                              _place,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodySmall,
                            ),
                          const SizedBox(height: 6),
                          Wrap(
                            spacing: 6,
                            runSpacing: 4,
                            children: [
                              if (garage.is24h)
                                _Badge('24 h/24', colors.tertiary)
                              else if (garage.isOpenNow)
                                _Badge('Ouvert', colors.tertiary)
                              else if (garage.hasOpeningHours)
                                _Badge('Fermé', colors.outline),
                              if (garage.isMobile)
                                _Badge('Se déplace', colors.secondary),
                            ],
                          ),
                          const Spacer(),
                          if (_services.isNotEmpty)
                            Text(
                              _services,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodySmall,
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            ColoredBox(
              color: selected ? colors.primary : colors.primaryContainer,
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                child: Row(
                  children: [
                    Icon(
                      Icons.near_me,
                      size: 16,
                      color: selected
                          ? colors.onPrimary
                          : colors.onPrimaryContainer,
                    ),
                    const SizedBox(width: 6),
                    Text(
                      formatDistance(garage.distanceMeters),
                      style: theme.textTheme.labelLarge?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: selected
                            ? colors.onPrimary
                            : colors.onPrimaryContainer,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Thumbnail extends StatelessWidget {
  const _Thumbnail({required this.url});

  final String? url;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final placeholder = ColoredBox(
      color: colors.surfaceContainerHighest,
      child: Icon(Icons.car_repair, color: colors.onSurfaceVariant),
    );

    return ClipRRect(
      borderRadius: BorderRadius.circular(10),
      child: SizedBox.square(
        dimension: 64,
        child: url == null
            ? placeholder
            : CachedNetworkImage(
                imageUrl: url!,
                fit: BoxFit.cover,
                placeholder: (_, _) => placeholder,
                errorWidget: (_, _, _) => placeholder,
              ),
      ),
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge(this.label, this.color);

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        border: Border.all(color: color),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(color: color),
      ),
    );
  }
}
