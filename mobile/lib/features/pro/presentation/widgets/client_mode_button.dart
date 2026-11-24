import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/app_router.dart';

/// Retour de l'espace garagiste vers l'espace client.
class ClientModeButton extends StatelessWidget {
  const ClientModeButton({super.key});

  @override
  Widget build(BuildContext context) {
    return TextButton.icon(
      onPressed: () => context.go(AppRoutes.search),
      icon: const Icon(Icons.swap_horiz),
      label: const Text('Mode client'),
    );
  }
}
