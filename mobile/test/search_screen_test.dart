import 'package:findsolux/core/location/location_service.dart';
import 'package:findsolux/features/search/domain/search_models.dart';
import 'package:findsolux/features/search/presentation/distance_format.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers/test_app.dart';

void main() {
  testWidgets('les garages proches s’affichent, du plus proche au plus loin', (
    tester,
  ) async {
    final search = FakeSearchRepository(
      garages: [
        garageResult(id: 1, name: 'Garage Proche', distanceMeters: 300),
        garageResult(id: 2, name: 'Garage Loin', distanceMeters: 1240),
      ],
    );
    await pumpApp(tester, searchRepository: search);

    // Recherche lancée autour de la position du téléphone.
    expect(search.lastQuery?.label, 'Ma position');
    expect(search.lastQuery?.latitude, 6.2105);
    expect(search.lastQuery?.radiusKm, SearchQuery.defaultRadiusKm);

    expect(find.text('Garage Proche'), findsOneWidget);
    expect(find.text('À 300 m'), findsOneWidget);
    expect(find.text('À 1,2 km'), findsOneWidget);
    expect(
      tester.getTopLeft(find.text('Garage Proche')).dx,
      lessThan(tester.getTopLeft(find.text('Garage Loin')).dx),
    );
    // La distance est tout en bas de la carte.
    expect(
      tester.getTopLeft(find.text('À 300 m')).dy,
      greaterThan(tester.getTopLeft(find.text('Garage Proche')).dy),
    );
    expect(find.text('Adidogomé · Près du marché'), findsWidgets);
    expect(find.text('Ouvert'), findsWidgets);
    expect(find.text('Vidange et entretien, Freins +1'), findsWidgets);
  });

  testWidgets('sans horaires renseignés, ni « Ouvert » ni « Fermé »', (
    tester,
  ) async {
    await pumpApp(
      tester,
      searchRepository: FakeSearchRepository(
        garages: [garageResult(isOpenNow: false, hasOpeningHours: false)],
      ),
    );

    expect(find.text('Ouvert'), findsNothing);
    expect(find.text('Fermé'), findsNothing);
  });

  testWidgets('la recherche d’un lieu recentre la recherche', (tester) async {
    final search = FakeSearchRepository(
      places: const [
        PlaceResult(
          isDistrict: true,
          name: 'Adidogomé',
          address: 'Lomé',
          latitude: 6.2101,
          longitude: 1.1547,
        ),
      ],
    );
    await pumpApp(tester, searchRepository: search);

    await tester.enterText(find.byType(TextField), 'adidogome');
    await tester.testTextInput.receiveAction(TextInputAction.search);
    await tester.pumpAndSettle();

    expect(search.placeQueries, ['adidogome']);
    await tester.tap(find.widgetWithText(ListTile, 'Adidogomé'));
    await tester.pumpAndSettle();

    expect(search.lastQuery?.label, 'Adidogomé');
    expect(search.lastQuery?.latitude, 6.2101);
    expect(find.widgetWithText(ListTile, 'Adidogomé'), findsNothing);
  });

  testWidgets('aucun garage : le rayon peut être élargi', (tester) async {
    final search = FakeSearchRepository();
    await pumpApp(tester, searchRepository: search);

    expect(
      find.text(
        'Aucun garage dans un rayon de 10 km autour de « Ma position ».',
      ),
      findsOneWidget,
    );

    await tester.tap(find.text('Élargir à 20 km'));
    await tester.pumpAndSettle();

    expect(search.lastQuery?.radiusKm, 20);
    expect(find.text('Élargir à 30 km'), findsOneWidget);
  });

  testWidgets('les filtres relancent la recherche', (tester) async {
    final search = FakeSearchRepository(garages: [garageResult()]);
    await pumpApp(tester, searchRepository: search);

    await tester.tap(find.widgetWithText(ChoiceChip, 'Moto'));
    await tester.pumpAndSettle();
    expect(search.lastQuery?.vehicleTypeId, moto.id);

    await tester.tap(find.widgetWithText(FilterChip, 'Ouvert maintenant'));
    await tester.pumpAndSettle();
    expect(search.lastQuery?.openNow, isTrue);
    expect(search.lastQuery?.vehicleTypeId, moto.id);

    // Second appui : tous les véhicules.
    await tester.tap(find.widgetWithText(ChoiceChip, 'Moto'));
    await tester.pumpAndSettle();
    expect(search.lastQuery?.vehicleTypeId, isNull);
  });

  testWidgets('sans position, la recherche se fait autour de la ville', (
    tester,
  ) async {
    final search = FakeSearchRepository();
    await pumpApp(
      tester,
      searchRepository: search,
      locationService: FakeLocationService.failing(
        'Activez la localisation (GPS) du téléphone, puis réessayez.',
      ),
    );

    expect(search.lastQuery?.label, 'Lomé');
    expect(search.lastQuery?.latitude, 6.1319);
    expect(
      find.text('Activez la localisation (GPS) du téléphone, puis réessayez.'),
      findsOneWidget,
    );
  });

  testWidgets('le bouton « Ma position » relance la localisation', (
    tester,
  ) async {
    final location = FakeLocationService(
      const LocationFix(latitude: 6.1427, longitude: 1.2485, accuracyMeters: 5),
    );
    final search = FakeSearchRepository();
    await pumpApp(tester, searchRepository: search, locationService: location);

    await tester.tap(find.byTooltip('Ma position'));
    await tester.pumpAndSettle();

    expect(location.calls, 2);
    expect(search.lastQuery?.latitude, 6.1427);
  });

  test('formatDistance', () {
    expect(formatDistance(8), 'À 8 m');
    expect(formatDistance(347), 'À 350 m');
    expect(formatDistance(1240), 'À 1,2 km');
    expect(formatDistance(9960), 'À 10,0 km');
    expect(formatDistance(12400), 'À 12 km');
  });
}
