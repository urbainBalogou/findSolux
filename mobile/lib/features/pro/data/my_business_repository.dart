import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/my_business.dart';

/// Fiche du garage de l'utilisateur connecté. Chaque modification renvoie la
/// fiche à jour. Toutes les méthodes lèvent [ApiException] en cas d'échec.
class MyBusinessRepository {
  MyBusinessRepository(this._dio);

  final Dio _dio;

  /// Null tant que l'utilisateur n'a pas inscrit de garage.
  Future<MyBusiness?> fetch() {
    return guardApi(() async {
      final response = await _dio.get<Map<String, dynamic>>('/my-business');
      final data = response.data!['data'];
      return data == null
          ? null
          : MyBusiness.fromJson(data as Map<String, dynamic>);
    });
  }

  Future<MyBusiness> create(NewBusiness business) =>
      _send(() => _dio.post('/my-business', data: business.toJson()));

  /// Modification partielle : seuls les champs de [changes] sont modifiés.
  Future<MyBusiness> update(Map<String, Object?> changes) =>
      _send(() => _dio.patch('/my-business', data: changes));

  Future<MyBusiness> replaceServices(List<BusinessServiceItem> services) =>
      _send(
        () => _dio.put(
          '/my-business/services',
          data: {
            'services': [for (final service in services) service.toJson()],
          },
        ),
      );

  Future<MyBusiness> replaceOpeningHours(List<OpeningRange> ranges) => _send(
    () => _dio.put(
      '/my-business/opening-hours',
      data: {
        'opening_hours': [for (final range in ranges) range.toJson()],
      },
    ),
  );

  Future<MyBusiness> uploadPhoto(String filePath) => _send(
    () async => _dio.post(
      '/my-business/photos',
      data: FormData.fromMap({'photo': await MultipartFile.fromFile(filePath)}),
    ),
  );

  Future<MyBusiness> deletePhoto(int photoId) =>
      _send(() => _dio.delete('/my-business/photos/$photoId'));

  Future<MyBusiness> reorderPhotos(List<int> photoIds) => _send(
    () => _dio.put('/my-business/photos/order', data: {'photo_ids': photoIds}),
  );

  Future<MyBusiness> _send(Future<Response<dynamic>> Function() request) {
    return guardApi(() async {
      final response = await request();
      final body = response.data as Map<String, dynamic>;
      return MyBusiness.fromJson(body['data'] as Map<String, dynamic>);
    });
  }
}

final myBusinessRepositoryProvider = Provider<MyBusinessRepository>(
  (ref) => MyBusinessRepository(ref.watch(dioProvider)),
);
