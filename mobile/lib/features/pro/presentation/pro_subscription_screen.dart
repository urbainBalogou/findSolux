import 'package:flutter/material.dart';

import '../../../core/widgets/coming_soon.dart';
import 'widgets/client_mode_button.dart';

/// Formule, échéance et paiement de l'abonnement (lot 9, P4).
class ProSubscriptionScreen extends StatelessWidget {
  const ProSubscriptionScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Abonnement'),
        actions: const [ClientModeButton()],
      ),
      body: const ComingSoon(
        icon: Icons.workspace_premium_outlined,
        message: 'Choisissez votre formule et payez par Mobile Money.',
      ),
    );
  }
}
