import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/router/app_router.dart';
import '../../../core/widgets/async_view.dart';
import '../../pricing/data/commercial_offer_repository.dart';
import '../../pricing/domain/commercial_offer.dart';
import '../../pricing/presentation/commercial_offer_ui.dart';
import '../application/my_business_controller.dart';
import '../domain/my_business.dart';
import 'widgets/client_mode_button.dart';

/// Accueil garagiste : vues et contacts reçus (lot 10, P6). Tant que le
/// garage n'est pas inscrit, invite à l'inscrire.
class ProHomeScreen extends ConsumerWidget {
  const ProHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final offers = ref.watch(commercialOffersProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mon garage'),
        actions: [
          IconButton(
            tooltip: 'Mon portefeuille',
            onPressed: () => context.push(AppRoutes.wallet),
            icon: const Icon(Icons.account_balance_wallet_outlined),
          ),
          const ClientModeButton(),
        ],
      ),
      body: AsyncView(
        value: ref.watch(myBusinessProvider),
        onRetry: () => ref.invalidate(myBusinessProvider),
        builder: (business) => business == null
            ? const _RegisterInvitation()
            : _GarageDashboard(
                business: business,
                offers: offers,
                onRetryOffers: () => ref.invalidate(commercialOffersProvider),
              ),
      ),
    );
  }
}

class _GarageDashboard extends StatelessWidget {
  const _GarageDashboard({
    required this.business,
    required this.offers,
    required this.onRetryOffers,
  });

  final MyBusiness business;
  final AsyncValue<CommercialOfferCatalog> offers;
  final VoidCallback onRetryOffers;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final serviceCount = {
      for (final service in business.services) service.serviceId,
    }.length;
    final missingCount = business.missingItems.length;
    final entitlements = business.entitlements;
    final isVisible = entitlements.isVisible;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      children: [
        Card(
          color: isVisible
              ? theme.colorScheme.primaryContainer
              : theme.colorScheme.errorContainer,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                CircleAvatar(
                  backgroundColor: theme.colorScheme.primary,
                  foregroundColor: theme.colorScheme.onPrimary,
                  child: const Icon(Icons.storefront_outlined),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(business.name, style: theme.textTheme.titleMedium),
                      const SizedBox(height: 3),
                      Text(
                        isVisible
                            ? 'Votre garage est visible par les clients et reçoit leurs demandes.'
                            : 'Votre garage est invisible : achetez une adhésion pour '
                                  'apparaître dans les recherches et recevoir des demandes.',
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        if (!isVisible) ...[
          const SizedBox(height: 8),
          FilledButton.icon(
            onPressed: () => context.go(AppRoutes.proSubscription),
            icon: const Icon(Icons.workspace_premium_outlined),
            label: const Text('Choisir une adhésion'),
          ),
        ],
        const SizedBox(height: 8),
        Card(
          child: ListTile(
            leading: const Icon(Icons.notifications_active_outlined),
            title: const Text('Demandes des clients'),
            subtitle: const Text(
              'Signaux de détresse et demandes de contact reçus',
            ),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => context.go(AppRoutes.proRequests),
          ),
        ),
        Card(
          child: ListTile(
            leading: const Icon(Icons.groups_outlined),
            title: const Text('Mon équipe'),
            subtitle: Text(
              entitlements.teamQuota == 0
                  ? 'Employés et répondeurs : avec un pack Équipe'
                  : '${entitlements.teamCount} / ${entitlements.teamQuota} '
                        'membre${entitlements.teamQuota > 1 ? 's' : ''}',
            ),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => context.go(AppRoutes.proTeam),
          ),
        ),
        const SizedBox(height: 20),
        Text('Résumé de votre espace', style: theme.textTheme.titleLarge),
        const SizedBox(height: 12),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 1.3,
          children: [
            _BenefitCard(
              icon: isVisible
                  ? Icons.visibility_outlined
                  : Icons.visibility_off_outlined,
              title: 'Visibilité',
              value: isVisible ? 'Visible' : 'Invisible',
              caption: isVisible
                  ? 'Dans les recherches clients'
                  : 'Adhésion requise',
            ),
            _BenefitCard(
              icon: Icons.build_outlined,
              title: 'Services',
              value: '$serviceCount',
              caption: entitlements.hiddenServicesCount > 0
                  ? '${entitlements.hiddenServicesCount} non affiché(s) : pack requis'
                  : 'service${serviceCount > 1 ? 's' : ''} sur votre fiche',
            ),
            _BenefitCard(
              icon: Icons.phone_in_talk_outlined,
              title: 'Contacts',
              value: business.whatsappPhone == null
                  ? 'Appel'
                  : 'Appel + WhatsApp',
              caption: 'Moyens de contact actifs',
            ),
            _BenefitCard(
              icon: missingCount == 0
                  ? Icons.task_alt
                  : Icons.checklist_outlined,
              title: 'Profil',
              value: missingCount == 0
                  ? 'Complet'
                  : '$missingCount à compléter',
              caption: 'Informations de votre fiche',
            ),
          ],
        ),
        const SizedBox(height: 24),
        Text('Avantages disponibles', style: theme.textTheme.titleLarge),
        const SizedBox(height: 4),
        Text(
          'Ces offres peuvent augmenter les capacités de votre espace. Une '
          'offre devient active après souscription et paiement.',
          style: theme.textTheme.bodyMedium,
        ),
        const SizedBox(height: 12),
        offers.when(
          loading: () => const Padding(
            padding: EdgeInsets.symmetric(vertical: 24),
            child: Center(child: CircularProgressIndicator()),
          ),
          error: (_, _) => Card(
            child: ListTile(
              leading: const Icon(Icons.wifi_off_outlined),
              title: const Text('Offres indisponibles'),
              subtitle: const Text(
                'Impossible de charger les avantages pour le moment.',
              ),
              trailing: TextButton(
                onPressed: onRetryOffers,
                child: const Text('Réessayer'),
              ),
            ),
          ),
          data: (catalog) {
            final availableTypes = CommercialOfferType.values
                .where((type) => catalog.ofType(type).isNotEmpty)
                .toList();
            final acceptancePrice = catalog.acceptancePrice;

            return Column(
              children: [
                if (availableTypes.isEmpty)
                  const Card(
                    child: ListTile(
                      leading: Icon(Icons.inventory_2_outlined),
                      title: Text('Aucune offre active'),
                      subtitle: Text(
                        'Les offres apparaîtront ici après leur activation.',
                      ),
                    ),
                  ),
                for (final type in availableTypes)
                  _OfferBenefitTile(type: type, offers: catalog.ofType(type)),
                if (acceptancePrice != null)
                  _AcceptancePriceTile(price: acceptancePrice),
              ],
            );
          },
        ),
        const SizedBox(height: 10),
        FilledButton.icon(
          onPressed: () => context.go(AppRoutes.proSubscription),
          icon: const Icon(Icons.workspace_premium_outlined),
          label: const Text('Voir toutes les offres'),
        ),
      ],
    );
  }
}

