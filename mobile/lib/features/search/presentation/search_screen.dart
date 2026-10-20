import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';

import '../../../core/location/location_service.dart';
import '../../reference/data/reference_repository.dart';
import '../../reference/domain/reference_data.dart';
import '../application/search_controller.dart';
import '../domain/search_models.dart';
import '../../../core/formatting/distance_format.dart';
import '../../../core/maps/native_map.dart';
import 'widgets/garage_results_panel.dart';
import 'widgets/place_search_bar.dart';
import 'widgets/search_filters.dart';

/// Écran d'accueil (ADR-012) : carte des garages autour de la position du
/// client ou du lieu recherché, cartes des garages en bas, du plus proche au
/// plus éloigné.
class SearchScreen extends ConsumerStatefulWidget {
  const SearchScreen({super.key});

  @override
  ConsumerState<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends ConsumerState<SearchScreen> {
  static const _myPosition = 'Ma position';
  static const _lome = LatLng(6.1319, 1.2228);

  /// Déplacement de la carte au-delà duquel « Rechercher dans cette zone » apparaît.
  static const _searchHereMeters = 800.0;

  final _cards = PageController(viewportFraction: 0.88);
  GoogleMapController? _map;
  LatLng? _cameraTarget;
  var _selected = 0;
  var _showSearchHere = false;
  var _locationGranted = false;
  String? _notice;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _locateOnStart());
  }

  @override
  void dispose() {
    _cards.dispose();
    _map?.dispose();
    super.dispose();
  }

  /// Au premier affichage : position du téléphone, sinon centre de la ville.
  Future<void> _locateOnStart() async {
    if (ref.read(searchQueryProvider) != null) return;
    try {
      _onPosition(await ref.read(locationServiceProvider).currentPosition());
    } on LocationException catch (error) {
      final city = await _defaultCity();
      if (!mounted) return;
      setState(() => _notice = error.message);
      _moveTo(
        city?.latitude ?? _lome.latitude,
        city?.longitude ?? _lome.longitude,
        city?.name ?? 'Lomé',
      );
    }
  }

  Future<City?> _defaultCity() async {
    try {
      return (await ref.read(referenceDataProvider.future)).cities.firstOrNull;
    } on Object {
      return null;
    }
  }

  Future<void> _locateMe() async {
    try {
      _onPosition(await ref.read(locationServiceProvider).currentPosition());
    } on LocationException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  void _onPosition(LocationFix fix) {
    if (!mounted) return;
    setState(() {
      _locationGranted = true;
      _notice = null;
    });
    _moveTo(fix.latitude, fix.longitude, _myPosition);
  }

  void _moveTo(
    double latitude,
    double longitude,
    String label, {
    bool moveCamera = true,
  }) {
    ref
        .read(searchQueryProvider.notifier)
        .moveTo(latitude: latitude, longitude: longitude, label: label);
    setState(() => _showSearchHere = false);
    if (moveCamera) {
      _map?.animateCamera(
        CameraUpdate.newLatLngZoom(LatLng(latitude, longitude), 14),
      );
    }
  }

  void _onCameraIdle() {
    final query = ref.read(searchQueryProvider);
    final target = _cameraTarget;
    if (query == null || target == null) return;
    final moved =
        distanceInMeters(
          query.latitude,
          query.longitude,
          target.latitude,
          target.longitude,
        ) >
        _searchHereMeters;
    if (moved != _showSearchHere) setState(() => _showSearchHere = moved);
  }

  void _searchHere() {
    final target = _cameraTarget;
    if (target == null) return;
    _moveTo(
      target.latitude,
      target.longitude,
      'Zone de la carte',
      moveCamera: false,
    );
  }

  void _focusGarage(int index) {
    final garages = ref.read(garageResultsProvider).value ?? const [];
    if (index >= garages.length) return;
    setState(() => _selected = index);
    _map?.animateCamera(
      CameraUpdate.newLatLng(
        LatLng(garages[index].latitude, garages[index].longitude),
      ),
    );
  }

  Set<Marker> _markers(List<GarageResult> garages, SearchQuery? query) {
    return {
      for (final (index, garage) in garages.indexed)
        Marker(
          markerId: MarkerId('garage-${garage.id}'),
          position: LatLng(garage.latitude, garage.longitude),
          icon: index == _selected
              ? BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueAzure)
              : BitmapDescriptor.defaultMarker,
          zIndexInt: index == _selected ? 1 : 0,
          infoWindow: InfoWindow(
            title: garage.name,
            snippet: formatDistance(garage.distanceMeters),
          ),
          consumeTapEvents: true,
          onTap: () => _cards.animateToPage(
            index,
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeOut,
          ),
        ),
      // Lieu recherché (quartier, lieu Google…), distinct des garages.
      if (query != null && query.label != _myPosition)
        Marker(
          markerId: const MarkerId('search-center'),
          position: LatLng(query.latitude, query.longitude),
          icon: BitmapDescriptor.defaultMarkerWithHue(
            BitmapDescriptor.hueViolet,
          ),
          infoWindow: InfoWindow(title: query.label),
        ),
    };
  }

  @override
  Widget build(BuildContext context) {
    final query = ref.watch(searchQueryProvider);
    final garages = ref.watch(garageResultsProvider).value ?? const [];
    final bottomInset = GarageResultsPanel.height + 24;

    // Nouveaux résultats : le plus proche est sélectionné.
    ref.listen(garageResultsProvider, (previous, next) {
      if (next.value == null || identical(next.value, previous?.value)) return;
      setState(() => _selected = 0);
      if (_cards.hasClients) _cards.jumpToPage(0);
    });

    return Scaffold(
      body: Stack(
        children: [
          Positioned.fill(
            child: ref.watch(nativeMapEnabledProvider)
                ? GoogleMap(
                    initialCameraPosition: const CameraPosition(
                      target: _lome,
                      zoom: 13,
                    ),
                    onMapCreated: (controller) {
                      _map = controller;
                      if (query != null) {
                        controller.moveCamera(
                          CameraUpdate.newLatLngZoom(
                            LatLng(query.latitude, query.longitude),
                            14,
                          ),
                        );
                      }
                    },
                    markers: _markers(garages, query),
                    myLocationEnabled: _locationGranted,
                    myLocationButtonEnabled: false,
                    zoomControlsEnabled: false,
                    mapToolbarEnabled: false,
                    compassEnabled: false,
                    // Le logo Google et le centrage restent au-dessus des cartes.
                    padding: EdgeInsets.only(top: 120, bottom: bottomInset),
                    onCameraMove: (position) => _cameraTarget = position.target,
                    onCameraIdle: _onCameraIdle,
                  )
                : const ColoredBox(color: Color(0xFFE8EAE6)),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  PlaceSearchBar(
                    onSelected: (place) =>
                        _moveTo(place.latitude, place.longitude, place.name),
                  ),
                  const SizedBox(height: 8),
                  const SearchFilters(),
                  if (_notice != null)
                    Card(
                      margin: const EdgeInsets.only(top: 8),
                      child: ListTile(
                        dense: true,
                        leading: const Icon(Icons.location_off_outlined),
                        title: Text(_notice!),
                        trailing: IconButton(
                          icon: const Icon(Icons.close),
                          tooltip: 'Fermer',
                          onPressed: () => setState(() => _notice = null),
                        ),
                      ),
                    ),
                  if (_showSearchHere)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Center(
                        child: FilledButton.tonalIcon(
                          onPressed: _searchHere,
                          icon: const Icon(Icons.refresh),
                          label: const Text('Rechercher dans cette zone'),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
          Positioned(
            right: 12,
            bottom: bottomInset,
            child: FloatingActionButton.small(
              heroTag: 'ma-position',
              tooltip: _myPosition,
              onPressed: _locateMe,
              child: const Icon(Icons.my_location),
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 12,
            child: GarageResultsPanel(
              pageController: _cards,
              selectedIndex: _selected,
              onPageChanged: _focusGarage,
            ),
          ),
        ],
      ),
    );
  }
}
