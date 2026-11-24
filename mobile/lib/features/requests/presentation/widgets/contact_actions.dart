import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../garage/application/contact_launcher.dart';

/// Appeler, WhatsApp et Itinéraire vers un contact échangé (garage ou client).
class ContactActions extends ConsumerWidget {
  const ContactActions({
    super.key,
    required this.phone,
    this.whatsappPhone,
    this.whatsappMessage = 'Bonjour, je vous contacte via MecaDispo.',
    this.latitude,
    this.longitude,
  });

  final String phone;
  final String? whatsappPhone;
  final String whatsappMessage;
  final double? latitude;
  final double? longitude;

  Future<void> _open(
    BuildContext context,
    Future<bool> Function(ContactLauncher launcher) open,
    String failure,
    WidgetRef ref,
  ) async {
    final opened = await open(ref.read(contactLauncherProvider));
    if (!opened && context.mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(failure)));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        FilledButton.icon(
          onPressed: () => _open(
            context,
            (launcher) => launcher.call(phone),
            'Impossible d’ouvrir le téléphone.',
            ref,
          ),
          icon: const Icon(Icons.call),
          label: const Text('Appeler'),
        ),
        if (whatsappPhone != null)
          FilledButton.tonalIcon(
            onPressed: () => _open(
              context,
              (launcher) => launcher.whatsApp(whatsappPhone!, whatsappMessage),
              'WhatsApp ne s’est pas ouvert. Est-il installé sur ce téléphone ?',
              ref,
            ),
            icon: const Icon(Icons.chat),
            label: const Text('WhatsApp'),
          ),
        if (latitude != null && longitude != null)
          OutlinedButton.icon(
            onPressed: () => _open(
              context,
              (launcher) => launcher.directions(latitude!, longitude!),
              'Impossible d’ouvrir l’itinéraire.',
              ref,
            ),
            icon: const Icon(Icons.directions),
            label: const Text('Itinéraire'),
          ),
      ],
    );
  }
}
