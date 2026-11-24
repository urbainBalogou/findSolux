import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatting/distance_format.dart';
import '../../../core/formatting/formatters.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/app_router.dart';
import '../../../core/widgets/async_view.dart';
import '../../auth/application/auth_controller.dart';
import '../../auth/presentation/phone_format.dart';
import '../../pro/presentation/widgets/client_mode_button.dart';
import '../../wallet/application/wallet_controller.dart';
import '../data/requests_repository.dart';
import '../domain/requests.dart';
import 'widgets/contact_actions.dart';

/// Demandes reçues par le garage (P5) et clients en contact (P6). Accepter
/// une demande débite le portefeuille du prix d'acceptation.
class ProRequestsScreen extends ConsumerStatefulWidget {
  const ProRequestsScreen({super.key});

  /// Rafraîchissement de secours, quand les notifications push manquent.
  static const refreshInterval = Duration(seconds: 30);

  @override
  ConsumerState<ProRequestsScreen> createState() => _ProRequestsScreenState();
}

class _ProRequestsScreenState extends ConsumerState<ProRequestsScreen> {
  Timer? _refresh;

  @override
  void initState() {
    super.initState();
    _refresh = Timer.periodic(
      ProRequestsScreen.refreshInterval,
      (_) => ref.invalidate(requestInboxProvider),
    );
  }

  @override
  void dispose() {
    _refresh?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final inbox = ref.watch(requestInboxProvider);
    final pending = inbox.value;
    final membership = ref
        .watch(authControllerProvider)
        .value
        ?.user
        .teamMembership;

    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: Text(membership?.businessName ?? 'Demandes'),
          actions: [
            if (membership == null)
              const ClientModeButton()
            else
              IconButton(
                icon: const Icon(Icons.logout),
                tooltip: 'Se déconnecter',
                onPressed: () =>
                    ref.read(authControllerProvider.notifier).logout(),
              ),
          ],
          bottom: TabBar(
            tabs: [
              Tab(
                text: _withCount(
                  'Détresse',
                  pending?.distressSignals.where((s) => s.canAnswer).length,
                ),
              ),
              Tab(
                text: _withCount(
                  'Contact',
                  pending?.contactRequests.where((r) => r.canAnswer).length,
                ),
              ),
              const Tab(text: 'Mes clients'),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            AsyncView(
              value: inbox,
              onRetry: () => ref.invalidate(requestInboxProvider),
              builder: (inbox) => _DistressSignalList(inbox: inbox),
            ),
            AsyncView(
              value: inbox,
              onRetry: () => ref.invalidate(requestInboxProvider),
              builder: (inbox) => _ContactRequestList(inbox: inbox),
            ),
            const _ClientList(),
          ],
        ),
      ),
    );
  }

  String _withCount(String label, int? count) =>
      count == null || count == 0 ? label : '$label ($count)';
}

/// Accepte ou refuse une demande, en expliquant le débit et les refus
/// (solde insuffisant, adhésion inactive, demande déjà prise).
Future<void> answerRequest(
  BuildContext context,
  WidgetRef ref, {
  required bool accept,
  required int price,
  required int balance,
  required Future<void> Function(RequestsRepository repository) action,
}) async {
  // Un membre de l'équipe accepte au nom du garage, sans gérer le portefeuille.
  final isTeamMember =
      ref.read(authControllerProvider).value?.user.isTeamMember ?? false;

  if (accept && price > 0) {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Accepter cette demande ?'),
        content: Text(
          '${formatPrice(price, price)} seront prélevés sur '
          '${isTeamMember ? 'le portefeuille du garage' : 'votre portefeuille'} '
          '(solde : ${formatPrice(balance, balance)}). Vous recevrez le numéro du client.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Accepter'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
  }

  try {
    await action(ref.read(requestsRepositoryProvider));
    if (accept) ref.invalidate(walletProvider);
  } on ApiException catch (error) {
    if (!context.mounted) return;
    if (isTeamMember &&
        (error.isInsufficientBalance || error.isMembershipRequired)) {
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(
            error.isInsufficientBalance
                ? 'Solde du garage insuffisant'
                : 'Adhésion du garage inactive',
          ),
          content: Text(
            '${error.message}\n\nPrévenez le responsable du garage.',
          ),
          actions: [
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text('OK'),
            ),
          ],
        ),
      );
    } else if (error.isInsufficientBalance || error.isMembershipRequired) {
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(
            error.isInsufficientBalance
                ? 'Solde insuffisant'
                : 'Adhésion inactive',
          ),
          content: Text(error.message),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text('Plus tard'),
            ),
            FilledButton(
              onPressed: () {
                Navigator.pop(dialogContext);
                if (error.isInsufficientBalance) {
                  context.push(AppRoutes.wallet);
                } else {
                  context.go(AppRoutes.proSubscription);
                }
              },
              child: Text(
                error.isInsufficientBalance ? 'Recharger' : 'Voir les offres',
              ),
            ),
          ],
        ),
      );
    } else {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }
  ref.invalidate(requestInboxProvider);
  ref.invalidate(businessClientsProvider);
}

