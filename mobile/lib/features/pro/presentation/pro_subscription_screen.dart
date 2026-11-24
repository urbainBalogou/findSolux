import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/app_router.dart';
import '../../../core/widgets/async_view.dart';
import '../../pricing/data/commercial_offer_repository.dart';
import '../../pricing/domain/commercial_offer.dart';
import '../../pricing/presentation/commercial_offer_ui.dart';
import '../../wallet/application/wallet_controller.dart';
import '../application/my_business_controller.dart';
import '../data/subscription_repository.dart';
import '../domain/subscriptions.dart';
import 'widgets/client_mode_button.dart';

/// Catalogue des offres commerciales configurées depuis l'administration.
class ProSubscriptionScreen extends ConsumerStatefulWidget {
  const ProSubscriptionScreen({super.key});

  @override
  ConsumerState<ProSubscriptionScreen> createState() =>
      _ProSubscriptionScreenState();
}

class _ProSubscriptionScreenState extends ConsumerState<ProSubscriptionScreen> {
  CommercialOfferType? _selectedType;
  final _selectedOfferIds = <CommercialOfferType, int>{};
  var _purchasing = false;

  @override
  Widget build(BuildContext context) {
    final offers = ref.watch(commercialOffersProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Offres'),
        actions: const [ClientModeButton()],
      ),
      body: AsyncView(
        value: offers,
        onRetry: () => ref.invalidate(commercialOffersProvider),
        builder: (catalog) => _buildCatalog(context, catalog),
      ),
    );
  }

  Widget _buildCatalog(BuildContext context, CommercialOfferCatalog catalog) {
    if (catalog.offers.isEmpty) {
      return _EmptyOffers(
        onRefresh: () => ref.invalidate(commercialOffersProvider),
      );
    }

    final availableTypes = [
      for (final type in CommercialOfferType.values)
        if (catalog.ofType(type).isNotEmpty) type,
    ];
    final selectedType = availableTypes.contains(_selectedType)
        ? _selectedType!
        : availableTypes.first;
    final visibleOffers = catalog.ofType(selectedType);
    final rememberedId = _selectedOfferIds[selectedType];
    final selectedOffer = visibleOffers.firstWhere(
      (offer) => offer.id == rememberedId,
      orElse: () => visibleOffers.first,
    );

    return RefreshIndicator(
      onRefresh: () => ref.refresh(commercialOffersProvider.future),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: [
          const _CurrentSubscriptions(),
          const SizedBox(height: 20),
          const _IntroductionCard(),
          const SizedBox(height: 20),
          if (catalog.acceptancePrice case final price?) ...[
            _AcceptancePriceCard(price: price),
            const SizedBox(height: 20),
          ],
          Text(
            'Que souhaitez-vous ajouter ?',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 12),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                for (final type in availableTypes) ...[
                  _OfferTypeChip(
                    type: type,
                    selected: type == selectedType,
                    onSelected: () => setState(() => _selectedType = type),
                  ),
                  if (type != availableTypes.last) const SizedBox(width: 8),
                ],
              ],
            ),
          ),
          const SizedBox(height: 20),
          _CategoryHeading(
            type: selectedType,
            offerCount: visibleOffers.length,
          ),
          const SizedBox(height: 12),
          for (final offer in visibleOffers) ...[
            _OfferCard(
              offer: offer,
              selected: offer.id == selectedOffer.id,
              onTap: () => setState(() {
                _selectedOfferIds[selectedType] = offer.id;
              }),
            ),
            const SizedBox(height: 10),
          ],
          const SizedBox(height: 6),
          _SelectionSummary(
            offer: selectedOffer,
            purchasing: _purchasing,
            onPurchase: () => _purchase(selectedOffer),
          ),
        ],
      ),
    );
  }

  /// Achat débité du portefeuille (§4.3). Solde insuffisant : proposition
  /// de recharge.
  Future<void> _purchase(CommercialOffer offer) async {
    final autoRenew = await showDialog<bool>(
      context: context,
      builder: (context) => _PurchaseDialog(offer: offer),
    );
    if (autoRenew == null || !mounted) return;

    setState(() => _purchasing = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref
          .read(subscriptionRepositoryProvider)
          .purchase(offer.id, autoRenew: autoRenew);
      messenger.showSnackBar(
        SnackBar(content: Text('« ${offer.name} » est activée.')),
      );
      ref
        ..invalidate(subscriptionsProvider)
        ..invalidate(myBusinessProvider)
        ..invalidate(walletProvider);
    } on ApiException catch (error) {
      if (!mounted) return;
      if (error.isInsufficientBalance) {
        final recharge = await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Solde insuffisant'),
            content: Text(error.message),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Plus tard'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Recharger'),
              ),
            ],
          ),
        );
        if (recharge == true && mounted) {
          await context.push(AppRoutes.wallet);
        }
      } else {
        messenger.showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _purchasing = false);
    }
  }
}

