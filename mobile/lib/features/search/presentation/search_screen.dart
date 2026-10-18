import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/app_router.dart';
import '../../../core/widgets/coming_soon.dart';

/// Écran d'accueil : recherche de garages à proximité (lot 4, C1/C2).
class SearchScreen extends StatelessWidget {
  const SearchScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Trouver un garage')),
      body: Column(
        children: [
          const Expanded(
            child: ComingSoon(
              icon: Icons.car_repair,
              message: 'La recherche des garages proches arrive bientôt.',
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Card(
              child: ListTile(
                leading: const Icon(Icons.storefront_outlined),
                title: const Text('Vous êtes garagiste ?'),
                subtitle: const Text('Inscrivez votre garage gratuitement'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => context.go(AppRoutes.proHome),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
