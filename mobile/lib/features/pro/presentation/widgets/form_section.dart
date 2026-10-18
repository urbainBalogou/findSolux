import 'package:flutter/material.dart';

/// Titre de section dans les formulaires de la fiche.
class FormSection extends StatelessWidget {
  const FormSection({super.key, required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

/// Puces de sélection multiple (véhicules, services).
class ChoiceChips<T> extends StatelessWidget {
  const ChoiceChips({
    super.key,
    required this.options,
    required this.label,
    required this.selected,
    required this.onChanged,
  });

  final List<T> options;
  final String Function(T option) label;
  final Set<T> selected;
  final ValueChanged<Set<T>> onChanged;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final option in options)
          FilterChip(
            label: Text(label(option)),
            selected: selected.contains(option),
            onSelected: (isSelected) => onChanged(
              isSelected
                  ? {...selected, option}
                  : selected.difference({option}),
            ),
          ),
      ],
    );
  }
}
