import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/search_repository.dart';
import '../domain/search_models.dart';

final searchQueryProvider =
    NotifierProvider<SearchQueryController, SearchQuery?>(
      SearchQueryController.new,
    );

/// Recherche en cours : null tant qu'aucun point (position ou lieu) n'est
/// connu. Les filtres sont conservés quand le point change.
class SearchQueryController extends Notifier<SearchQuery?> {
  @override
  SearchQuery? build() => null;

  void moveTo({
    required double latitude,
    required double longitude,
    required String label,
  }) {
    final current = state;
    state = current == null
        ? SearchQuery(latitude: latitude, longitude: longitude, label: label)
        : current.copyWith(
            latitude: latitude,
            longitude: longitude,
            label: label,
            radiusKm: SearchQuery.defaultRadiusKm,
          );
  }

  /// Un seul type de véhicule à la fois ; null pour tous.
  void setVehicleType(int? vehicleTypeId) {
    state = state?.copyWith(vehicleTypeId: () => vehicleTypeId);
  }

  void setOpenNow(bool openNow) {
    state = state?.copyWith(openNow: openNow);
  }

  void widenRadius() {
    final wider = state?.widerRadiusKm;
    if (wider != null) state = state!.copyWith(radiusKm: wider);
  }
}

/// Garages de la recherche en cours. Pendant un nouveau chargement, les
/// résultats précédents restent affichés.
final garageResultsProvider = FutureProvider<List<GarageResult>>((ref) async {
  final query = ref.watch(searchQueryProvider);
  if (query == null) return const [];
  return ref.watch(searchRepositoryProvider).searchGarages(query);
});
