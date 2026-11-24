import 'package:findsolux/core/network/api_exception.dart';
import 'package:findsolux/features/auth/data/google_auth_client.dart';
import 'package:findsolux/features/auth/presentation/phone_format.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers/test_app.dart';

void main() {
  Future<void> openLoginFromAccount(WidgetTester tester) async {
    await tester.tap(find.text('Compte'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Se connecter'));
    await tester.pumpAndSettle();
  }

  testWidgets("l'espace garagiste demande d'abord de se connecter", (
    tester,
  ) async {
    await pumpApp(tester);

    await tester.tap(find.text('Vous êtes garagiste ?'));
    await tester.pumpAndSettle();

    expect(find.text('Connexion'), findsOneWidget);
    expect(find.text('Continuer avec Google'), findsOneWidget);
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

  testWidgets('connexion Google puis ouverture de l’espace garagiste', (
    tester,
  ) async {
    final storage = InMemorySessionStorage();
    await pumpApp(tester, storage: storage);

    await tester.tap(find.text('Vous êtes garagiste ?'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continuer avec Google'));
    await tester.pumpAndSettle();

    expect(find.text('Mon garage'), findsOneWidget);
    expect(storage.session?.user.email, 'kossi.mensah@gmail.com');
  });

  testWidgets('première connexion : le numéro est demandé avant de continuer', (
    tester,
  ) async {
    final storage = InMemorySessionStorage();
    final api = FakeAuthRepository(phone: null);
    await pumpApp(tester, storage: storage, authRepository: api);

    await tester.tap(find.text('Vous êtes garagiste ?'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continuer avec Google'));
    await tester.pumpAndSettle();

    expect(find.text('Votre numéro'), findsOneWidget);
    expect(find.text('Mon garage'), findsNothing);

    await tester.enterText(find.byType(TextField), '90 12 34 56');
    await tester.tap(find.text('Continuer'));
    await tester.pumpAndSettle();

    expect(api.updatedPhones, ['+22890123456']);
    expect(storage.session?.user.phone, '+22890123456');
    expect(find.text('Mon garage'), findsOneWidget);
  });

  testWidgets('un compte sans numéro ne peut pas utiliser l’application', (
    tester,
  ) async {
    await pumpApp(tester, storage: InMemorySessionStorage(sessionFor(null)));

    expect(find.text('Votre numéro'), findsOneWidget);
  });

  testWidgets('un numéro incomplet est signalé sans appeler l’API', (
    tester,
  ) async {
    final api = FakeAuthRepository(phone: null);
    await pumpApp(
      tester,
      storage: InMemorySessionStorage(sessionFor(null)),
      authRepository: api,
    );

    await tester.enterText(find.byType(TextField), '90 12');
    await tester.tap(find.text('Continuer'));
    await tester.pumpAndSettle();

    expect(find.text('Le numéro doit contenir 8 chiffres.'), findsOneWidget);
    expect(api.updatedPhones, isEmpty);
  });

  testWidgets('changer de compte depuis l’écran du numéro déconnecte', (
    tester,
  ) async {
    final storage = InMemorySessionStorage(
      sessionFor(null, email: 'kossi.mensah@gmail.com'),
    );
    final google = FakeGoogleAuthClient();
    await pumpApp(tester, storage: storage, googleAuthClient: google);

    await tester.tap(
      find.text('Utiliser un autre compte que kossi.mensah@gmail.com'),
    );
    await tester.pumpAndSettle();

    expect(storage.session, isNull);
    expect(google.signedOut, isTrue);
    expect(find.text('Continuer avec Google'), findsOneWidget);
  });

  testWidgets('fermer la fenêtre Google laisse sur la connexion sans erreur', (
    tester,
  ) async {
    final storage = InMemorySessionStorage();
    await pumpApp(
      tester,
      storage: storage,
      googleAuthClient: FakeGoogleAuthClient(canceled: true),
    );
    await openLoginFromAccount(tester);

    await tester.tap(find.text('Continuer avec Google'));
    await tester.pumpAndSettle();

    expect(find.text('Continuer avec Google'), findsOneWidget);
    expect(find.textContaining('échoué'), findsNothing);
    expect(storage.session, isNull);
  });

  testWidgets('un échec de Google est affiché', (tester) async {
    await pumpApp(
      tester,
      googleAuthClient: FakeGoogleAuthClient(
        error: const GoogleAuthException(
          'La connexion Google a échoué. Réessayez.',
        ),
      ),
    );
    await openLoginFromAccount(tester);

    await tester.tap(find.text('Continuer avec Google'));
    await tester.pumpAndSettle();

    expect(
      find.text('La connexion Google a échoué. Réessayez.'),
      findsOneWidget,
    );
  });

  testWidgets('un refus de l’API est affiché et oublie le compte Google', (
    tester,
  ) async {
    final google = FakeGoogleAuthClient();
    await pumpApp(
      tester,
      googleAuthClient: google,
      authRepository: FakeAuthRepository(
        error: const ApiException(
          'La connexion Google est momentanément indisponible.',
        ),
      ),
    );
    await openLoginFromAccount(tester);

    await tester.tap(find.text('Continuer avec Google'));
    await tester.pumpAndSettle();

    expect(
      find.text('La connexion Google est momentanément indisponible.'),
      findsOneWidget,
    );
    expect(google.signedOut, isTrue);
  });

  testWidgets('modifier son numéro depuis le compte', (tester) async {
    final storage = InMemorySessionStorage(sessionFor('+22890123456'));
    final api = FakeAuthRepository();
    await pumpApp(tester, storage: storage, authRepository: api);

    await tester.tap(find.text('Compte'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Numéro de téléphone'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), '91 00 00 00');
    await tester.tap(find.text('Enregistrer'));
    await tester.pumpAndSettle();

    expect(api.updatedPhones, ['+22891000000']);
    expect(find.text('Mon compte'), findsOneWidget);
    expect(find.text('+228 91 00 00 00'), findsOneWidget);
  });

  testWidgets('déconnexion depuis le compte', (tester) async {
    final storage = InMemorySessionStorage(
      sessionFor(
        '+22890123456',
        name: 'Kossi',
        email: 'kossi.mensah@gmail.com',
      ),
    );
    final api = FakeAuthRepository();
    final google = FakeGoogleAuthClient();
    await pumpApp(
      tester,
      storage: storage,
      authRepository: api,
      googleAuthClient: google,
    );

    await tester.tap(find.text('Compte'));
    await tester.pumpAndSettle();
    expect(find.text('Kossi'), findsOneWidget);
    expect(find.text('kossi.mensah@gmail.com'), findsOneWidget);
    expect(find.text('+228 90 12 34 56'), findsOneWidget);

    await tester.tap(find.text('Se déconnecter'));
    await tester.pumpAndSettle();

    expect(api.loggedOut, isTrue);
    expect(google.signedOut, isTrue);
    expect(storage.session, isNull);
    expect(find.text('Se connecter'), findsOneWidget);
  });

  test('formatage des numéros togolais', () {
    expect(formatPhone('+22890123456'), '+228 90 12 34 56');
    expect(formatPhone('+33612345678'), '+33612345678');
  });
}
