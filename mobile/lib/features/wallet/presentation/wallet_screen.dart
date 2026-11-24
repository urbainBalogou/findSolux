import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/widgets/async_view.dart';
import '../application/wallet_controller.dart';
import '../domain/wallet.dart';
import 'widgets/top_up_sheet.dart';

class WalletScreen extends ConsumerWidget {
  const WalletScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mon portefeuille')),
      body: AsyncView(
        value: ref.watch(walletProvider),
        onRetry: () => ref.invalidate(walletProvider),
        builder: (wallet) => RefreshIndicator(
          onRefresh: () => ref.read(walletProvider.notifier).reload(),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
            children: [
              _BalanceCard(
                balance: wallet.balance,
                onTopUp: () => showTopUpSheet(context, wallet),
              ),
              const SizedBox(height: 20),
              if (wallet.topUps.isNotEmpty) ...[
                Text(
                  'Recharges récentes',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 8),
                for (final topUp in wallet.topUps) _TopUpTile(topUp: topUp),
                const SizedBox(height: 16),
              ],
              Text(
                'Historique',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              if (wallet.transactions.isEmpty)
                const Card(
                  child: ListTile(
                    leading: Icon(Icons.receipt_long_outlined),
                    title: Text('Aucune opération'),
                    subtitle: Text(
                      'Vos recharges et paiements apparaîtront ici.',
                    ),
                  ),
                )
              else
                for (final entry in wallet.transactions)
                  _EntryTile(entry: entry),
            ],
          ),
        ),
      ),
    );
  }
}

class _BalanceCard extends StatelessWidget {
  const _BalanceCard({required this.balance, required this.onTopUp});

  final int balance;
  final VoidCallback onTopUp;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Card(
      color: colors.primaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('Solde disponible'),
            const SizedBox(height: 6),
            Text(
              formatPrice(balance, balance),
              style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                fontWeight: FontWeight.w700,
                color: colors.onPrimaryContainer,
              ),
            ),
            const SizedBox(height: 18),
            FilledButton.icon(
              onPressed: onTopUp,
              icon: const Icon(Icons.add_card_outlined),
              label: const Text('Recharger le portefeuille'),
            ),
          ],
        ),
      ),
    );
  }
}

class _TopUpTile extends StatelessWidget {
  const _TopUpTile({required this.topUp});

  final WalletTopUp topUp;

  @override
  Widget build(BuildContext context) {
    final succeeded = topUp.status == WalletTopUpStatus.succeeded;
    final pending = topUp.status == WalletTopUpStatus.pending;

    return Card(
      child: ListTile(
        leading: Icon(
          succeeded
              ? Icons.check_circle_outline
              : pending
              ? Icons.schedule_outlined
              : Icons.error_outline,
        ),
        title: Text(formatPrice(topUp.amount, topUp.amount)),
        subtitle: Text('${topUp.paymentMethod.label} · ${topUp.message}'),
      ),
    );
  }
}

class _EntryTile extends StatelessWidget {
  const _EntryTile({required this.entry});

  final WalletEntry entry;

  @override
  Widget build(BuildContext context) {
    final isCredit = entry.direction == 'credit';

    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: CircleAvatar(
        child: Icon(isCredit ? Icons.south_west : Icons.north_east),
      ),
      title: Text(entry.description),
      subtitle: Text(
        '${entry.createdAt.day.toString().padLeft(2, '0')}/'
        '${entry.createdAt.month.toString().padLeft(2, '0')}/'
        '${entry.createdAt.year}',
      ),
      trailing: Text(
        '${isCredit ? '+' : '-'}${formatPrice(entry.amount, entry.amount)}',
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
    );
  }
}
