import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/location/location_service.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/app_router.dart';
import '../../reference/data/reference_repository.dart';
import '../data/requests_repository.dart';
import '../domain/requests.dart';
import 'widgets/contact_actions.dart';

/// Signal de détresse (C5) : le client décrit sa panne, sa position est
/// envoyée aux garages qui interviennent dans sa zone ; le premier qui
/// accepte lui transmet son numéro.
class DistressSignalScreen extends ConsumerStatefulWidget {
  const DistressSignalScreen({super.key, this.signalId});

  /// Signal à afficher (ouvert depuis une notification).
  final String? signalId;

  /// Fréquence de vérification d'un signal en attente.
  static const pollInterval = Duration(seconds: 5);

  @override
  ConsumerState<DistressSignalScreen> createState() =>
      _DistressSignalScreenState();
}

class _DistressSignalScreenState extends ConsumerState<DistressSignalScreen> {
  static const _suggestions = [
    'Crevaison',
    'Batterie à plat',
    'Le moteur ne démarre pas',
    'Panne d’essence',
    'Accident',
  ];

  final _message = TextEditingController();
  int? _serviceId;
  int? _vehicleTypeId;

  DistressSignal? _signal;
  var _loading = true;
  var _sending = false;
  String? _error;
  Timer? _poll;
  Timer? _clock;

  @override
  void initState() {
    super.initState();
    _loadCurrent();
  }

  @override
  void dispose() {
    _poll?.cancel();
    _clock?.cancel();
    _message.dispose();
    super.dispose();
  }

  /// Reprend le signal en cours, s'il y en a un.
  Future<void> _loadCurrent() async {
    final repository = ref.read(requestsRepositoryProvider);
    try {
      final signal = widget.signalId != null
          ? await repository.fetchDistressSignal(widget.signalId!)
          : (await repository.fetchDistressSignals())
                .where((signal) => signal.isOpen)
                .firstOrNull;
      if (!mounted) return;
      setState(() => _loading = false);
      if (signal != null) _show(signal);
    } on ApiException {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _show(DistressSignal signal) {
    setState(() => _signal = signal);
    _poll?.cancel();
    _clock?.cancel();
    if (!signal.isOpen) return;

    _poll = Timer.periodic(
      DistressSignalScreen.pollInterval,
      (_) => _refresh(),
    );
    _clock = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      if (_signal?.isOpen == true) {
        setState(() {});
      } else {
        _refresh();
      }
    });
  }

  Future<void> _refresh() async {
    final current = _signal;
    if (current == null) return;
    try {
      final signal = await ref
          .read(requestsRepositoryProvider)
          .fetchDistressSignal(current.id);
      if (mounted && signal.status != current.status) _show(signal);
    } on ApiException {
      // Réseau instable : nouvel essai au prochain passage.
    }
  }

