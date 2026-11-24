import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/requests.dart';

/// Signaux de détresse, demandes de contact et contacts échangés, côté client
/// et côté garage. Lève [ApiException] en cas d'échec ; son `code` indique
/// un solde insuffisant ou une adhésion inactive.
class RequestsRepository {
  RequestsRepository(this._dio);

  final Dio _dio;

  // Espace client.

  Future<DistressSignal> sendDistressSignal(NewDistressSignal signal) =>
      guardApi(() async {
        final response = await _dio.post<Map<String, dynamic>>(
          '/distress-signals',
          data: signal.toJson(),
        );
        return DistressSignal.fromJson(_data(response));
      });

  /// Le plus récent d'abord.
  Future<List<DistressSignal>> fetchDistressSignals() => guardApi(() async {
    final response = await _dio.get<Map<String, dynamic>>('/distress-signals');
    return [
      for (final item in response.data!['data'] as List)
        DistressSignal.fromJson(item as Map<String, dynamic>),
    ];
  });

  Future<DistressSignal> fetchDistressSignal(String id) => guardApi(() async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/distress-signals/$id',
    );
    return DistressSignal.fromJson(_data(response));
  });

  Future<DistressSignal> cancelDistressSignal(String id) => guardApi(() async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/distress-signals/$id/cancel',
    );
    return DistressSignal.fromJson(_data(response));
  });

  Future<ContactRequest> requestContact(String slug, {String? message}) =>
      guardApi(() async {
        final response = await _dio.post<Map<String, dynamic>>(
          '/businesses/$slug/contact-requests',
          data: {'message': ?message},
        );
        return ContactRequest.fromJson(_data(response));
      });

  Future<List<ContactRequest>> fetchContactRequests() => guardApi(() async {
    final response = await _dio.get<Map<String, dynamic>>('/contact-requests');
    return [
      for (final item in response.data!['data'] as List)
        ContactRequest.fromJson(item as Map<String, dynamic>),
    ];
  });

  Future<List<SavedGarage>> fetchSavedGarages() => guardApi(() async {
    final response = await _dio.get<Map<String, dynamic>>('/contacts');
    return [
      for (final item in response.data!['data'] as List)
        SavedGarage.fromJson(item as Map<String, dynamic>),
    ];
  });

  // Espace garagiste.

  Future<RequestInbox> fetchInbox() => guardApi(() async {
    final responses = await Future.wait([
      _dio.get<Map<String, dynamic>>('/my-business/distress-signals'),
      _dio.get<Map<String, dynamic>>('/my-business/contact-requests'),
    ]);
    final meta = responses.first.data!['meta'] as Map<String, dynamic>;
    return RequestInbox(
      distressSignals: [
        for (final item in responses.first.data!['data'] as List)
          ReceivedDistressSignal.fromJson(item as Map<String, dynamic>),
      ],
      contactRequests: [
        for (final item in responses.last.data!['data'] as List)
          ReceivedContactRequest.fromJson(item as Map<String, dynamic>),
      ],
      acceptancePrice:
          (meta['acceptance_price'] as Map<String, dynamic>)['amount'] as int,
      balance: (meta['balance'] as Map<String, dynamic>)['amount'] as int,
    );
  });

  Future<ReceivedDistressSignal> answerDistressSignal(
    String id, {
    required bool accept,
  }) => guardApi(() async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/my-business/distress-signals/$id/${accept ? 'accept' : 'decline'}',
    );
    return ReceivedDistressSignal.fromJson(_data(response));
  });

  Future<ReceivedContactRequest> answerContactRequest(
    String id, {
    required bool accept,
  }) => guardApi(() async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/my-business/contact-requests/$id/${accept ? 'accept' : 'decline'}',
    );
    return ReceivedContactRequest.fromJson(_data(response));
  });

  Future<List<BusinessClient>> fetchClients() => guardApi(() async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/my-business/clients',
    );
    return [
      for (final item in response.data!['data'] as List)
        BusinessClient.fromJson(item as Map<String, dynamic>),
    ];
  });

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data!['data'] as Map<String, dynamic>;
}

final requestsRepositoryProvider = Provider<RequestsRepository>(
  (ref) => RequestsRepository(ref.watch(dioProvider)),
);

/// Demandes reçues par le garage (écran « Demandes »).
final requestInboxProvider = FutureProvider.autoDispose<RequestInbox>(
  (ref) => ref.watch(requestsRepositoryProvider).fetchInbox(),
);

/// « Mes clients » du garage.
final businessClientsProvider =
    FutureProvider.autoDispose<List<BusinessClient>>(
      (ref) => ref.watch(requestsRepositoryProvider).fetchClients(),
    );

/// « Mes garages » du client, et ses demandes de contact.
final savedGaragesProvider = FutureProvider.autoDispose<List<SavedGarage>>(
  (ref) => ref.watch(requestsRepositoryProvider).fetchSavedGarages(),
);

final myContactRequestsProvider =
    FutureProvider.autoDispose<List<ContactRequest>>(
      (ref) => ref.watch(requestsRepositoryProvider).fetchContactRequests(),
    );