/// « par vous », ou « par Kodjo » quand un autre membre de l'équipe a accepté.
String acceptedByLabel(RequestResponder? acceptedBy, int? currentUserId) {
  if (acceptedBy == null || acceptedBy.id == currentUserId) return 'par vous';
  return 'par ${acceptedBy.name ?? 'un membre de l’équipe'}';
}

class _DistressSignalList extends ConsumerWidget {
  const _DistressSignalList({required this.inbox});

  final RequestInbox inbox;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final signals = inbox.distressSignals;

    return RefreshIndicator(
      onRefresh: () => ref.refresh(requestInboxProvider.future),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: [
          _PriceHint(inbox: inbox),
          if (signals.isEmpty)
            const _Empty(
              icon: Icons.sos,
              message:
                  'Aucun signal de détresse pour le moment. Les clients en panne '
                  'dans votre zone d’intervention apparaîtront ici.',
            ),
          for (final signal in signals)
            _DistressSignalCard(signal: signal, inbox: inbox),
        ],
      ),
    );
  }
}

class _DistressSignalCard extends ConsumerWidget {
  const _DistressSignalCard({required this.signal, required this.inbox});

  final ReceivedDistressSignal signal;
  final RequestInbox inbox;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final client = signal.client;
    final remaining = signal.expiresAt.difference(DateTime.now()).inMinutes;
    final currentUserId = ref.watch(authControllerProvider).value?.user.id;
    final status = switch (signal.status) {
      ReceivedSignalStatus.accepted =>
        'Accepté ${acceptedByLabel(signal.acceptedBy, currentUserId)}',
      ReceivedSignalStatus.taken => 'Pris en charge par un autre garage',
      ReceivedSignalStatus.cancelled => 'Annulé par le client',
      ReceivedSignalStatus.expired => 'Expiré',
      ReceivedSignalStatus.declined when signal.canAnswer =>
        'Refusé · encore $remaining min pour changer d’avis',
      ReceivedSignalStatus.declined => 'Refusé',
      ReceivedSignalStatus.open => 'Encore $remaining min pour répondre',
    };

