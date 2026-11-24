import 'package:findsolux/core/network/api_exception.dart';
import 'package:findsolux/features/pro/domain/team.dart';
import 'package:findsolux/features/requests/domain/requests.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers/test_app.dart';

void main() {
  FakeRequestsRepository withSignal() => FakeRequestsRepository(
    inbox: RequestInbox(
      distressSignals: [receivedSignal()],
      contactRequests: const [],
      acceptancePrice: 500,
      balance: 2000,
    ),
  );

  group('membre de l’équipe (employé ou répondeur)', () {
    testWidgets('se connecte avec son identifiant et arrive sur les demandes', (
      tester,
    ) async {
      final storage = InMemorySessionStorage();
      await pumpApp(tester, storage: storage);
      await tester.tap(find.text('Compte'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Se connecter'));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Je suis employé ou répondeur d’un garage'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.widgetWithText(TextField, 'Identifiant'),
        'kodjo',
      );
      await tester.enterText(
        find.widgetWithText(TextField, 'Mot de passe'),
        'motdepasse',
      );
      await tester.tap(find.widgetWithText(FilledButton, 'Se connecter'));
      await tester.pumpAndSettle();

      expect(storage.session?.user.isTeamMember, isTrue);
      expect(find.text('Garage de la Paix'), findsOneWidget);
      expect(find.text('Détresse'), findsOneWidget);
    });

    testWidgets('un mot de passe refusé affiche le message de l’API', (
      tester,
    ) async {
      await pumpApp(tester);
      await tester.tap(find.text('Compte'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Se connecter'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Je suis employé ou répondeur d’un garage'));
      await tester.pumpAndSettle();

      await tester.enterText(
        find.widgetWithText(TextField, 'Identifiant'),
        'kodjo',
      );
      await tester.enterText(
        find.widgetWithText(TextField, 'Mot de passe'),
        'mauvais',
      );
      await tester.tap(find.widgetWithText(FilledButton, 'Se connecter'));
      await tester.pumpAndSettle();

      expect(
        find.text('Identifiant ou mot de passe incorrect.'),
        findsOneWidget,
      );
    });

    testWidgets('reste sur les demandes du garage, sans espace client', (
      tester,
    ) async {
      await pumpApp(
        tester,
        storage: InMemorySessionStorage(teamMemberSession()),
      );

      expect(find.text('Garage de la Paix'), findsOneWidget);
      expect(find.text('Rechercher'), findsNothing);
      expect(find.text('Mon garage'), findsNothing);
    });

    testWidgets('accepte au nom du garage, prélevé sur son portefeuille', (
      tester,
    ) async {
      useTallScreen(tester);
      final requests = withSignal();
      await pumpApp(
        tester,
        storage: InMemorySessionStorage(teamMemberSession()),
        requestsRepository: requests,
      );

      await tester.tap(find.text('J’y vais'));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('prélevés sur le portefeuille du garage'),
        findsOneWidget,
      );
      await tester.tap(find.widgetWithText(FilledButton, 'Accepter'));
      await tester.pumpAndSettle();

      expect(requests.answers, ['accept signal-1']);
      expect(find.text('Recharger'), findsNothing);
    });

    testWidgets('solde insuffisant : il prévient le responsable', (
      tester,
    ) async {
      useTallScreen(tester);
      final requests = withSignal()
        ..answerError = const ApiException(
          'Solde insuffisant.',
          statusCode: 422,
          code: 'insufficient_balance',
        );
      await pumpApp(
        tester,
        storage: InMemorySessionStorage(teamMemberSession()),
        requestsRepository: requests,
      );

      await tester.tap(find.text('J’y vais'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Accepter'));
      await tester.pumpAndSettle();

      expect(find.text('Solde du garage insuffisant'), findsOneWidget);
      expect(
        find.textContaining('Prévenez le responsable du garage.'),
        findsOneWidget,
      );
      expect(find.widgetWithText(FilledButton, 'Recharger'), findsNothing);
    });

    testWidgets('se déconnecte depuis les demandes', (tester) async {
      final storage = InMemorySessionStorage(teamMemberSession());
      await pumpApp(tester, storage: storage);

      await tester.tap(find.byTooltip('Se déconnecter'));
      await tester.pumpAndSettle();

      expect(storage.session, isNull);
      expect(find.text('Continuer avec Google'), findsOneWidget);
    });
  });

  group('garagiste', () {
    Future<void> openTeam(WidgetTester tester, FakeTeamRepository team) async {
      useTallScreen(tester);
      await pumpApp(
        tester,
        storage: InMemorySessionStorage(sessionFor('+22890123456')),
        businessRepository: FakeMyBusinessRepository(testBusiness()),
        teamRepository: team,
      );
      await tester.tap(find.text('Compte'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Espace garagiste'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Mon équipe'));
      await tester.pumpAndSettle();
    }

    testWidgets('crée un membre et lui transmet ses identifiants', (
      tester,
    ) async {
      final team = FakeTeamRepository(quota: 2);
      await openTeam(tester, team);

      expect(find.text('0 / 2 membres'), findsOneWidget);
      await tester.tap(find.text('Ajouter un membre'));
      await tester.pumpAndSettle();
      await tester.enterText(find.widgetWithText(TextField, 'Nom'), 'Kodjo');
      await tester.enterText(
        find.widgetWithText(TextField, 'Identifiant'),
        'Kodjo.Paix',
      );
      await tester.enterText(
        find.widgetWithText(TextField, 'Mot de passe'),
        'atelier2026',
      );
      await tester.tap(find.widgetWithText(FilledButton, 'Créer'));
      await tester.pumpAndSettle();

      expect(team.members.single.username, 'kodjo.paix');
      expect(find.text('Identifiant : kodjo.paix'), findsWidgets);
      expect(find.text('Mot de passe : atelier2026'), findsOneWidget);

      await tester.tap(find.text('C’est noté'));
      await tester.pumpAndSettle();
      expect(find.text('Kodjo'), findsOneWidget);
      expect(find.text('1 / 2 membres'), findsOneWidget);
    });

    testWidgets('un mot de passe trop court est signalé sans appeler l’API', (
      tester,
    ) async {
      final team = FakeTeamRepository();
      await openTeam(tester, team);

      await tester.tap(find.text('Ajouter un membre'));
      await tester.pumpAndSettle();
      await tester.enterText(find.widgetWithText(TextField, 'Nom'), 'Kodjo');
      await tester.enterText(
        find.widgetWithText(TextField, 'Identifiant'),
        'kodjo',
      );
      await tester.enterText(
        find.widgetWithText(TextField, 'Mot de passe'),
        '1234',
      );
      await tester.tap(find.widgetWithText(FilledButton, 'Créer'));
      await tester.pumpAndSettle();

      expect(
        find.text('Le mot de passe doit contenir au moins 8 caractères.'),
        findsOneWidget,
      );
      expect(team.members, isEmpty);
    });

    testWidgets('équipe complète : propose un pack supplémentaire', (
      tester,
    ) async {
      await openTeam(
        tester,
        FakeTeamRepository(
          quota: 1,
          members: const [
            TeamMember(id: 7, name: 'Kodjo', username: 'kodjo', isActive: true),
          ],
        ),
      );

      expect(find.textContaining('Votre équipe est complète'), findsOneWidget);
      expect(find.text('Voir les offres'), findsOneWidget);
      expect(find.text('Ajouter un membre'), findsNothing);
    });

    testWidgets('retire un membre de l’équipe', (tester) async {
      final team = FakeTeamRepository(
        members: const [
          TeamMember(id: 7, name: 'Kodjo', username: 'kodjo', isActive: true),
        ],
      );
      await openTeam(tester, team);

      await tester.tap(find.byTooltip('Actions pour Kodjo'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Retirer de l’équipe'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Retirer'));
      await tester.pumpAndSettle();

      expect(team.members, isEmpty);
      expect(find.text('Kodjo'), findsNothing);
    });

    testWidgets('voit quel membre de l’équipe a accepté', (tester) async {
      useTallScreen(tester);
      await pumpApp(
        tester,
        storage: InMemorySessionStorage(sessionFor('+22890123456')),
        businessRepository: FakeMyBusinessRepository(testBusiness()),
        requestsRepository: FakeRequestsRepository(
          inbox: RequestInbox(
            distressSignals: [
              ReceivedDistressSignal(
                id: 'signal-1',
                status: ReceivedSignalStatus.accepted,
                message: 'Crevaison du pneu avant',
                distanceMeters: 800,
                expiresAt: DateTime.now().add(const Duration(minutes: 5)),
                createdAt: DateTime.now(),
                client: const RequestClient(
                  name: 'Kossi',
                  phone: '+22891000000',
                ),
                acceptedBy: const RequestResponder(id: 7, name: 'Kodjo'),
              ),
            ],
            contactRequests: const [],
            acceptancePrice: 500,
            balance: 1500,
          ),
        ),
      );
      await tester.tap(find.text('Compte'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Espace garagiste'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Demandes').last);
      await tester.pumpAndSettle();

      expect(find.text('Accepté par Kodjo'), findsOneWidget);
    });
  });
}
