import 'package:findsolux/features/garage/domain/public_business.dart';
import 'package:findsolux/features/pro/domain/my_business.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers/test_app.dart';

void main() {
  Future<void> openGarage(
    WidgetTester tester, {
    FakeGarageRepository? garages,
    FakeContactLauncher? launcher,
    FakeRequestsRepository? requests,
    bool loggedIn = false,
  }) async {
    useTallScreen(tester);
    await pumpApp(
      tester,
      searchRepository: FakeSearchRepository(
        garages: [garageResult(id: 1, name: 'Garage de la Paix')],
      ),
      garageRepository: garages,
      contactLauncher: launcher,
      requestsRepository: requests,
      storage: loggedIn
          ? InMemorySessionStorage(sessionFor('+22891000000'))
          : null,
    );
    await tester.tap(find.text('Garage de la Paix'));
    await tester.pumpAndSettle();
  }

  /// Client déjà en contact avec le garage : numéros visibles.
  FakeGarageRepository inContact({GarageContact contact = garageContact}) =>
      FakeGarageRepository({'garage-1': publicGarage(contact: contact)});

  testWidgets('sans contact, le numéro est masqué et se demande au garage', (
    tester,
  ) async {
    final requests = FakeRequestsRepository();
    final garages = FakeGarageRepository();
    await openGarage(
      tester,
      garages: garages,
      requests: requests,
      loggedIn: true,
    );

    expect(find.widgetWithText(FilledButton, 'Appeler'), findsNothing);
    expect(find.textContaining('90 12 34 56'), findsNothing);

    await tester.tap(find.widgetWithText(FilledButton, 'Demander le contact'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'Vidange demain ?');
    await tester.tap(find.text('Envoyer la demande'));
    await tester.pumpAndSettle();

    expect(requests.contactRequests, ['garage-1 Vidange demain ?']);
    expect(
      find.text(
        'Demande envoyée. Vous serez prévenu dès que le garage répond.',
      ),
      findsOneWidget,
    );
    // La fiche est rechargée pour afficher la demande en attente.
    expect(garages.fetches, ['garage-1', 'garage-1']);
  });

  testWidgets('demander le contact sans être connecté ouvre la connexion', (
    tester,
  ) async {
    final requests = FakeRequestsRepository();
    await openGarage(tester, requests: requests);

    await tester.tap(find.widgetWithText(FilledButton, 'Demander le contact'));
    await tester.pumpAndSettle();

    expect(find.text('Continuer avec Google'), findsOneWidget);
    expect(requests.contactRequests, isEmpty);
  });

  testWidgets('une demande en attente ne peut pas être renvoyée', (
    tester,
  ) async {
    await openGarage(
      tester,
      garages: FakeGarageRepository({
        'garage-1': publicGarage(hasPendingContactRequest: true),
      }),
      loggedIn: true,
    );

    final button = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Demande envoyée'),
    );
    expect(button.onPressed, isNull);
  });

  testWidgets('toucher une carte ouvre la fiche du garage', (tester) async {
    final garages = FakeGarageRepository();
    await openGarage(tester, garages: garages);

    expect(find.text('Spécialiste moto depuis 15 ans.'), findsOneWidget);
    expect(find.text('Adidogomé · Près du marché'), findsOneWidget);
    expect(find.textContaining('de votre position'), findsOneWidget);
    expect(find.text('5 000 – 15 000 FCFA'), findsOneWidget);
    expect(find.text('Freins · Moto'), findsOneWidget);
    expect(find.text('Prix sur demande'), findsOneWidget);
    expect(find.text('08:00–18:00'), findsOneWidget);
    expect(find.text('Fermé'), findsNWidgets(6));
    expect(
      find.text('Se déplace chez le client, jusqu’à 5 km.'),
      findsOneWidget,
    );
    // La consultation est comptée.
    expect(garages.events, [GarageEvent.view]);
  });

  testWidgets(
    'en contact, Appeler, WhatsApp et Itinéraire ouvrent les applications',
    (tester) async {
      final garages = inContact();
      final launcher = FakeContactLauncher();
      await openGarage(tester, garages: garages, launcher: launcher);

      await tester.tap(find.widgetWithText(FilledButton, 'Appeler'));
      await tester.tap(find.widgetWithText(FilledButton, 'WhatsApp'));
      await tester.tap(find.widgetWithText(OutlinedButton, 'Itinéraire'));
      await tester.pumpAndSettle();

      expect(launcher.opened, [
        'appel +22890123456',
        'whatsapp +22890123456 Bonjour Garage de la Paix, je vous ai trouvé sur MecaDispo.',
        'itineraire 6.2101,1.1547',
      ]);
      expect(garages.events, [
        GarageEvent.view,
        GarageEvent.call,
        GarageEvent.whatsapp,
        GarageEvent.directions,
      ]);
    },
  );

  testWidgets('le partage envoie le lien de la page web', (tester) async {
    final launcher = FakeContactLauncher();
    await openGarage(tester, launcher: launcher);

    await tester.tap(find.byTooltip('Partager'));
    await tester.pumpAndSettle();

    expect(
      launcher.opened.single,
      'partage Garage de la Paix — garage à Adidogomé\n'
      'https://findsolux.tg/garages/garage-1',
    );
  });

  testWidgets('sans WhatsApp, le bouton n’apparaît pas', (tester) async {
    await openGarage(
      tester,
      garages: inContact(contact: const GarageContact(phone: '+22890123456')),
    );

    expect(find.widgetWithText(FilledButton, 'Appeler'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'WhatsApp'), findsNothing);
  });

  testWidgets('un échec d’ouverture est expliqué', (tester) async {
    await openGarage(
      tester,
      garages: inContact(),
      launcher: FakeContactLauncher(succeeds: false),
    );

    await tester.tap(find.widgetWithText(FilledButton, 'WhatsApp'));
    await tester.pumpAndSettle();

    expect(
      find.text(
        'WhatsApp ne s’est pas ouvert. Est-il installé sur ce téléphone ?',
      ),
      findsOneWidget,
    );
  });

  testWidgets('sans horaires, le client est invité à appeler', (tester) async {
    await openGarage(
      tester,
      garages: FakeGarageRepository({
        'garage-1': publicGarage(openingHours: const []),
      }),
    );

    expect(
      find.text('Horaires non renseignés : renseignez-vous avant de passer.'),
      findsOneWidget,
    );
  });

  testWidgets('rouvrir une fiche recharge les prix et horaires modifiés', (
    tester,
  ) async {
    final garages = FakeGarageRepository({
      'garage-1': publicGarage(
        services: const [BusinessServiceItem(serviceId: 100)],
        openingHours: const [],
      ),
    });
    await openGarage(tester, garages: garages);

    expect(find.text('Prix sur demande'), findsOneWidget);
    expect(
      find.text('Horaires non renseignés : renseignez-vous avant de passer.'),
      findsOneWidget,
    );

    garages.garages['garage-1'] = publicGarage();
    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    await tester.tap(find.text('Garage de la Paix'));
    await tester.pumpAndSettle();

    expect(find.text('5 000 – 15 000 FCFA'), findsOneWidget);
    expect(find.text('08:00–18:00'), findsOneWidget);
    expect(garages.fetches, ['garage-1', 'garage-1']);
  });

  testWidgets('une fiche introuvable propose de réessayer', (tester) async {
    await openGarage(tester, garages: FakeGarageRepository({}));

    expect(find.text('Élément introuvable.'), findsOneWidget);
    expect(find.text('Réessayer'), findsOneWidget);
  });
}