    return Card(
      color: signal.canAnswer ? theme.colorScheme.errorContainer : null,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.sos),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    formatDistance(signal.distanceMeters),
                    style: theme.textTheme.titleMedium,
                  ),
                ),
                Text(status, style: theme.textTheme.bodySmall),
              ],
            ),
            const SizedBox(height: 8),
            Text(signal.message, style: theme.textTheme.bodyLarge),
            if (client?.phone != null) ...[
              const SizedBox(height: 12),
              Text(
                '${client!.name ?? 'Client'} · ${formatPhone(client.phone!)}',
                style: theme.textTheme.titleSmall,
              ),
              const SizedBox(height: 8),
              ContactActions(
                phone: client.phone!,
                whatsappPhone: client.phone,
                whatsappMessage:
                    'Bonjour, j’ai reçu votre signal de détresse sur MecaDispo. J’arrive.',
                latitude: client.latitude,
                longitude: client.longitude,
              ),
            ],
            if (signal.canAnswer) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: FilledButton(
                      onPressed: () => answerRequest(
                        context,
                        ref,
                        accept: true,
                        price: inbox.acceptancePrice,
                        balance: inbox.balance,
                        action: (repository) => repository.answerDistressSignal(
                          signal.id,
                          accept: true,
                        ),
                      ),
                      child: const Text('J’y vais'),
                    ),
                  ),
                  if (signal.status == ReceivedSignalStatus.open) ...[
                    const SizedBox(width: 8),
                    OutlinedButton(
                      onPressed: () => answerRequest(
                        context,
                        ref,
                        accept: false,
                        price: 0,
                        balance: inbox.balance,
                        action: (repository) => repository.answerDistressSignal(
                          signal.id,
                          accept: false,
                        ),
                      ),
                      child: const Text('Refuser'),
                    ),
                  ],
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ContactRequestList extends ConsumerWidget {
  const _ContactRequestList({required this.inbox});

  final RequestInbox inbox;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final requests = inbox.contactRequests;
    final currentUserId = ref.watch(authControllerProvider).value?.user.id;

    return RefreshIndicator(
      onRefresh: () => ref.refresh(requestInboxProvider.future),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: [
          _PriceHint(inbox: inbox),
          if (requests.isEmpty)
            const _Empty(
              icon: Icons.contact_phone_outlined,
              message:
                  'Aucune demande de contact. Les clients qui consultent votre '
                  'fiche peuvent vous demander votre numéro.',
            ),
          for (final request in requests)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      request.client.name ?? 'Un client',
                      style: theme.textTheme.titleMedium,
                    ),
                    if (request.message != null) ...[
                      const SizedBox(height: 4),
                      Text(request.message!),
                    ],
                    const SizedBox(height: 4),
                    Text(switch (request.status) {
                      ContactRequestStatus.pending when request.canAnswer =>
                        'En attente de votre réponse',
                      ContactRequestStatus.accepted =>
                        'Acceptée ${acceptedByLabel(request.acceptedBy, currentUserId)}',
                      ContactRequestStatus.declined => 'Refusée',
                      ContactRequestStatus.cancelled => 'Annulée par le client',
                      _ => 'Expirée',
                    }, style: theme.textTheme.bodySmall),
                    if (request.client.phone != null) ...[
                      const SizedBox(height: 8),
                      Text(formatPhone(request.client.phone!)),
                      const SizedBox(height: 8),
                      ContactActions(
                        phone: request.client.phone!,
                        whatsappPhone: request.client.phone,
                      ),
                    ],
                    if (request.canAnswer) ...[
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: FilledButton(
                              onPressed: () => answerRequest(
                                context,
                                ref,
                                accept: true,
                                price: inbox.acceptancePrice,
                                balance: inbox.balance,
                                action: (repository) =>
                                    repository.answerContactRequest(
                                      request.id,
                                      accept: true,
                                    ),
                              ),
                              child: const Text('Partager mon numéro'),
                            ),
                          ),
                          const SizedBox(width: 8),
                          OutlinedButton(
                            onPressed: () => answerRequest(
                              context,
                              ref,
                              accept: false,
                              price: 0,
                              balance: inbox.balance,
                              action: (repository) =>
                                  repository.answerContactRequest(
                                    request.id,
                                    accept: false,
                                  ),
                            ),
                            child: const Text('Refuser'),
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _ClientList extends ConsumerWidget {
  const _ClientList();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return AsyncView(
      value: ref.watch(businessClientsProvider),
      onRetry: () => ref.invalidate(businessClientsProvider),
      builder: (clients) => RefreshIndicator(
        onRefresh: () => ref.refresh(businessClientsProvider.future),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
          children: [
            if (clients.isEmpty)
              const _Empty(
                icon: Icons.people_outline,
                message:
                    'Les clients dont vous acceptez une demande apparaissent ici, '
                    'avec leur numéro.',
              ),
            for (final client in clients)
              Card(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: Icon(
                          client.fromDistressSignal
                              ? Icons.sos
                              : Icons.contact_phone_outlined,
                        ),
                        title: Text(client.name ?? formatPhone(client.phone)),
                        subtitle: Text(formatPhone(client.phone)),
                      ),
                      ContactActions(
                        phone: client.phone,
                        whatsappPhone: client.phone,
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

class _PriceHint extends ConsumerWidget {
  const _PriceHint({required this.inbox});

  final RequestInbox inbox;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final price = inbox.acceptancePrice;
    // Seul le garagiste recharge le portefeuille.
    final canTopUp =
        !(ref.watch(authControllerProvider).value?.user.isTeamMember ?? false);
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          const Icon(Icons.account_balance_wallet_outlined, size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              price == 0
                  ? 'Accepter une demande est gratuit pour le moment.'
                  : 'Chaque acceptation : ${formatPrice(price, price)} · '
                        'solde ${formatPrice(inbox.balance, inbox.balance)}',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
          if (canTopUp)
            TextButton(
              onPressed: () => context.push(AppRoutes.wallet),
              child: const Text('Recharger'),
            ),
        ],
      ),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 48, horizontal: 16),
      child: Column(
        children: [
          Icon(icon, size: 48),
          const SizedBox(height: 16),
          Text(message, textAlign: TextAlign.center),
        ],
      ),
    );
  }
}
