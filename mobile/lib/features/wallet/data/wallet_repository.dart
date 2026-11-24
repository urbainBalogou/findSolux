import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/wallet.dart';

class WalletRepository {
  WalletRepository(this._dio);

  final Dio _dio;

  Future<WalletSummary> fetch() => guardApi(() async {
    final response = await _dio.get<Map<String, dynamic>>('/wallet');
    return WalletSummary.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  });

  Future<WalletTopUp> createTopUp({
    required int amount,
    required String paymentMethod,
    required String phone,
  }) => guardApi(() async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/wallet/top-ups',
      data: {'amount': amount, 'payment_method': paymentMethod, 'phone': phone},
    );
    return WalletTopUp.fromJson(response.data!['data'] as Map<String, dynamic>);
  });

  Future<WalletTopUp> fetchTopUp(String id) => guardApi(() async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/wallet/top-ups/$id',
    );
    return WalletTopUp.fromJson(response.data!['data'] as Map<String, dynamic>);
  });
}

final walletRepositoryProvider = Provider<WalletRepository>(
  (ref) => WalletRepository(ref.watch(dioProvider)),
);