class _BenefitCard extends StatelessWidget {
  const _BenefitCard({
    required this.icon,
    required this.title,
    required this.value,
    required this.caption,
  });

  final IconData icon;
  final String title;
  final String value;
  final String caption;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: theme.colorScheme.primary),
            const Spacer(),
            Text(title, style: theme.textTheme.labelMedium),
            const SizedBox(height: 2),
            Text(
              value,
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              caption,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}

class _OfferBenefitTile extends StatelessWidget {
  const _OfferBenefitTile({required this.type, required this.offers});

  final CommercialOfferType type;
  final List<CommercialOffer> offers;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: Icon(type.icon),
        title: Text(type.label),
        subtitle: Text(_summary),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => context.go(AppRoutes.proSubscription),
      ),
    );
  }

  String get _summary {
    if (offers.isEmpty) return 'Aucune offre active pour le moment';

    final minimumPrice = offers
        .map((offer) => offer.price.amount)
        .reduce((left, right) => left < right ? left : right);
    final price = formatPrice(minimumPrice, minimumPrice);
    final maximumQuantity = offers
        .map((offer) => offer.quantity)
        .whereType<int>()
        .fold<int?>(null, (current, quantity) {
          if (current == null || quantity > current) return quantity;
          return current;
        });

    return switch (type) {
      CommercialOfferType.membership =>
        '${offers.length} formule${offers.length > 1 ? 's' : ''} dès $price',
      CommercialOfferType.serviceBundle =>
        maximumQuantity == null
            ? '${offers.length} offre${offers.length > 1 ? 's' : ''} dès $price'
            : 'Jusqu’à $maximumQuantity services par offre',
      CommercialOfferType.staffBundle =>
        maximumQuantity == null
            ? '${offers.length} offre${offers.length > 1 ? 's' : ''} dès $price'
            : 'Jusqu’à $maximumQuantity collaborateurs par offre',
    };
  }
}

/// Prix unique d'une demande acceptée, le même quelle que soit l'offre.
class _AcceptancePriceTile extends StatelessWidget {
  const _AcceptancePriceTile({required this.price});

  final int price;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: const Icon(AcceptancePriceUi.icon),
        title: const Text(AcceptancePriceUi.label),
        subtitle: Text(AcceptancePriceUi.summary(price)),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => context.go(AppRoutes.proSubscription),
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
