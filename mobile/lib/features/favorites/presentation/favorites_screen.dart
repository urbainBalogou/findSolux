import 'package:flutter/material.dart';

import '../../../core/widgets/coming_soon.dart';

/// Garages enregistrés, disponibles sans compte (lot 11, C7).
class FavoritesScreen extends StatelessWidget {
  const FavoritesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mes favoris')),
      body: const ComingSoon(
        icon: Icons.favorite_border,
        message: 'Retrouvez ici les garages que vous avez enregistrés.',
      ),
    );
  }
}
