import 'package:findsolux/features/auth/presentation/phone_format.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers/test_app.dart';

void main() {
  testWidgets("l'espace garagiste demande d'abord de se connecter", (
    tester,
  ) async {
    await pumpApp(tester);

    await tester.tap(find.text('Vous êtes garagiste ?'));
    await tester.pumpAndSettle();

    expect(find.text('Connexion'), findsOneWidget);
    expect(find.text('Mon garage'), findsNothing);
  });

  testWidgets('fermer la connexion ramène à l’accueil', (tester) async {
    await pumpApp(tester);
    await tester.tap(find.text('Vous êtes garagiste ?'));
    await tester.pumpAndSettle();

    await tester.tap(find.byTooltip('Fermer'));
    await tester.pumpAndSettle();

    expect(find.text(searchHint), findsOneWidget);
  });

  testWidgets('connexion complète puis ouverture de l’espace garagiste', (
    tester,
  ) async {
    final storage = InMemorySessionStorage();
    final api = FakeAuthRepository();
    await pumpApp(tester, storage: storage, authRepository: api);

    await tester.tap(find.text('Vous êtes garagiste ?'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), '90 12 34 56');
    await tester.tap(find.text('Recevoir le code'));
    await tester.pumpAndSettle();

    expect(api.requestedPhones, ['+22890123456']);
    expect(find.textContaining('+228 90 12 34 56'), findsOneWidget);
    expect(find.text('Renvoyer le code dans 60 s'), findsOneWidget);

    await tester.enterText(find.byType(TextField), validCode);
    await tester.pumpAndSettle();

    expect(find.text('Mon garage'), findsOneWidget);
    expect(storage.session?.user.phone, '+22890123456');
  });

  testWidgets('un numéro incomplet est signalé sans appeler l’API', (
    tester,
  ) async {
    final api = FakeAuthRepository();
    await pumpApp(tester, authRepository: api);
    await tester.tap(find.text('Compte'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Se connecter'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), '90 12');
    await tester.tap(find.text('Recevoir le code'));
    await tester.pumpAndSettle();

    expect(find.text('Le numéro doit contenir 8 chiffres.'), findsOneWidget);
    expect(api.requestedPhones, isEmpty);
  });

  testWidgets('un code refusé affiche le message de l’API', (tester) async {
    await pumpApp(tester);
    await tester.tap(find.text('Compte'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Se connecter'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), '90123456');
    await tester.tap(find.text('Recevoir le code'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), '000000');
    await tester.pump();

    expect(
      find.text('Code incorrect. Il vous reste 4 essai(s).'),
      findsOneWidget,
    );
  });

  testWidgets('déconnexion depuis le compte', (tester) async {
    final storage = InMemorySessionStorage(
      sessionFor('+22890123456', name: 'Kossi'),
    );
    final api = FakeAuthRepository();
    await pumpApp(tester, storage: storage, authRepository: api);

    await tester.tap(find.text('Compte'));
    await tester.pumpAndSettle();
    expect(find.text('Kossi'), findsOneWidget);
    expect(find.text('+228 90 12 34 56'), findsOneWidget);

    await tester.tap(find.text('Se déconnecter'));
    await tester.pumpAndSettle();

    expect(api.loggedOut, isTrue);
    expect(storage.session, isNull);
    expect(find.text('Se connecter'), findsOneWidget);
  });

  test('formatage des numéros togolais', () {
    expect(formatPhone('+22890123456'), '+228 90 12 34 56');
    expect(formatPhone('+33612345678'), '+33612345678');
  });
}
