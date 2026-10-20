import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/device/device_id.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/public_business.dart';

/// Fiches publiques et statistiques. Lève [ApiException] en cas d'échec.
class GarageRepository {
  GarageRepository(this._dio);

  final Dio _dio;

  Future<PublicBusiness> fetch(String slug) {
    return guardApi(() async {
      final response = await _dio.get<Map<String, dynamic>>(
        '/businesses/$slug',
      );
      return PublicBusiness.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    });
  }

  Future<void> recordEvent(String slug, GarageEvent event, String deviceId) {
    return guardApi(
      () => _dio.post<void>(
        '/businesses/$slug/events',
        data: {'type': event.name, 'device_id': deviceId},
      ),
    );
  }
}

final garageRepositoryProvider = Provider<GarageRepository>(
  (ref) => GarageRepository(ref.watch(dioProvider)),
);

final garageDetailProvider = FutureProvider.family<PublicBusiness, String>(
  (ref, slug) => ref.watch(garageRepositoryProvider).fetch(slug),
);

/// Envoi des vues et des clics, sans faire attendre le client : un échec
/// (réseau coupé…) ne l'empêche jamais d'appeler le garage.
class GarageTracker {
  GarageTracker(this._repository, this._deviceId);

  final GarageRepository _repository;
  final Future<String> Function() _deviceId;

  void track(String slug, GarageEvent event) {
    unawaited(() async {
      try {
        await _repository.recordEvent(slug, event, await _deviceId());
      } on Object {
        // Statistique perdue : sans conséquence pour le client.
      }
    }());
  }
}

final garageTrackerProvider = Provider<GarageTracker>(
  (ref) => GarageTracker(
    ref.watch(garageRepositoryProvider),
    () => ref.read(deviceIdProvider.future),
  ),
);
