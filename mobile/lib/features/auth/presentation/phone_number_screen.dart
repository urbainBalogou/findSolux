import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/network/api_exception.dart';
import '../application/auth_controller.dart';

/// Numéro où les garages joindront l'utilisateur (ADR-017) : demandé juste
/// après la première connexion Google, modifiable ensuite depuis le compte.
class PhoneNumberScreen extends ConsumerStatefulWidget {
  const PhoneNumberScreen({super.key, this.editing = false});

  /// Modification depuis le compte. Sinon, le numéro est obligatoire pour
  /// continuer : le routeur rouvre la page d'origine une fois enregistré.
  final bool editing;

  @override
  ConsumerState<PhoneNumberScreen> createState() => _PhoneNumberScreenState();
}

class _PhoneNumberScreenState extends ConsumerState<PhoneNumberScreen> {
  late final _phoneController = TextEditingController(
    text: localDigits(ref.read(authControllerProvider).value?.user.phone),
  );
  String? _error;
  bool _busy = false;

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

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await ref
          .read(authControllerProvider.notifier)
          .updatePhone('+228$digits');
      if (!mounted || !widget.editing) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Numéro enregistré.')));
      context.pop();
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// Sans numéro, le compte ne peut pas servir : on propose d'en changer.
  Future<void> _useAnotherAccount() async {
    setState(() => _busy = true);
    await ref.read(authControllerProvider.notifier).logout();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final email = ref.watch(authControllerProvider).value?.user.email;

    return PopScope(
      canPop: widget.editing,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && !_busy) _useAnotherAccount();
      },
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Votre numéro'),
          automaticallyImplyLeading: widget.editing,
        ),
        body: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            Text(
              'Le garage qui accepte votre demande vous appellera ou vous '
              'écrira sur WhatsApp à ce numéro.',
              style: theme.textTheme.bodyLarge,
            ),
            const SizedBox(height: 24),
            TextField(
              controller: _phoneController,
              autofocus: true,
              enabled: !_busy,
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
              onPressed: _busy ? null : _submit,
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(52),
              ),
              child: _busy
                  ? const SizedBox.square(
                      dimension: 24,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(widget.editing ? 'Enregistrer' : 'Continuer'),
            ),
            if (!widget.editing) ...[
              const SizedBox(height: 8),
              TextButton(
                onPressed: _busy ? null : _useAnotherAccount,
                child: Text(
                  email == null
                      ? 'Utiliser un autre compte'
                      : 'Utiliser un autre compte que $email',
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
