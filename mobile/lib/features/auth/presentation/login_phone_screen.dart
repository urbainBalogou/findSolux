import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/router/app_router.dart';
import '../data/auth_repository.dart';

/// Connexion, étape 1 : saisie du numéro de téléphone.
class LoginPhoneScreen extends ConsumerStatefulWidget {
  const LoginPhoneScreen({super.key, this.from});

  /// Page à rouvrir une fois connecté.
  final String? from;

  @override
  ConsumerState<LoginPhoneScreen> createState() => _LoginPhoneScreenState();
}

class _LoginPhoneScreenState extends ConsumerState<LoginPhoneScreen> {
  final _phoneController = TextEditingController();
  String? _error;
  bool _sending = false;

  @override
  void dispose() {
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final digits = _phoneController.text.replaceAll(RegExp(r'\D'), '');
    if (digits.length != 8) {
      setState(() => _error = 'Le numéro doit contenir 8 chiffres.');
      return;
    }

    final phone = '+228$digits';
    setState(() {
      _sending = true;
      _error = null;
    });

    try {
      final result = await ref.read(authRepositoryProvider).requestCode(phone);
      if (!mounted) return;
      context.push(
        AppRoutes.loginCodeLocation(phone: phone, from: widget.from),
        extra: result,
      );
    } on ApiException catch (error) {
      setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    // Ouvert par une redirection (espace garagiste), l'écran est seul dans la
    // pile : le retour Android ramène à l'accueil au lieu de fermer l'app.
    final canPop = context.canPop();

    return PopScope(
      canPop: canPop,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) context.go(AppRoutes.search);
      },
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Connexion'),
          leading: canPop
              ? null
              : IconButton(
                  icon: const Icon(Icons.close),
                  tooltip: 'Fermer',
                  onPressed: () => context.go(AppRoutes.search),
                ),
        ),
        body: _buildForm(theme),
      ),
    );
  }

  Widget _buildForm(ThemeData theme) {
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Text(
          'Entrez votre numéro de téléphone. '
          'Nous vous envoyons un code par SMS.',
          style: theme.textTheme.bodyLarge,
        ),
        const SizedBox(height: 24),
        TextField(
          controller: _phoneController,
          autofocus: true,
          keyboardType: TextInputType.phone,
          autofillHints: const [AutofillHints.telephoneNumberNational],
          inputFormatters: [
            FilteringTextInputFormatter.allow(RegExp(r'[\d ]')),
            LengthLimitingTextInputFormatter(11),
          ],
          style: theme.textTheme.titleLarge,
          decoration: InputDecoration(
            labelText: 'Numéro de téléphone',
            prefixText: '+228 ',
            hintText: '90 12 34 56',
            errorText: _error,
            errorMaxLines: 3,
            border: const OutlineInputBorder(),
          ),
          onSubmitted: (_) => _submit(),
        ),
        const SizedBox(height: 24),
        FilledButton(
          onPressed: _sending ? null : _submit,
          style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52)),
          child: _sending
              ? const SizedBox.square(
                  dimension: 24,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Text('Recevoir le code'),
        ),
      ],
    );
  }
}
