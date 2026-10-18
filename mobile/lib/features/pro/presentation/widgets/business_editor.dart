import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/network/api_exception.dart';
import '../../../../core/widgets/async_view.dart';
import '../../../reference/data/reference_repository.dart';
import '../../../reference/domain/reference_data.dart';
import '../../application/my_business_controller.dart';
import '../../domain/my_business.dart';

/// Écran de modification d'une section de la fiche : attend la fiche et les
/// référentiels, puis affiche le formulaire.
class BusinessEditor extends ConsumerWidget {
  const BusinessEditor({super.key, required this.title, required this.builder});

  final String title;
  final Widget Function(MyBusiness business, ReferenceData reference) builder;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: AsyncView(
        value: ref.watch(referenceDataProvider),
        onRetry: () => ref.invalidate(referenceDataProvider),
        builder: (reference) => AsyncView(
          value: ref.watch(myBusinessProvider),
          onRetry: () => ref.invalidate(myBusinessProvider),
          builder: (business) => business == null
              ? const Center(child: Text('Inscrivez d’abord votre garage.'))
              : builder(business, reference),
        ),
      ),
    );
  }
}

/// Enregistrement d'un formulaire de la fiche : bouton, erreur et retour à
/// la fiche une fois la modification acceptée par l'API.
mixin BusinessFormSaving<T extends StatefulWidget> on State<T> {
  String? saveError;
  var saving = false;

  Future<void> save(Future<void> Function() action) async {
    setState(() {
      saving = true;
      saveError = null;
    });
    try {
      await action();
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Fiche mise à jour.')));
      context.pop();
    } on ApiException catch (error) {
      if (mounted) setState(() => saveError = error.message);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Widget saveButton(VoidCallback onPressed) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (saveError != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Text(
              saveError!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ),
        FilledButton(
          onPressed: saving ? null : onPressed,
          style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52)),
          child: saving
              ? const SizedBox.square(
                  dimension: 24,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Text('Enregistrer'),
        ),
        const SizedBox(height: 24),
      ],
    );
  }
}
