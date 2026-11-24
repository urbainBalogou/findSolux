import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/subscriptions.dart';

/// Offres du garage, achetées depuis le portefeuille (P4). Lève
/// [ApiException] ; `isInsufficientBalance` indique un solde trop faible.
class SubscriptionRepository {
  SubscriptionRepository(this._dio);

  final Dio _dio;

  Future<SubscriptionsOverview> fetch() => guardApi(() async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/my-business/subscriptions',
    );
    return SubscriptionsOverview.fromJson(response.data!);
  });

  /// Renvoie l'offre achetée et les droits mis à jour.
  Future<SubscriptionsOverview> purchase(
    int offerId, {
    required bool autoRenew,
  }) => guardApi(() async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/my-business/subscriptions',
      data: {'offer_id': offerId, 'auto_renew': autoRenew},
    );
    return SubscriptionsOverview.fromJson(response.data!);
  });

  Future<BusinessSubscription> setAutoRenew(
    int subscriptionId, {
    required bool autoRenew,
  }) => guardApi(() async {
    final response = await _dio.patch<Map<String, dynamic>>(
      '/my-business/subscriptions/$subscriptionId',
      data: {'auto_renew': autoRenew},
    );
    return BusinessSubscription.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  });
}

final subscriptionRepositoryProvider = Provider<SubscriptionRepository>(
  (ref) => SubscriptionRepository(ref.watch(dioProvider)),
);

final subscriptionsProvider = FutureProvider.autoDispose<SubscriptionsOverview>(
  (ref) => ref.watch(subscriptionRepositoryProvider).fetch(),
);
