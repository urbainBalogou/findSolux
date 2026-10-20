import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';

import '../../../core/maps/native_map.dart';

/// Ajustement manuel de la position du garage : la carte glisse sous un
/// repère fixe au centre. Renvoie la position choisie, ou null si annulé.
class PositionPickerScreen extends ConsumerStatefulWidget {
  const PositionPickerScreen({super.key, required this.initial});

  final LatLng initial;

  @override
  ConsumerState<PositionPickerScreen> createState() =>
      _PositionPickerScreenState();
}

class _PositionPickerScreenState extends ConsumerState<PositionPickerScreen> {
  late LatLng _target = widget.initial;
  var _satellite = false;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Position du garage'),
        actions: [
          IconButton(
            icon: Icon(_satellite ? Icons.map_outlined : Icons.satellite_alt),
            tooltip: _satellite ? 'Plan' : 'Vue satellite',
            onPressed: () => setState(() => _satellite = !_satellite),
          ),
        ],
      ),
      body: Stack(
        children: [
          Positioned.fill(
            child: ref.watch(nativeMapEnabledProvider)
                ? GoogleMap(
                    initialCameraPosition: CameraPosition(
                      target: widget.initial,
                      zoom: 18,
                    ),
                    mapType: _satellite ? MapType.hybrid : MapType.normal,
                    zoomControlsEnabled: false,
                    mapToolbarEnabled: false,
                    myLocationButtonEnabled: false,
                    onCameraMove: (position) => _target = position.target,
                  )
                : const ColoredBox(color: Color(0xFFE8EAE6)),
          ),
          // Pointe du repère au centre exact de la carte.
          IgnorePointer(
            child: Center(
              child: Padding(
                padding: const EdgeInsets.only(bottom: 48),
                child: Icon(
                  Icons.location_pin,
                  size: 48,
                  color: theme.colorScheme.error,
                ),
              ),
            ),
          ),
          const Positioned(
            top: 12,
            left: 12,
            right: 12,
            child: Card(
              child: Padding(
                padding: EdgeInsets.all(12),
                child: Text(
                  'Faites glisser la carte pour placer le repère sur '
                  'l’entrée du garage.',
                ),
              ),
            ),
          ),
          Positioned(
            left: 16,
            right: 16,
            bottom: 24,
            child: SafeArea(
              child: FilledButton(
                onPressed: () => Navigator.of(context).pop(_target),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                ),
                child: const Text('Valider cette position'),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
