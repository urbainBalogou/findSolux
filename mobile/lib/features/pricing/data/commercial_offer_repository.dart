import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/commercial_offer.dart';

/// Conserve le dernier catalogue valide sur le téléphone et demande au
/// serveur de le renvoyer uniquement lorsque son ETag a changé.
class CommercialOfferRepository {
  CommercialOfferRepository(this._dio, this._preferences);

  static const _bodyKey = 'commercial_offers.body';
  static const _etagKey = 'commercial_offers.etag';

  final Dio _dio;
  final SharedPreferencesAsync _preferences;

  Future<CommercialOfferCatalog> load() async {
    final cachedBody = await _preferences.getString(_bodyKey);
    final etag = await _preferences.getString(_etagKey);

    try {
      final response = await _dio.get<String>(
        '/commercial-offers',
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
      final catalog = _parse(body);
      await _preferences.setString(_bodyKey, body);

      final newEtag = response.headers.value('etag');
      if (newEtag != null) {
        await _preferences.setString(_etagKey, newEtag);
      }

      return catalog;
    } on DioException catch (error) {
      if (cachedBody != null) return _parse(cachedBody);
      throw ApiException.fromDio(error);
    }
  }

  CommercialOfferCatalog _parse(String body) =>
      CommercialOfferCatalog.fromJson(jsonDecode(body) as Map<String, dynamic>);
}

final commercialOfferRepositoryProvider = Provider<CommercialOfferRepository>(
  (ref) => CommercialOfferRepository(
    ref.watch(dioProvider),
    SharedPreferencesAsync(),
  ),
);

/// Le provider non auto-disposé garde aussi le catalogue en mémoire pendant
/// toute la session de l'application.
final commercialOffersProvider = FutureProvider<CommercialOfferCatalog>(
  (ref) => ref.watch(commercialOfferRepositoryProvider).load(),
);
