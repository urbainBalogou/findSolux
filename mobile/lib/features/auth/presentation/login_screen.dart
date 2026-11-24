import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/router/app_router.dart';
import '../application/auth_controller.dart';
import '../data/google_auth_client.dart';

/// Connexion avec un compte Google (ADR-017). Une fois la session ouverte,
/// le routeur demande le numéro s'il manque, puis rouvre la page d'origine.
class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  String? _error;
  bool _busy = false;

  Future<void> _signIn() async {
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await ref.read(authControllerProvider.notifier).signInWithGoogle();
    } on GoogleAuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
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
        body: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            Text(
              'Connectez-vous avec votre compte Google pour contacter un '
              'garage, envoyer un signal de détresse ou gérer votre garage.',
              style: theme.textTheme.bodyLarge,
            ),
            const SizedBox(height: 24),
            FilledButton.icon(
              onPressed: _busy ? null : _signIn,
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(52),
              ),
              icon: _busy
                  ? const SizedBox.square(
                      dimension: 24,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.account_circle_outlined),
              label: const Text('Continuer avec Google'),
            ),
            if (_error != null) ...[
              const SizedBox(height: 16),
              Text(
                _error!,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.error,
                ),
              ),
            ],
            const SizedBox(height: 32),
            const Divider(),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: _busy
                  ? null
                  : () => context.push(AppRoutes.teamMemberLogin),
              style: OutlinedButton.styleFrom(
                minimumSize: const Size.fromHeight(52),
              ),
              icon: const Icon(Icons.badge_outlined),
              label: const Text('Je suis employé ou répondeur d’un garage'),
            ),
          ],
        ),
      ),
    );
  }
}
