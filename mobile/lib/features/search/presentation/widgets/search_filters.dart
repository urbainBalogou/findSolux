import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../reference/data/reference_repository.dart';
import '../../application/search_controller.dart';

/// Filtres rapides (C2) : ouvert maintenant, type de véhicule.
class SearchFilters extends ConsumerWidget {
  const SearchFilters({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final query = ref.watch(searchQueryProvider);
    final vehicleTypes =
        ref.watch(referenceDataProvider).value?.vehicleTypes ?? [];
    final controller = ref.read(searchQueryProvider.notifier);
    final background = Theme.of(context).colorScheme.surface;

    if (query == null) return const SizedBox.shrink();

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          FilterChip(
            label: const Text('Ouvert maintenant'),
            selected: query.openNow,
            onSelected: controller.setOpenNow,
            backgroundColor: background,
            elevation: 2,
          ),
          for (final type in vehicleTypes) ...[
            const SizedBox(width: 8),
            ChoiceChip(
              label: Text(type.name),
              selected: query.vehicleTypeId == type.id,
              onSelected: (selected) =>
                  controller.setVehicleType(selected ? type.id : null),
              backgroundColor: background,
              elevation: 2,
            ),
          ],
        ],
      ),
    );
  }
}