/// Confirmation d'achat ; renvoie le choix du renouvellement automatique,
/// ou null si le garagiste renonce.
class _PurchaseDialog extends StatefulWidget {
  const _PurchaseDialog({required this.offer});

  final CommercialOffer offer;

  @override
  State<_PurchaseDialog> createState() => _PurchaseDialogState();
}

class _PurchaseDialogState extends State<_PurchaseDialog> {
  var _autoRenew = false;

  @override
  Widget build(BuildContext context) {
    final offer = widget.offer;
    final price = offer.price.amount;

    return AlertDialog(
      title: Text('Acheter « ${offer.name} » ?'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${formatPrice(price, price)} seront prélevés sur votre portefeuille '
            '(${offer.duration.label}).',
          ),
          if (!offer.duration.isUnlimited)
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _autoRenew,
              onChanged: (value) => setState(() => _autoRenew = value),
              title: const Text('Renouveler automatiquement'),
              subtitle: const Text(
                'À l’échéance, si votre solde suffit. Modifiable à tout moment.',
              ),
            ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Annuler'),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(context, _autoRenew),
          child: const Text('Acheter'),
        ),
      ],
    );
  }
}

/// Offres en cours, droits qui en découlent et renouvellement automatique.
class _CurrentSubscriptions extends ConsumerWidget {
  const _CurrentSubscriptions();

  Future<void> _setAutoRenew(
    BuildContext context,
    WidgetRef ref,
    BusinessSubscription subscription,
    bool autoRenew,
  ) async {
    try {
      await ref
          .read(subscriptionRepositoryProvider)
          .setAutoRenew(subscription.id, autoRenew: autoRenew);
    } on ApiException catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
    ref.invalidate(subscriptionsProvider);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final overview = ref.watch(subscriptionsProvider).value;
    if (overview == null) return const SizedBox.shrink();

    final theme = Theme.of(context);
    final entitlements = overview.entitlements;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Card(
          color: entitlements.isVisible
              ? theme.colorScheme.primaryContainer
              : theme.colorScheme.errorContainer,
          child: ListTile(
            leading: Icon(
              entitlements.isVisible ? Icons.visibility : Icons.visibility_off,
            ),
            title: Text(
              entitlements.isVisible
                  ? 'Votre garage est visible'
                  : 'Votre garage est invisible',
            ),
            subtitle: Text(
              [
                if (!entitlements.hasMembership)
                  'Achetez une adhésion pour apparaître dans les recherches et recevoir des demandes.'
                else if (entitlements.isUnlimitedMembership)
                  'Adhésion sans limite de durée.'
                else
                  'Adhésion jusqu’au ${formatDay(entitlements.membershipEndsAt!)}.',
                '${entitlements.publishedServicesCount} service(s) affiché(s) sur '
                    '${entitlements.servicesQuota} permis par vos packs.',
                'Solde : ${formatPrice(overview.balance, overview.balance)}.',
              ].join('\n'),
            ),
          ),
        ),
        for (final subscription in overview.active)
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: Icon(subscription.type.icon),
                  title: Text(subscription.name),
                  subtitle: Text(switch (subscription) {
                    BusinessSubscription(endsAt: null) =>
                      'Sans limite de durée',
                    BusinessSubscription(isCurrent: false) =>
                      'Commence le ${formatDay(subscription.startsAt)}',
                    _ =>
                      'Jusqu’au ${formatDay(subscription.endsAt!)}'
                          '${subscription.isGranted ? ' · offerte' : ''}',
                  }),
                ),
                if (subscription.canAutoRenew)
                  SwitchListTile(
                    value: subscription.autoRenew,
                    onChanged: (value) =>
                        _setAutoRenew(context, ref, subscription, value),
                    title: const Text('Renouvellement automatique'),
                    subtitle: Text(
                      'Prélevé sur votre portefeuille à l’échéance. Rappels 7 jours et 1 jour avant.',
                      style: theme.textTheme.bodySmall,
                    ),
                  ),
              ],
            ),
          ),
      ],
    );
  }
}