  Future<void> _send() async {
    final message = _message.text.trim();
    if (message.length < 3) {
      setState(() => _error = 'Décrivez votre problème en quelques mots.');
      return;
    }

    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      final position = await ref
          .read(locationServiceProvider)
          .currentPosition();
      final signal = await ref
          .read(requestsRepositoryProvider)
          .sendDistressSignal(
            NewDistressSignal(
              message: message,
              latitude: position.latitude,
              longitude: position.longitude,
              serviceId: _serviceId,
              vehicleTypeId: _vehicleTypeId,
            ),
          );
      if (mounted) _show(signal);
    } on LocationException catch (error) {
      setState(() => _error = error.message);
    } on ApiException catch (error) {
      if (error.code == 'already_open') {
        await _loadCurrent();
      } else {
        setState(() => _error = error.message);
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _cancel() async {
    final signal = _signal;
    if (signal == null) return;
    try {
      final cancelled = await ref
          .read(requestsRepositoryProvider)
          .cancelDistressSignal(signal.id);
      if (mounted) _show(cancelled);
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  void _newSignal() {
    _poll?.cancel();
    _clock?.cancel();
    setState(() => _signal = null);
  }

  @override
  Widget build(BuildContext context) {
    final signal = _signal;

    return Scaffold(
      appBar: AppBar(title: const Text('Signal de détresse')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : switch (signal?.status) {
              null || DistressSignalStatus.cancelled => _buildForm(context),
              DistressSignalStatus.open when signal!.isOpen => _Waiting(
                signal: signal,
                onCancel: _cancel,
              ),
              DistressSignalStatus.accepted => _Accepted(signal: signal!),
              _ => _Unanswered(onRetry: _newSignal),
            },
    );
  }

  Widget _buildForm(BuildContext context) {
    final theme = Theme.of(context);
    final reference = ref.watch(referenceDataProvider).value;
    final services = [
      for (final trade in reference?.trades ?? const []) ...trade.services,
    ];

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
      children: [
        Card(
          color: theme.colorScheme.errorContainer,
          child: const ListTile(
            leading: Icon(Icons.sos),
            title: Text('En panne ? Prévenez les garages autour de vous.'),
            subtitle: Text(
              'Votre position et votre message sont envoyés aux garages qui se '
              'déplacent dans votre zone. Le premier qui accepte vous appelle.',
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text('Que se passe-t-il ?', style: theme.textTheme.titleMedium),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 4,
          children: [
            for (final suggestion in _suggestions)
              ActionChip(
                label: Text(suggestion),
                onPressed: () => setState(() => _message.text = suggestion),
              ),
          ],
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _message,
          maxLength: 500,
          maxLines: 3,
          decoration: const InputDecoration(
            labelText: 'Votre problème',
            hintText: 'Ex. : crevaison du pneu avant, carrefour Bè',
            border: OutlineInputBorder(),
          ),
        ),
        if (reference != null) ...[
          const SizedBox(height: 8),
          Text('Véhicule', style: theme.textTheme.titleSmall),
          Wrap(
            spacing: 8,
            children: [
              for (final type in reference.vehicleTypes)
                ChoiceChip(
                  label: Text(type.name),
                  selected: _vehicleTypeId == type.id,
                  onSelected: (selected) => setState(
                    () => _vehicleTypeId = selected ? type.id : null,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<int?>(
            initialValue: _serviceId,
            decoration: const InputDecoration(
              labelText: 'Service recherché (facultatif)',
              border: OutlineInputBorder(),
            ),
            items: [
              const DropdownMenuItem(
                value: null,
                child: Text('Je ne sais pas'),
              ),
              for (final service in services)
                DropdownMenuItem(value: service.id, child: Text(service.name)),
            ],
            onChanged: (value) => setState(() => _serviceId = value),
          ),
        ],
        if (_error != null) ...[
          const SizedBox(height: 12),
          Text(_error!, style: TextStyle(color: theme.colorScheme.error)),
        ],
        const SizedBox(height: 16),
        FilledButton.icon(
          onPressed: _sending ? null : _send,
          style: FilledButton.styleFrom(
            minimumSize: const Size.fromHeight(56),
            backgroundColor: theme.colorScheme.error,
            foregroundColor: theme.colorScheme.onError,
          ),
          icon: _sending
              ? const SizedBox.square(
                  dimension: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.campaign),
          label: const Text('Envoyer le signal'),
        ),
      ],
    );
  }
}

class _Waiting extends StatelessWidget {
  const _Waiting({required this.signal, required this.onCancel});

  final DistressSignal signal;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final remaining = signal.expiresAt.difference(DateTime.now());
    final minutes = remaining.inMinutes;
    final seconds = (remaining.inSeconds % 60).toString().padLeft(2, '0');
    final garages = signal.recipientsCount;

    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const SizedBox(height: 24),
        const Center(
          child: SizedBox.square(
            dimension: 64,
            child: CircularProgressIndicator(),
          ),
        ),
        const SizedBox(height: 24),
        Text(
          'Signal envoyé à $garages garage${garages > 1 ? 's' : ''}',
          textAlign: TextAlign.center,
          style: theme.textTheme.titleLarge,
        ),
        const SizedBox(height: 8),
        Text(
          'En attente d’une réponse… ($minutes:$seconds)',
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 16),
        Card(
          child: ListTile(
            leading: const Icon(Icons.chat_bubble_outline),
            title: Text(signal.message),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          'Restez joignable : le garage qui accepte reçoit votre numéro et votre position.',
          textAlign: TextAlign.center,
          style: theme.textTheme.bodySmall,
        ),
        const SizedBox(height: 24),
        OutlinedButton(
          onPressed: onCancel,
          child: const Text('Annuler le signal'),
        ),
      ],
    );
  }
}

class _Accepted extends ConsumerWidget {
  const _Accepted({required this.signal});

  final DistressSignal signal;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final garage = signal.garage!;
    final district = ref
        .watch(referenceDataProvider)
        .value
        ?.districtById(garage.districtId)
        ?.name;

    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Icon(Icons.check_circle, size: 64, color: theme.colorScheme.primary),
        const SizedBox(height: 16),
        Text(
          '${garage.name} a accepté votre demande d’aide',
          textAlign: TextAlign.center,
          style: theme.textTheme.titleLarge,
        ),
        if (district != null) Text(district, textAlign: TextAlign.center),
        const SizedBox(height: 8),
        const Text(
          'Le garage a reçu votre numéro et votre position. Vous pouvez aussi l’appeler.',
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 24),
        Center(
          child: ContactActions(
            phone: garage.phone!,
            whatsappPhone: garage.whatsappPhone,
            whatsappMessage:
                'Bonjour ${garage.name}, je suis le client du signal de détresse sur MecaDispo.',
            latitude: garage.latitude,
            longitude: garage.longitude,
          ),
        ),
        const SizedBox(height: 24),
        TextButton(
          onPressed: () => context.go(AppRoutes.myGarages),
          child: const Text('Retrouver ce garage dans « Mes garages »'),
        ),
      ],
    );
  }
}

class _Unanswered extends StatelessWidget {
  const _Unanswered({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const Icon(Icons.schedule, size: 64),
        const SizedBox(height: 16),
        Text(
          'Aucun garage n’a répondu à temps',
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: 8),
        const Text(
          'Vous pouvez envoyer un nouveau signal ou chercher un garage proche sur la carte.',
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 24),
        FilledButton(
          onPressed: onRetry,
          child: const Text('Envoyer un nouveau signal'),
        ),
        const SizedBox(height: 8),
        OutlinedButton(
          onPressed: () => context.go(AppRoutes.search),
          child: const Text('Chercher sur la carte'),
        ),
      ],
    );
  }
}
