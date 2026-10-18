import 'package:findsolux/features/pro/domain/my_business.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers/test_app.dart';

void main() {
  const phone = '+22890123456';

  Future<void> openMyBusiness(
    WidgetTester tester,
    FakeMyBusinessRepository repository,
  ) async {
    useTallScreen(tester);
    await pumpApp(
      tester,
      storage: InMemorySessionStorage(sessionFor(phone)),
      businessRepository: repository,
    );
    await tester.tap(find.text('Vous êtes garagiste ?'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ma fiche'));
    await tester.pumpAndSettle();
  }

  testWidgets('l’accueil garagiste invite à inscrire le garage', (
    tester,
  ) async {
    await pumpApp(tester, storage: InMemorySessionStorage(sessionFor(phone)));
    await tester.tap(find.text('Vous êtes garagiste ?'));
    await tester.pumpAndSettle();

    expect(find.text('Inscrivez votre garage'), findsOneWidget);

    await tester.tap(find.widgetWithText(FilledButton, 'Inscrire mon garage'));
    await tester.pumpAndSettle();

    expect(find.text('1. Votre garage'), findsOneWidget);
  });

  testWidgets('inscription express complète', (tester) async {
    final repository = FakeMyBusinessRepository();
    await openMyBusiness(tester, repository);

    // Le numéro du compte est proposé pour WhatsApp.
    expect(find.text('90123456'), findsOneWidget);

    await tester.enterText(
      find.widgetWithText(TextField, 'Nom du garage'),
      'Garage de la Paix',
    );
    await tester.tap(find.text('Je suis au garage : prendre la position'));
    await tester.pumpAndSettle();

    expect(find.text('Position enregistrée'), findsOneWidget);
    expect(find.text('Précision : 8 m'), findsOneWidget);
    // Quartier le plus proche de la position, proposé automatiquement.
    expect(find.text('Adidogomé'), findsWidgets);

    await tester.tap(find.widgetWithText(FilterChip, 'Moto'));
    await tester.tap(find.widgetWithText(FilterChip, 'Vidange et entretien'));
    await tester.pump();
    await tester.tap(find.widgetWithText(FilledButton, 'Inscrire mon garage'));
    await tester.pumpAndSettle();

    final created = repository.created!;
    expect(created.name, 'Garage de la Paix');
    expect(created.phone, phone);
    expect(created.whatsappPhone, phone);
    expect(created.districtId, adidogome.id);
    expect(created.latitude, 6.2105);
    expect(created.vehicleTypeIds, [moto.id]);
    expect(created.serviceIds, [oilChange.id]);

    expect(find.text('Votre garage est inscrit !'), findsOneWidget);
    expect(find.text('Visible par les clients'), findsOneWidget);
    expect(find.text('Complétez votre fiche'), findsOneWidget);
  });

  testWidgets('les éléments manquants de l’inscription sont signalés', (
    tester,
  ) async {
    await openMyBusiness(tester, FakeMyBusinessRepository());
    final submit = find.widgetWithText(FilledButton, 'Inscrire mon garage');

    await tester.tap(submit);
    await tester.pump();
    expect(find.text('Indiquez le nom du garage.'), findsOneWidget);

    await tester.enterText(
      find.widgetWithText(TextField, 'Nom du garage'),
      'Garage de la Paix',
    );
    await tester.tap(submit);
    await tester.pump();
    expect(find.text('Prenez la position du garage.'), findsOneWidget);
  });

  testWidgets('des horaires sont proposés puis enregistrés', (tester) async {
    final repository = FakeMyBusinessRepository(testBusiness());
    await openMyBusiness(tester, repository);

    expect(find.text('Non renseignés'), findsOneWidget);

    await tester.tap(find.text('Horaires'));
    await tester.pumpAndSettle();
    expect(
      find.text('Horaires proposés : vérifiez-les, puis enregistrez.'),
      findsOneWidget,
    );

    await tester.tap(find.widgetWithText(FilledButton, 'Enregistrer'));
    await tester.pumpAndSettle();

    final ranges = repository.lastOpeningHours!;
    expect([for (final r in ranges) r.day], [1, 2, 3, 4, 5, 6]);
    expect(ranges.first.opensAt, '08:00');
    expect(ranges.first.closesAt, '18:00');
    // Retour à la fiche, résumé à jour.
    expect(find.text('Lun–Sam 08:00–18:00'), findsOneWidget);
  });

  testWidgets('un prix indicatif est ajouté à un service', (tester) async {
    final repository = FakeMyBusinessRepository(testBusiness());
    await openMyBusiness(tester, repository);

    await tester.tap(find.text('Services et prix'));
    await tester.pumpAndSettle();

    await tester.enterText(
      find.widgetWithText(TextField, 'Prix min').first,
      '15000',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Prix max').first,
      '5000',
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Enregistrer'));
    await tester.pump();
    expect(
      find.textContaining('le prix maximum doit être supérieur'),
      findsOneWidget,
    );

    await tester.enterText(
      find.widgetWithText(TextField, 'Prix min').first,
      '5000',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Prix max').first,
      '15000',
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Enregistrer'));
    await tester.pumpAndSettle();

    final services = repository.lastServices!;
    expect(services, hasLength(2));
    expect(services.first.serviceId, oilChange.id);
    expect(services.first.priceMin, 5000);
    expect(services.first.priceMax, 15000);
    expect(services.last.serviceId, brakes.id);
    expect(services.last.hasPrice, isFalse);
    expect(find.text('2 services · 1 avec prix'), findsOneWidget);
  });

  testWidgets('les informations modifiées sont envoyées', (tester) async {
    final repository = FakeMyBusinessRepository(testBusiness());
    await openMyBusiness(tester, repository);

    await tester.tap(find.text('Informations'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextField, 'Point de repère'),
      'En face de la pharmacie',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Numéro WhatsApp'),
      '',
    );
    await tester.tap(find.text('Dépannage 24 h/24'));
    await tester.pump();
    await tester.tap(find.widgetWithText(FilledButton, 'Enregistrer'));
    await tester.pumpAndSettle();

    final changes = repository.lastUpdate!;
    expect(changes['landmark'], 'En face de la pharmacie');
    expect(changes['whatsapp_phone'], isNull);
    expect(changes['phone'], phone);
    expect(changes['is_24_7'], isTrue);
    expect(changes['district_id'], adidogome.id);
    expect(find.text('Fiche mise à jour.'), findsOneWidget);
  });

  test('éléments manquants d’une fiche', () {
    final business = testBusiness(
      services: const [BusinessServiceItem(serviceId: 100, priceMin: 5000)],
      openingHours: const [
        OpeningRange(day: 1, opensAt: '08:00', closesAt: '18:00'),
      ],
    );

    expect(business.missingItems, [
      'Une description',
      'Un point de repère',
      'Des photos de l’atelier',
    ]);
  });
}
