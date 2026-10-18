import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/location/location_service.dart';

/// Prise de la position du garage, sur place, par le GPS du téléphone.
class LocationCapture extends ConsumerStatefulWidget {
  const LocationCapture({
    super.key,
    required this.value,
    required this.onChanged,
  });

  /// Position actuelle ; une précision de 0 signifie « déjà enregistrée ».
  final LocationFix? value;
  final ValueChanged<LocationFix> onChanged;

  @override
  ConsumerState<LocationCapture> createState() => _LocationCaptureState();
}

class _LocationCaptureState extends ConsumerState<LocationCapture> {
  static const _goodAccuracyMeters = 50;

  bool _locating = false;
  String? _error;

  Future<void> _capture() async {
    setState(() {
      _locating = true;
      _error = null;
    });
    try {
      final fix = await ref.read(locationServiceProvider).currentPosition();
      widget.onChanged(fix);
    } on LocationException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final fix = widget.value;

    if (_locating) {
      return const ListTile(
        leading: SizedBox.square(
          dimension: 24,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
        title: Text('Recherche de la position…'),
        subtitle: Text('Restez immobile quelques secondes.'),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (fix == null)
          OutlinedButton.icon(
            onPressed: _capture,
            icon: const Icon(Icons.my_location),
            label: const Text('Je suis au garage : prendre la position'),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(52),
            ),
          )
        else ...[
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: Icon(Icons.check_circle, color: theme.colorScheme.primary),
            title: const Text('Position enregistrée'),
            subtitle: fix.accuracyMeters > 0
                ? Text('Précision : ${fix.accuracyMeters.round()} m')
                : null,
            trailing: TextButton(
              onPressed: _capture,
              child: const Text('Reprendre'),
            ),
          ),
          if (fix.accuracyMeters > _goodAccuracyMeters)
            Text(
              'Position peu précise. Placez-vous dehors, devant le garage, '
              'puis reprenez la position.',
              style: TextStyle(color: theme.colorScheme.error),
            ),
        ],
        if (_error != null)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(
              _error!,
              style: TextStyle(color: theme.colorScheme.error),
            ),
          ),
      ],
    );
  }
}
