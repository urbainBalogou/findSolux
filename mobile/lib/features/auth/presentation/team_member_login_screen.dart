import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../application/auth_controller.dart';

/// Connexion d'un membre de l'équipe d'un garage (employé ou répondeur), avec
/// l'identifiant et le mot de passe créés par le garagiste. Une fois connecté,
/// le routeur ouvre les demandes du garage.
class TeamMemberLoginScreen extends ConsumerStatefulWidget {
  const TeamMemberLoginScreen({super.key});

  @override
  ConsumerState<TeamMemberLoginScreen> createState() =>
      _TeamMemberLoginScreenState();
}

class _TeamMemberLoginScreenState extends ConsumerState<TeamMemberLoginScreen> {
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  var _obscurePassword = true;
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _usernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final username = _usernameController.text.trim();
    final password = _passwordController.text;
    if (username.isEmpty || password.isEmpty) {
      setState(
        () => _error = 'Saisissez votre identifiant et votre mot de passe.',
      );
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await ref
          .read(authControllerProvider.notifier)
          .signInAsTeamMember(username: username, password: password);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Membre d’une équipe')),
      body: AutofillGroup(
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            Text(
              'Employé ou répondeur d’un garage : connectez-vous avec '
              'l’identifiant et le mot de passe que votre responsable vous a '
              'donnés.',
              style: theme.textTheme.bodyLarge,
            ),
            const SizedBox(height: 24),
            TextField(
              controller: _usernameController,
              enabled: !_busy,
              autofocus: true,
              autocorrect: false,
              textInputAction: TextInputAction.next,
              autofillHints: const [AutofillHints.username],
              decoration: const InputDecoration(
                labelText: 'Identifiant',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _passwordController,
              enabled: !_busy,
              obscureText: _obscurePassword,
              autocorrect: false,
              enableSuggestions: false,
              autofillHints: const [AutofillHints.password],
              decoration: InputDecoration(
                labelText: 'Mot de passe',
                border: const OutlineInputBorder(),
                suffixIcon: IconButton(
                  tooltip: _obscurePassword
                      ? 'Afficher le mot de passe'
                      : 'Masquer le mot de passe',
                  icon: Icon(
                    _obscurePassword ? Icons.visibility : Icons.visibility_off,
                  ),
                  onPressed: () =>
                      setState(() => _obscurePassword = !_obscurePassword),
                ),
              ),
              onSubmitted: (_) => _submit(),
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
                  : const Text('Se connecter'),
            ),
          ],
        ),
      ),
    );
  }
}
