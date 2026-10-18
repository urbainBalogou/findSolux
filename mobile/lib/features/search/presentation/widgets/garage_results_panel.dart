import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/network/api_exception.dart';
import '../../../../core/router/app_router.dart';
import '../../../reference/data/reference_repository.dart';
import '../../application/search_controller.dart';
import '../../domain/search_models.dart';
import 'garage_card.dart';

/// Bandeau du bas : cartes des garages, du plus proche au plus éloigné, à
/// faire défiler horizontalement. Affiche aussi le chargement, l'erreur et
/// l'absence de résultat.
class GarageResultsPanel extends ConsumerWidget {
  const GarageResultsPanel({
    super.key,
    required this.pageController,
    required this.selectedIndex,
    required this.onPageChanged,
  });

  static const height = 176.0;

  final PageController pageController;
  final int selectedIndex;
  final ValueChanged<int> onPageChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final query = ref.watch(searchQueryProvider);
    final results = ref.watch(garageResultsProvider);
    final reference = ref.watch(referenceDataProvider).value;
    final garages = results.value;

    if (query == null) {
      return const _Message(child: _Progress('Recherche de votre position…'));
    }
    if (garages == null) {
      return _Message(
        child: results.hasError
            ? _Retry(
                message: results.error is ApiException
                    ? (results.error! as ApiException).message
                    : 'Une erreur est survenue.',
                onRetry: () => ref.invalidate(garageResultsProvider),
              )
            : const _Progress('Recherche des garages…'),
      );
    }
    if (garages.isEmpty) {
      return _Message(child: _NoResult(query: query));
    }

    return SizedBox(
      height: height,
      child: Column(
        children: [
          if (results.isLoading)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 24),
              child: LinearProgressIndicator(minHeight: 2),
            ),
          Expanded(
            child: PageView.builder(
              controller: pageController,
              itemCount: garages.length,
              onPageChanged: onPageChanged,
              itemBuilder: (context, index) => Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: GarageCard(
                  garage: garages[index],
                  reference: reference,
                  selected: index == selectedIndex,
                  onTap: () => index == selectedIndex
                      ? onPageChanged(index)
                      : pageController.animateToPage(
                          index,
                          duration: const Duration(milliseconds: 300),
                          curve: Curves.easeOut,
                        ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12),
      child: Card(
        child: Padding(padding: const EdgeInsets.all(16), child: child),
      ),
    );
  }
}

class _Progress extends StatelessWidget {
  const _Progress(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const SizedBox.square(
          dimension: 20,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
        const SizedBox(width: 12),
        Expanded(child: Text(label)),
      ],
    );
  }
}

class _Retry extends StatelessWidget {
  const _Retry({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(child: Text(message)),
        TextButton(onPressed: onRetry, child: const Text('Réessayer')),
      ],
    );
  }
}

class _NoResult extends ConsumerWidget {
  const _NoResult({required this.query});

  final SearchQuery query;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final wider = query.widerRadiusKm;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'Aucun garage dans un rayon de ${query.radiusKm} km '
          'autour de « ${query.label} ».',
        ),
        Wrap(
          spacing: 8,
          children: [
            if (wider != null)
              FilledButton.tonal(
                onPressed: () =>
                    ref.read(searchQueryProvider.notifier).widenRadius(),
                child: Text('Élargir à $wider km'),
              ),
            TextButton(
              onPressed: () => context.go(AppRoutes.proHome),
              child: const Text('Vous êtes garagiste ?'),
            ),
          ],
        ),
      ],
    );
  }
}
