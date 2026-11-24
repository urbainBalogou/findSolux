import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/router/app_router.dart';
import '../../../core/widgets/async_view.dart';
import '../application/my_business_controller.dart';
import '../data/team_repository.dart';
import '../domain/team.dart';

/// Équipe du garage : employés ou répondeurs qui reçoivent les demandes et les
/// acceptent au nom du garage. Le garagiste crée leur identifiant et leur mot
/// de passe, dans la limite de ses packs « Équipe ».
class TeamScreen extends ConsumerWidget {
  const TeamScreen({super.key});

  static const _minimumPasswordLength = 8;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mon équipe')),
      body: AsyncView(
        value: ref.watch(teamProvider),
        onRetry: () => ref.invalidate(teamProvider),
        builder: (team) => RefreshIndicator(
          onRefresh: () => ref.refresh(teamProvider.future),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
            children: [
              const _TeamIntroduction(),
              const SizedBox(height: 16),
              Text(
                team.quota == 0
                    ? 'Aucun pack Équipe actif'
                    : '${team.members.length} / ${team.quota} '
                          'membre${team.quota > 1 ? 's' : ''}',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              for (final member in team.members)
                _MemberCard(
                  member: member,
                  onChangePassword: () => _changePassword(context, ref, member),
                  onRemove: () => _remove(context, ref, member),
                ),
              const SizedBox(height: 8),
              if (team.isFull)
                _QuotaReached(quota: team.quota)
              else
                FilledButton.icon(
                  onPressed: () => _addMember(context, ref),
                  icon: const Icon(Icons.person_add_alt_1_outlined),
                  label: const Text('Ajouter un membre'),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _addMember(BuildContext context, WidgetRef ref) async {
    final created = await showDialog<(TeamMember, String)>(
      context: context,
      builder: (context) => const _NewMemberDialog(),
    );
    if (created == null || !context.mounted) return;

    _refresh(ref);
    final (member, password) = created;
    await showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('${member.name} fait partie de l’équipe'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Transmettez-lui ces informations de connexion :'),
            const SizedBox(height: 12),
            SelectableText('Identifiant : ${member.username}'),
            SelectableText('Mot de passe : $password'),
            const SizedBox(height: 12),
            const Text(
              'Dans MecaDispo, il choisit « Je suis employé ou répondeur d’un '
              'garage » sur l’écran de connexion.',
            ),
          ],
        ),
        actions: [
          FilledButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('C’est noté'),
          ),
        ],
      ),
    );
  }

  Future<void> _changePassword(
    BuildContext context,
    WidgetRef ref,
    TeamMember member,
  ) async {
    final changed = await showDialog<bool>(
      context: context,
      builder: (context) => _NewPasswordDialog(member: member),
    );
    if (changed != true || !context.mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          'Mot de passe changé : ${member.name} doit se reconnecter avec le '
          'nouveau.',
        ),
      ),
    );
  }

  Future<void> _remove(
    BuildContext context,
    WidgetRef ref,
    TeamMember member,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Retirer ${member.name} de l’équipe ?'),
        content: const Text(
          'Il ne pourra plus se connecter ni répondre aux demandes du garage.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Retirer'),
          ),
        ],
      ),
    );
    if (confirmed != true || !context.mounted) return;

    try {
      await ref.read(teamRepositoryProvider).remove(member.id);
      _refresh(ref);
    } on ApiException catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  /// L'accueil garagiste affiche aussi le nombre de membres.
  void _refresh(WidgetRef ref) {
    ref.invalidate(teamProvider);
    ref.invalidate(myBusinessProvider);
  }
}

class _TeamIntroduction extends StatelessWidget {
  const _TeamIntroduction();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Card(
      color: colors.primaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.groups_outlined, color: colors.primary),
            const SizedBox(width: 12),
            const Expanded(
              child: Text(
                'Vos employés ou répondeurs reçoivent les demandes des clients '
                'et peuvent les accepter au nom du garage, quand vous êtes '
                'occupé. Chaque acceptation est prélevée sur votre portefeuille.',
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MemberCard extends StatelessWidget {
  const _MemberCard({
    required this.member,
    required this.onChangePassword,
    required this.onRemove,
  });

  final TeamMember member;
  final VoidCallback onChangePassword;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: ListTile(
        leading: Icon(
          member.isActive ? Icons.badge_outlined : Icons.pause_circle_outline,
        ),
        title: Text(member.name),
        subtitle: Text(
          member.isActive
              ? 'Identifiant : ${member.username}'
              : 'Identifiant : ${member.username} · suspendu, pack expiré',
          style: member.isActive
              ? null
              : TextStyle(color: theme.colorScheme.error),
        ),
        trailing: PopupMenuButton<VoidCallback>(
          tooltip: 'Actions pour ${member.name}',
          onSelected: (action) => action(),
          itemBuilder: (context) => [
            PopupMenuItem(
              value: onChangePassword,
              child: const Text('Nouveau mot de passe'),
            ),
            PopupMenuItem(
              value: onRemove,
              child: const Text('Retirer de l’équipe'),
            ),
          ],
        ),
      ),
    );
  }
}

class _QuotaReached extends StatelessWidget {
  const _QuotaReached({required this.quota});

