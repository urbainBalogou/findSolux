import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/app_router.dart';
import '../../../core/widgets/async_view.dart';
import '../../auth/application/auth_controller.dart';
import '../../reference/data/reference_repository.dart';
import '../data/requests_repository.dart';
import '../domain/requests.dart';
import 'widgets/contact_actions.dart';

/// « Mes garages » (C6) : garages qui ont accepté de partager leur numéro,
/// et demandes de contact en attente.
class MyGaragesScreen extends ConsumerWidget {
  const MyGaragesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isLoggedIn = ref.watch(authControllerProvider).value != null;

    return Scaffold(
      appBar: AppBar(title: const Text('Mes garages')),
      body: isLoggedIn
          ? const _MyGaragesList()
          : _LoginInvitation(
              onLogin: () => context.push(
                AppRoutes.loginLocation(from: AppRoutes.myGarages),
              ),
            ),
    );
  }
}

class _MyGaragesList extends ConsumerWidget {
  const _MyGaragesList();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final garages = ref.watch(savedGaragesProvider);
    final pending = [
      for (final request in ref.watch(myContactRequestsProvider).value ?? [])
        if (request.status == ContactRequestStatus.pending) request,
    ];
    final reference = ref.watch(referenceDataProvider).value;

    Future<void> refresh() async {
      ref.invalidate(myContactRequestsProvider);
      ref.invalidate(savedGaragesProvider);
      await ref.read(savedGaragesProvider.future);
    }

    return AsyncView(
      value: garages,
      onRetry: refresh,
      builder: (garages) => RefreshIndicator(
        onRefresh: refresh,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          children: [
            Card(
              child: ListTile(
                leading: const Icon(Icons.sos),
                title: const Text('En panne maintenant ?'),
                subtitle: const Text(
                  'Envoyez un signal de détresse aux garages proches.',
                ),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => context.push(AppRoutes.distressSignal),
              ),
            ),
            if (pending.isNotEmpty) ...[
              const SizedBox(height: 16),
              Text(
                'En attente de réponse',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              for (final request in pending)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.hourglass_top),
                  title: Text(request.garage.name),
                  subtitle: const Text('Demande de contact envoyée'),
                  onTap: () =>
                      context.push(AppRoutes.garage(request.garage.slug)),
                ),
            ],
            const SizedBox(height: 16),
            Text(
              'Garages en contact',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            if (garages.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 24),
                child: Text(
                  'Aucun garage pour l’instant. Demandez le contact d’un garage '
                  'depuis sa fiche : son numéro s’affichera ici dès qu’il accepte.',
                  textAlign: TextAlign.center,
                ),
              ),
            for (final saved in garages)
              Card(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(saved.garage.name),
                        subtitle: Text(
                          reference
                                  ?.districtById(saved.garage.districtId)
                                  ?.name ??
                              '',
                        ),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () =>
                            context.push(AppRoutes.garage(saved.garage.slug)),
                      ),
                      ContactActions(
                        phone: saved.garage.phone!,
                        whatsappPhone: saved.garage.whatsappPhone,
                        whatsappMessage:
                            'Bonjour ${saved.garage.name}, je vous contacte via MecaDispo.',
                        latitude: saved.garage.latitude,
                        longitude: saved.garage.longitude,
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _LoginInvitation extends StatelessWidget {
  const _LoginInvitation({required this.onLogin});

  final VoidCallback onLogin;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.handshake_outlined, size: 48),
            const SizedBox(height: 16),
            const Text(
              'Connectez-vous pour retrouver les garages qui ont accepté de '
              'vous donner leur numéro.',
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 16),
            FilledButton(onPressed: onLogin, child: const Text('Se connecter')),
          ],
        ),
      ),
    );
  }
}
