import 'package:flutter/material.dart';

import '../../../core/widgets/coming_soon.dart';
import 'widgets/client_mode_button.dart';

/// Avis reçus et réponses du garagiste (lot 6, P7).
class ProReviewsScreen extends StatelessWidget {
  const ProReviewsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Avis clients'),
        actions: const [ClientModeButton()],
      ),
      body: const ComingSoon(
        icon: Icons.star_outline,
        message: 'Les avis de vos clients apparaîtront ici.',
      ),
    );
  }
}
