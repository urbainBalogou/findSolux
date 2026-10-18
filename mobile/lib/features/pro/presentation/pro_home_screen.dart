import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/app_router.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/coming_soon.dart';
import '../application/my_business_controller.dart';
import 'widgets/client_mode_button.dart';

/// Accueil garagiste : vues et contacts reçus (lot 10, P6). Tant que le
/// garage n'est pas inscrit, invite à l'inscrire.
class ProHomeScreen extends ConsumerWidget {
  const ProHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Mon garage'),
        actions: const [ClientModeButton()],
      ),
      body: AsyncView(
        value: ref.watch(myBusinessProvider),
        onRetry: () => ref.invalidate(myBusinessProvider),
        builder: (business) => business == null
            ? const _RegisterInvitation()
            : const ComingSoon(
                icon: Icons.insights_outlined,
                message:
                    'Vous verrez ici combien de clients ont vu votre fiche '
                    'et vous ont contacté.',
              ),
      ),
    );
  }
}

class _RegisterInvitation extends StatelessWidget {
  const _RegisterInvitation();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.storefront_outlined,
              size: 56,
              color: theme.colorScheme.primary,
            ),
            const SizedBox(height: 16),
            Text(
              'Inscrivez votre garage',
              style: theme.textTheme.titleLarge,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            const Text(
              'Gratuit, en quelques minutes. Les clients du quartier vous '
              'trouveront et vous appelleront directement.',
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: () => context.go(AppRoutes.proBusiness),
              child: const Text('Inscrire mon garage'),
            ),
          ],
        ),
      ),
    );
  }
}
