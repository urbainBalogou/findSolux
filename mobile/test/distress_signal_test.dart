import 'package:findsolux/features/requests/domain/requests.dart';
import 'package:findsolux/features/requests/presentation/distress_signal_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers/test_app.dart';

void main() {
  Future<void> openDistressSignal(
    WidgetTester tester, {
    required FakeRequestsRepository requests,
    FakeLocationService? location,
    FakeContactLauncher? launcher,
  }) async {
    useTallScreen(tester);
    await pumpApp(
      tester,
      storage: InMemorySessionStorage(sessionFor('+22891000000')),
      requestsRepository: requests,
      locationService: location,
      contactLauncher: launcher,
    );
    await tester.tap(find.text('En panne ?'));
    await tester.pumpAndSettle();
  }

  testWidgets('sans compte, le signal de détresse demande la connexion', (
    tester,
  ) async {
    await pumpApp(tester);

    await tester.tap(find.text('En panne ?'));
    await tester.pumpAndSettle();

    expect(find.text('Continuer avec Google'), findsOneWidget);
  });

  testWidgets('le signal part avec la position et attend un garage', (
    tester,
  ) async {
    final requests = FakeRequestsRepository();
    await openDistressSignal(tester, requests: requests);

    await tester.tap(find.text('Crevaison'));
    await tester.tap(find.widgetWithText(ChoiceChip, 'Moto'));
    await tester.tap(find.text('Envoyer le signal'));
    await tester.pump();
    await tester.pump();

    final sent = requests.sentSignals.single;
    expect(sent.message, 'Crevaison');
    expect(sent.vehicleTypeId, moto.id);
    expect(sent.latitude, 6.2105);
    expect(find.text('Signal envoyé à 3 garages'), findsOneWidget);
    expect(find.text('Annuler le signal'), findsOneWidget);
  });

  testWidgets('quand un garage accepte, son numéro s’affiche', (tester) async {
    final requests = FakeRequestsRepository();
    final launcher = FakeContactLauncher();
    await openDistressSignal(tester, requests: requests, launcher: launcher);
    await tester.enterText(find.byType(TextField), 'Batterie à plat');
    await tester.tap(find.text('Envoyer le signal'));
    await tester.pump();
    await tester.pump();

    requests.nextSignalState = distressSignal(
      status: DistressSignalStatus.accepted,
      garage: const ContactGarage(
        slug: 'garage-rapide',
        name: 'Garage Rapide',
        districtId: 10,
        phone: '+22890111111',
      ),
    );
    await tester.pump(DistressSignalScreen.pollInterval);
    await tester.pump();

    expect(
      find.text('Garage Rapide a accepté votre demande d’aide'),
      findsOneWidget,
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Appeler'));
    await tester.pump();
    expect(launcher.opened, ['appel +22890111111']);
  });

  testWidgets('sans réponse à temps, le client peut renvoyer un signal', (
    tester,
  ) async {
    final requests = FakeRequestsRepository();
    await openDistressSignal(tester, requests: requests);
    await tester.enterText(find.byType(TextField), 'Panne moteur');
    await tester.tap(find.text('Envoyer le signal'));
    await tester.pump();
    await tester.pump();

    requests.nextSignalState = distressSignal(
      status: DistressSignalStatus.expired,
    );
    await tester.pump(DistressSignalScreen.pollInterval);
    await tester.pump();

    expect(find.text('Aucun garage n’a répondu à temps'), findsOneWidget);
    await tester.tap(find.text('Envoyer un nouveau signal'));
    await tester.pump();
    expect(find.text('Envoyer le signal'), findsOneWidget);
  });

  testWidgets('un message trop court est refusé avant l’envoi', (tester) async {
    final requests = FakeRequestsRepository();
    await openDistressSignal(tester, requests: requests);

    await tester.tap(find.text('Envoyer le signal'));
    await tester.pump();

    expect(
      find.text('Décrivez votre problème en quelques mots.'),
      findsOneWidget,
    );
    expect(requests.sentSignals, isEmpty);
  });
}
