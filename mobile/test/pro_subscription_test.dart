import 'package:findsolux/core/formatting/formatters.dart';
import 'package:findsolux/core/network/api_exception.dart';
import 'package:findsolux/features/pricing/domain/commercial_offer.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers/test_app.dart';

void main() {
  Future<void> openProHome(WidgetTester tester) async {
    await tester.tap(find.text('Compte'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Espace garagiste'));
    await tester.pumpAndSettle();
  }

  Future<void> openSubscription(WidgetTester tester) async {
    await openProHome(tester);
    await tester.tap(find.text('Offres'));
    await tester.pumpAndSettle();
  }

  CommercialOfferCatalog partialCatalog() => CommercialOfferCatalog(
    testCommercialOffers.offers
        .where(
          (offer) =>
              offer.type == CommercialOfferType.membership ||
              offer.type == CommercialOfferType.serviceBundle,
        )
        .toList(),
  );

  testWidgets('affiche et filtre les offres commerciales de l’API', (
    tester,
  ) async {
    useTallScreen(tester);
    await pumpApp(
      tester,
      storage: InMemorySessionStorage(sessionFor('+22890123456')),
    );

    await openSubscription(tester);

    expect(find.text('Adhésion mensuelle'), findsWidgets);
    expect(find.text('Adhésion annuelle'), findsOneWidget);
    expect(find.text(formatPrice(5000, 5000)), findsWidgets);

    await tester.tap(find.widgetWithText(FilterChip, 'Services'));
    await tester.pumpAndSettle();

    expect(find.text('Pack de 10 services'), findsWidgets);
    expect(find.text('10 services'), findsOneWidget);
    expect(find.text(formatPrice(12000, 12000)), findsWidgets);
    expect(find.text('Adhésion mensuelle'), findsNothing);

    // Prix unique d'une demande acceptée : une information, pas une offre.
    expect(find.widgetWithText(FilterChip, 'Demandes clients'), findsNothing);
    expect(find.text('Demandes clients'), findsOneWidget);
    expect(
      find.text('${formatPrice(500, 500)} par demande acceptée'),
      findsOneWidget,
    );
  });

  testWidgets('l’accueil garagiste rappelle le prix d’une demande acceptée', (
    tester,
  ) async {
    useTallScreen(tester);
    await pumpApp(
      tester,
      storage: InMemorySessionStorage(sessionFor('+22890123456')),
      businessRepository: FakeMyBusinessRepository(testBusiness()),
    );

    await openProHome(tester);

    expect(find.text('Demandes clients'), findsOneWidget);
    expect(
      find.text('${formatPrice(500, 500)} par demande acceptée'),
      findsOneWidget,
    );
  });

  Future<void> buyAnnualMembership(WidgetTester tester) async {
    await openSubscription(tester);
    final annualOffer = find.text('Adhésion annuelle');
    await tester.ensureVisible(annualOffer);
    await tester.tap(annualOffer);
    await tester.pumpAndSettle();

    final buy = find.textContaining('Acheter avec mon portefeuille');
    await tester.ensureVisible(buy);
    await tester.tap(buy);
    await tester.pumpAndSettle();
  }

  testWidgets('une offre sélectionnée s’achète avec le portefeuille', (
    tester,
  ) async {
    useTallScreen(tester);
    final subscriptions = FakeSubscriptionRepository();
    await pumpApp(
      tester,
      storage: InMemorySessionStorage(sessionFor('+22890123456')),
      businessRepository: FakeMyBusinessRepository(testBusiness()),
      subscriptionRepository: subscriptions,
    );

    await buyAnnualMembership(tester);

    expect(find.text('Acheter « Adhésion annuelle » ?'), findsOneWidget);
    await tester.tap(find.text('Renouveler automatiquement'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Acheter'));
    await tester.pumpAndSettle();

    expect(subscriptions.purchases, ['2 renouvelée']);
    expect(find.text('« Adhésion annuelle » est activée.'), findsOneWidget);
  });

  testWidgets('solde insuffisant : l’achat propose de recharger', (
    tester,
  ) async {
    useTallScreen(tester);
    await pumpApp(
      tester,
      storage: InMemorySessionStorage(sessionFor('+22890123456')),
      businessRepository: FakeMyBusinessRepository(testBusiness()),
      subscriptionRepository: FakeSubscriptionRepository(
        purchaseError: const ApiException(
          'Solde insuffisant. Rechargez votre portefeuille pour continuer.',
          statusCode: 422,
          code: 'insufficient_balance',
        ),
      ),
    );

    await buyAnnualMembership(tester);
    await tester.tap(find.widgetWithText(FilledButton, 'Acheter'));
    await tester.pumpAndSettle();

    expect(find.text('Solde insuffisant'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'Recharger'));
    await tester.pumpAndSettle();

    expect(find.text('Mon portefeuille'), findsOneWidget);
  });

  testWidgets('les packs d’équipe s’achètent avec le portefeuille', (
    tester,
  ) async {
    useTallScreen(tester);
    await pumpApp(
      tester,
      storage: InMemorySessionStorage(sessionFor('+22890123456')),
      businessRepository: FakeMyBusinessRepository(testBusiness()),
    );

    await openSubscription(tester);
    final teamChip = find.widgetWithText(FilterChip, 'Équipe');
    await tester.ensureVisible(teamChip);
    await tester.tap(teamChip);
    await tester.pumpAndSettle();

    expect(find.text('Bientôt disponible.'), findsNothing);
    expect(
      find.textContaining('Acheter avec mon portefeuille'),
      findsOneWidget,
    );
  });

  testWidgets('affiche le résumé global sur l’accueil garagiste', (
    tester,
  ) async {
    useTallScreen(tester);
    await pumpApp(
      tester,
      storage: InMemorySessionStorage(sessionFor('+22890123456')),
      businessRepository: FakeMyBusinessRepository(testBusiness()),
      commercialOffers: partialCatalog(),
    );

    await openProHome(tester);

    expect(find.text('Résumé de votre espace'), findsOneWidget);
    expect(find.text('Visible'), findsOneWidget);
    expect(find.text('2'), findsOneWidget);
    expect(find.text('services sur votre fiche'), findsOneWidget);
    expect(find.text('Appel + WhatsApp'), findsOneWidget);
    expect(find.text('Avantages disponibles'), findsOneWidget);
    expect(find.text('Équipe'), findsNothing);
    expect(find.text('Demandes clients'), findsNothing);
    expect(find.text('Voir toutes les offres'), findsOneWidget);
  });
}