  final int quota;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              quota == 0
                  ? 'Achetez un pack « Employés et répondeurs » pour ajouter '
                        'des membres à votre équipe.'
                  : 'Votre équipe est complète. Achetez un pack supplémentaire '
                        'pour ajouter un membre.',
            ),
            const SizedBox(height: 12),
            FilledButton.tonal(
              onPressed: () => context.go(AppRoutes.proSubscription),
              child: const Text('Voir les offres'),
            ),
          ],
        ),
      ),
    );
  }
}

/// Création d'un membre : renvoie le membre créé et son mot de passe, à lui
/// transmettre.
class _NewMemberDialog extends ConsumerStatefulWidget {
  const _NewMemberDialog();

  @override
  ConsumerState<_NewMemberDialog> createState() => _NewMemberDialogState();
}

class _NewMemberDialogState extends ConsumerState<_NewMemberDialog> {
  final _nameController = TextEditingController();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  String? _error;
  var _busy = false;

  @override
  void dispose() {
    _nameController.dispose();
    _usernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final name = _nameController.text.trim();
    final username = _usernameController.text.trim().toLowerCase();
    final password = _passwordController.text;

    String? error;
    if (name.isEmpty) {
      error = 'Saisissez le nom du membre.';
    } else if (username.length < 3) {
      error = 'L’identifiant doit contenir au moins 3 caractères.';
    } else if (password.length < TeamScreen._minimumPasswordLength) {
      error =
          'Le mot de passe doit contenir au moins '
          '${TeamScreen._minimumPasswordLength} caractères.';
    }
    if (error != null) {
      setState(() => _error = error);
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final member = await ref
          .read(teamRepositoryProvider)
          .create(
            NewTeamMember(name: name, username: username, password: password),
          );
      if (mounted) Navigator.pop(context, (member, password));
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Ajouter un membre'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _nameController,
              enabled: !_busy,
              autofocus: true,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                labelText: 'Nom',
                hintText: 'Kodjo',
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _usernameController,
              enabled: !_busy,
              autocorrect: false,
              inputFormatters: [
                FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z0-9._-]')),
                LengthLimitingTextInputFormatter(30),
              ],
              decoration: const InputDecoration(
                labelText: 'Identifiant',
                hintText: 'kodjo.garage',
                helperText: 'Lettres sans accent, chiffres, points, tirets',
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _passwordController,
              enabled: !_busy,
              autocorrect: false,
              enableSuggestions: false,
              decoration: const InputDecoration(
                labelText: 'Mot de passe',
                helperText:
                    'Au moins ${TeamScreen._minimumPasswordLength} caractères',
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _busy ? null : () => Navigator.pop(context),
          child: const Text('Annuler'),
        ),
        FilledButton(
          onPressed: _busy ? null : _submit,
          child: const Text('Créer'),
        ),
      ],
    );
  }
}

class _NewPasswordDialog extends ConsumerStatefulWidget {
  const _NewPasswordDialog({required this.member});

  final TeamMember member;

  @override
  ConsumerState<_NewPasswordDialog> createState() => _NewPasswordDialogState();
}

class _NewPasswordDialogState extends ConsumerState<_NewPasswordDialog> {
  final _passwordController = TextEditingController();
  String? _error;
  var _busy = false;

  @override
  void dispose() {
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final password = _passwordController.text;
    if (password.length < TeamScreen._minimumPasswordLength) {
      setState(
        () => _error =
            'Le mot de passe doit contenir au moins '
            '${TeamScreen._minimumPasswordLength} caractères.',
      );
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref
          .read(teamRepositoryProvider)
          .changePassword(widget.member.id, password);
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text('Nouveau mot de passe pour ${widget.member.name}'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            controller: _passwordController,
            enabled: !_busy,
            autofocus: true,
            autocorrect: false,
            enableSuggestions: false,
            decoration: InputDecoration(
              labelText: 'Mot de passe',
              helperText:
                  'Au moins ${TeamScreen._minimumPasswordLength} caractères. '
                  'Il sera déconnecté de son téléphone.',
              helperMaxLines: 2,
              errorText: _error,
              errorMaxLines: 3,
            ),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: _busy ? null : () => Navigator.pop(context, false),
          child: const Text('Annuler'),
        ),
        FilledButton(
          onPressed: _busy ? null : _submit,
          child: const Text('Enregistrer'),
        ),
      ],
    );
  }
}
