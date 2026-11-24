import 'package:findsolux/features/wallet/presentation/wallet_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers/test_app.dart';

void main() {
  testWidgets('le portefeuille est accessible depuis le compte', (
    tester,
  ) async {
    useTallScreen(tester);
    await pumpApp(
      tester,
      storage: InMemorySessionStorage(sessionFor('+22890123456')),
    );

    await tester.tap(find.text('Compte'));
    await tester.pumpAndSettle();
    expect(find.text('Mon portefeuille'), findsOneWidget);

    await tester.tap(find.text('Mon portefeuille'));
    await tester.pumpAndSettle();

    expect(find.byType(WalletScreen), findsOneWidget);
    expect(find.text('Solde disponible'), findsOneWidget);
    expect(find.text('Recharger le portefeuille'), findsOneWidget);
  });

  testWidgets('la recharge est native et le numéro repart vide', (
    tester,
  ) async {
    useTallScreen(tester);
    final repository = FakeWalletRepository();
    await pumpApp(
      tester,
      storage: InMemorySessionStorage(sessionFor('+22890123456')),
      walletRepository: repository,
    );

    await tester.tap(find.text('Compte'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Mon portefeuille'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Recharger le portefeuille'));
    await tester.pumpAndSettle();

    expect(
      find.text(
        'Le numéro sert uniquement à cette recharge et ne sera pas enregistré.',
      ),
      findsOneWidget,
    );
    expect(find.text('Flooz (Moov Africa)'), findsOneWidget);
    expect(find.text('TMoney (Yas Togo)'), findsOneWidget);

    final fields = find.byType(TextFormField);
    expect(
      tester.widget<TextFormField>(fields.at(1)).controller!.text,
      isEmpty,
    );

    await tester.enterText(fields.at(0), '5000');
    await tester.enterText(fields.at(1), '90123456');
    await tester.tap(find.text('Demander la recharge'));
    await tester.pump();

    expect(repository.submittedAmount, 5000);
    expect(repository.submittedPhone, '90123456');
    expect(repository.submittedPaymentMethod, 'moov_tg');
    expect(
      find.text('Validez la demande sur votre téléphone.'),
      findsOneWidget,
    );

    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
    expect(find.text('Le portefeuille a été crédité.'), findsOneWidget);
  });
}
