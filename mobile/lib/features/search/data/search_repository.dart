import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/search_models.dart';

/// Recherche publique (sans compte). Lève [ApiException] en cas d'échec.
class SearchRepository {
  SearchRepository(this._dio);

  final Dio _dio;

  /// Garages autour du point, du plus proche au plus éloigné.
  Future<List<GarageResult>> searchGarages(SearchQuery query) {
    return guardApi(() async {
      final response = await _dio.get<Map<String, dynamic>>(
        '/businesses/search',
        queryParameters: query.toParameters(),
      );
      return [
        for (final item in response.data!['data'] as List)
          GarageResult.fromJson(item as Map<String, dynamic>),
      ];
    });
  }

  /// Quartiers et lieux correspondant à la saisie.
  Future<List<PlaceResult>> searchPlaces(String text) {
    return guardApi(() async {
      final response = await _dio.get<Map<String, dynamic>>(
        '/places/search',
        queryParameters: {'q': text},
      );
      return [
        for (final item in response.data!['data'] as List)
          PlaceResult.fromJson(item as Map<String, dynamic>),
      ];
    });
  }
}

final searchRepositoryProvider = Provider<SearchRepository>(
  (ref) => SearchRepository(ref.watch(dioProvider)),
);