class _IntroductionCard extends StatelessWidget {
  const _IntroductionCard();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Card(
      color: colors.primaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.workspace_premium_outlined, color: colors.primary),
            const SizedBox(width: 12),
            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Développez votre garage',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  SizedBox(height: 4),
                  Text(
                    'Choisissez une adhésion ou augmentez vos services et '
                    'votre équipe.',
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _OfferTypeChip extends StatelessWidget {
  const _OfferTypeChip({
    required this.type,
    required this.selected,
    required this.onSelected,
  });

  final CommercialOfferType type;
  final bool selected;
  final VoidCallback onSelected;

  @override
  Widget build(BuildContext context) {
    return FilterChip(
      avatar: Icon(type.icon, size: 18),
      label: Text(type.label),
      selected: selected,
      onSelected: (_) => onSelected(),
    );
  }
}

class _CategoryHeading extends StatelessWidget {
  const _CategoryHeading({required this.type, required this.offerCount});

  final CommercialOfferType type;
  final int offerCount;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(type.label, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 3),
              Text(type.description),
            ],
          ),
        ),
        const SizedBox(width: 12),
        Chip(label: Text('$offerCount offre${offerCount > 1 ? 's' : ''}')),
      ],
    );
  }
}

class _OfferCard extends StatelessWidget {
  const _OfferCard({
    required this.offer,
    required this.selected,
    required this.onTap,
  });

  final CommercialOffer offer;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    return Card(
      clipBehavior: Clip.antiAlias,
      color: selected ? colors.secondaryContainer : null,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: selected ? colors.primary : colors.outlineVariant,
          width: selected ? 2 : 1,
        ),
      ),
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(offer.name, style: theme.textTheme.titleMedium),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 6,
                      children: [
                        _OfferDetail(
                          icon: Icons.schedule_outlined,
                          label: offer.duration.label,
                        ),
                        if (offer.quantity != null)
                          _OfferDetail(
                            icon: offer.type.icon,
                            label: offer.quantityLabel,
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    formatPrice(offer.price.amount, offer.price.amount),
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: colors.primary,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Icon(
                    selected
                        ? Icons.check_circle
                        : Icons.radio_button_unchecked,
                    color: selected ? colors.primary : colors.outline,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OfferDetail extends StatelessWidget {
  const _OfferDetail({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15),
          const SizedBox(width: 5),
          Text(label, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}

class _SelectionSummary extends StatelessWidget {
  const _SelectionSummary({
    required this.offer,
    required this.purchasing,
    required this.onPurchase,
  });

  final CommercialOffer offer;
  final bool purchasing;
  final VoidCallback onPurchase;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      elevation: 0,
      color: theme.colorScheme.surfaceContainer,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Votre sélection', style: theme.textTheme.labelLarge),
            const SizedBox(height: 8),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(offer.name, style: theme.textTheme.titleMedium),
                      const SizedBox(height: 2),
                      Text(offer.duration.label),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Text(
                  formatPrice(offer.price.amount, offer.price.amount),
                  style: theme.textTheme.titleMedium,
                ),
              ],
            ),
            const SizedBox(height: 16),
            if (offer.isPurchasable)
              FilledButton.icon(
                onPressed: purchasing ? null : onPurchase,
                icon: const Icon(Icons.account_balance_wallet_outlined),
                label: Text(
                  'Acheter avec mon portefeuille · '
                  '${formatPrice(offer.price.amount, offer.price.amount)}',
                ),
              )
            else
              Text('Bientôt disponible.', style: theme.textTheme.bodySmall),
          ],
        ),
      ),
    );
  }
}

/// Prix unique d'une demande acceptée : rien à acheter, le même pour tous.
class _AcceptancePriceCard extends StatelessWidget {
  const _AcceptancePriceCard({required this.price});

  final int price;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(AcceptancePriceUi.icon, color: theme.colorScheme.primary),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    AcceptancePriceUi.label,
                    style: theme.textTheme.titleSmall,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    AcceptancePriceUi.summary(price),
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    AcceptancePriceUi.explanation,
                    style: theme.textTheme.bodySmall,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EmptyOffers extends StatelessWidget {
  const _EmptyOffers({required this.onRefresh});

  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.inventory_2_outlined, size: 48),
            const SizedBox(height: 16),
            Text(
              'Aucune offre disponible',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 6),
            const Text(
              'Les nouvelles offres apparaîtront ici dès leur activation.',
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 16),
            FilledButton.tonal(
              onPressed: onRefresh,
              child: const Text('Actualiser'),
            ),
          ],
        ),
      ),
    );
  }
}
