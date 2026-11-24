import 'package:findsolux/core/network/api_exception.dart';
import 'package:findsolux/features/requests/domain/requests.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers/test_app.dart';

void main() {
  Future<void> openRequests(
    WidgetTester tester,
    FakeRequestsRepository requests,
  ) async {
    useTallScreen(tester);
    await pumpApp(
      tester,
      storage: InMemorySessionStorage(sessionFor('+22890123456')),
      businessRepository: FakeMyBusinessRepository(testBusiness()),
      requestsRepository: requests,
    );
    await tester.tap(find.text('Compte'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Espace garagiste'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Demandes').last);
    await tester.pumpAndSettle();
  }

  FakeRequestsRepository withSignal() => FakeRequestsRepository(
    inbox: RequestInbox(
      distressSignals: [receivedSignal()],
      contactRequests: const [],
      acceptancePrice: 500,
      balance: 2000,
    ),
  );

  testWidgets('accepter un signal de détresse débite et donne le client', (
    tester,
  ) async {
    final requests = withSignal();
    await openRequests(tester, requests);

    expect(find.text('Détresse (1)'), findsOneWidget);
    expect(find.text('Crevaison du pneu avant'), findsOneWidget);

    await tester.tap(find.text('J’y vais'));
    await tester.pumpAndSettle();
    expect(find.textContaining('500 FCFA seront prélevés'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'Accepter'));
    await tester.pumpAndSettle();

    expect(requests.answers, ['accept signal-1']);
    expect(find.text('Accepté par vous'), findsOneWidget);
    expect(find.textContaining('Kossi'), findsOneWidget);
    expect(find.widgetWithText(OutlinedButton, 'Itinéraire'), findsOneWidget);
  });

  testWidgets('solde insuffisant : la recharge est proposée', (tester) async {
    final requests = withSignal()
      ..answerError = const ApiException(
        'Solde insuffisant. Rechargez votre portefeuille pour continuer.',
        statusCode: 422,
        code: 'insufficient_balance',
      );
    await openRequests(tester, requests);

    await tester.tap(find.text('J’y vais'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Accepter'));
    await tester.pumpAndSettle();

    expect(find.text('Solde insuffisant'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'Recharger'));
    await tester.pumpAndSettle();

    expect(find.text('Mon portefeuille'), findsOneWidget);
  });

  testWidgets('refuser un signal ne débite rien', (tester) async {
    final requests = withSignal();
    await openRequests(tester, requests);

    await tester.tap(find.widgetWithText(OutlinedButton, 'Refuser'));
    await tester.pumpAndSettle();

    expect(requests.answers, ['decline signal-1']);
    expect(find.textContaining('prélevés'), findsNothing);
  });
}
