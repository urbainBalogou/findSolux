import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers/test_app.dart';

void main() {
  testWidgets("l'application démarre sur l'espace client", (tester) async {
    await pumpApp(tester);

    expect(find.text(searchHint), findsOneWidget);
    expect(find.text('Rechercher'), findsOneWidget);
    expect(find.text('Favoris'), findsOneWidget);
    expect(find.text('Compte'), findsOneWidget);
  });

  testWidgets('passage entre espace client et espace garagiste', (
    tester,
  ) async {
    await pumpApp(
      tester,
      storage: InMemorySessionStorage(sessionFor('+22890123456')),
    );

    await tester.tap(find.text('Compte'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Espace garagiste'));
    await tester.pumpAndSettle();

    expect(find.text('Mon garage'), findsOneWidget);
    expect(find.text('Ma fiche'), findsOneWidget);
    expect(find.text('Abonnement'), findsOneWidget);

    await tester.tap(find.widgetWithText(TextButton, 'Mode client'));
    await tester.pumpAndSettle();

    expect(find.text(searchHint), findsOneWidget);
  });
}
