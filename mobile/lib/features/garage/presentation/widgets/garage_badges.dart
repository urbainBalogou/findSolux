import 'package:flutter/material.dart';

/// Badges d'état d'un garage : 24 h/24, ouvert ou fermé (seulement si les
/// horaires sont renseignés), se déplace.
class GarageBadges extends StatelessWidget {
  const GarageBadges({
    super.key,
    required this.is24h,
    required this.isOpenNow,
    required this.hasOpeningHours,
    required this.isMobile,
  });

  final bool is24h;
  final bool isOpenNow;
  final bool hasOpeningHours;
  final bool isMobile;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Wrap(
      spacing: 6,
      runSpacing: 4,
      children: [
        if (is24h)
          _Badge('24 h/24', colors.tertiary)
        else if (isOpenNow)
          _Badge('Ouvert', colors.tertiary)
        else if (hasOpeningHours)
          _Badge('Fermé', colors.outline),
        if (isMobile) _Badge('Se déplace', colors.secondary),
      ],
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge(this.label, this.color);

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        border: Border.all(color: color),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(color: color),
      ),
    );
  }
}
