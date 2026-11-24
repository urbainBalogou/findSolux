import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/reference_data.dart';

/// Référentiels gardés sur le téléphone : l'API n'est interrogée qu'avec
/// l'ETag de la version connue (réponse 304 vide si rien n'a changé), et la
/// dernière version reste utilisable hors connexion.
class ReferenceRepository {
  ReferenceRepository(this._dio, this._preferences);

  static const _bodyKey = 'reference_data.body';
  static const _etagKey = 'reference_data.etag';

  final Dio _dio;
  final SharedPreferencesAsync _preferences;

  Future<ReferenceData> load() async {
    final cachedBody = await _preferences.getString(_bodyKey);
    final etag = await _preferences.getString(_etagKey);

    try {
      final response = await _dio.get<String>(
        '/reference-data',
        options: Options(
          responseType: ResponseType.plain,
          headers: {
            if (cachedBody != null && etag != null) 'If-None-Match': etag,
          },
          validateStatus: (status) => status == 200 || status == 304,
        ),
      );

      if (response.statusCode == 304 && cachedBody != null) {
        return _parse(cachedBody);
      }

      final body = response.data!;
      final data = _parse(body);
      await _preferences.setString(_bodyKey, body);
      final newEtag = response.headers.value('etag');
      if (newEtag != null) {
        await _preferences.setString(_etagKey, newEtag);
      }
      return data;
    } on DioException catch (error) {
      if (cachedBody != null) return _parse(cachedBody);
      throw ApiException.fromDio(error);
    }
  }

  ReferenceData _parse(String body) =>
      ReferenceData.fromJson(jsonDecode(body) as Map<String, dynamic>);
}

final referenceRepositoryProvider = Provider<ReferenceRepository>(
  (ref) =>
      ReferenceRepository(ref.watch(dioProvider), SharedPreferencesAsync()),
);

final referenceDataProvider = FutureProvider<ReferenceData>(
  (ref) => ref.watch(referenceRepositoryProvider).load(),
);
