import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/async_view.dart';
import '../../reference/data/reference_repository.dart';
import '../application/my_business_controller.dart';
import 'business_overview.dart';
import 'express_signup_form.dart';
import 'widgets/client_mode_button.dart';

/// « Ma fiche » : inscription express si le garage n'est pas encore inscrit,
/// sinon vue d'ensemble de la fiche (lot 3, P1/P3).
class ProBusinessScreen extends ConsumerWidget {
  const ProBusinessScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final business = ref.watch(myBusinessProvider);
    final hasBusiness = business.value != null;

    return Scaffold(
      appBar: AppBar(
        title: Text(
          hasBusiness || business.isLoading
              ? 'Ma fiche'
              : 'Inscrire mon garage',
        ),
        actions: const [ClientModeButton()],
      ),
      body: AsyncView(
        value: ref.watch(referenceDataProvider),
        onRetry: () => ref.invalidate(referenceDataProvider),
        builder: (reference) => AsyncView(
          value: business,
          onRetry: () => ref.invalidate(myBusinessProvider),
          builder: (data) => data == null
              ? ExpressSignupForm(
                  reference: reference,
                  onRetryReference: () => ref.invalidate(referenceDataProvider),
                )
              : BusinessOverview(business: data, reference: reference),
        ),
      ),
    );
  }
}
