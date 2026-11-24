import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/app_router.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/presentation/phone_format.dart';

/// Compte de l'utilisateur et accès à l'espace garagiste (ADR-010).
class AccountScreen extends ConsumerWidget {
  const AccountScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(authControllerProvider).value;

    return Scaffold(
      appBar: AppBar(title: const Text('Mon compte')),
      body: ListView(
        children: [
          if (session == null)
            ListTile(
              leading: const Icon(Icons.login),
              title: const Text('Se connecter'),
              subtitle: const Text('Avec votre compte Google'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push(
                AppRoutes.loginLocation(from: AppRoutes.account),
              ),
            )
          else ...[
            ListTile(
              leading: const Icon(Icons.person),
              title: Text(session.user.name ?? 'Mon compte'),
              subtitle: session.user.email == null
                  ? null
                  : Text(session.user.email!),
            ),
            ListTile(
              leading: const Icon(Icons.phone_outlined),
              title: const Text('Numéro de téléphone'),
              subtitle: Text(
                session.user.phone == null
                    ? 'À renseigner'
                    : formatPhone(session.user.phone!),
              ),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push(AppRoutes.accountPhone),
            ),
          ],
          ListTile(
            leading: const Icon(Icons.storefront_outlined),
            title: const Text('Espace garagiste'),
            subtitle: const Text('Inscrire et gérer mon garage'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => context.go(AppRoutes.proHome),
          ),
          if (session != null)
            ListTile(
              leading: const Icon(Icons.account_balance_wallet_outlined),
              title: const Text('Mon portefeuille'),
              subtitle: const Text('Solde, recharges et opérations'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push(AppRoutes.wallet),
            ),
          if (session != null)
            ListTile(
              leading: const Icon(Icons.logout),
              title: const Text('Se déconnecter'),
              onTap: () => ref.read(authControllerProvider.notifier).logout(),
            ),
        ],
      ),
    );
  }
}
