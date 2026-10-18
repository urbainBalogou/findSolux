import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

class LocationFix {
  const LocationFix({
    required this.latitude,
    required this.longitude,
    required this.accuracyMeters,
  });

  final double latitude;
  final double longitude;
  final double accuracyMeters;
}

/// Erreur de localisation, avec un message prêt à afficher.
class LocationException implements Exception {
  const LocationException(this.message);

  final String message;

  @override
  String toString() => message;
}

class LocationService {
  /// Position actuelle du téléphone. Lève [LocationException] si le GPS est
  /// coupé ou l'autorisation refusée.
  Future<LocationFix> currentPosition() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const LocationException(
        'Activez la localisation (GPS) du téléphone, puis réessayez.',
      );
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied) {
      throw const LocationException(
        "L'application a besoin de votre position pour situer le garage.",
      );
    }
    if (permission == LocationPermission.deniedForever) {
      throw const LocationException(
        'Autorisez la localisation pour findSolux dans les réglages du téléphone.',
      );
    }

    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 30),
        ),
      );
      return LocationFix(
        latitude: position.latitude,
        longitude: position.longitude,
        accuracyMeters: position.accuracy,
      );
    } on Exception {
      throw const LocationException(
        "Position introuvable. Placez-vous à l'extérieur et réessayez.",
      );
    }
  }
}

final locationServiceProvider = Provider<LocationService>(
  (ref) => LocationService(),
);
