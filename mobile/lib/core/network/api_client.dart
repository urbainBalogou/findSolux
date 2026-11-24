import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/application/auth_controller.dart';
import '../config/app_config.dart';

/// Client HTTP partagé. Délais adaptés aux réseaux 3G instables.
final dioProvider = Provider<Dio>((ref) {
  final dio = Dio(
    BaseOptions(
      baseUrl: AppConfig.apiBaseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 20),
      headers: {'Accept': 'application/json'},
    ),
  );

  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) {
        final token = ref.read(authControllerProvider).value?.token;
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (error, handler) {
        final hadToken = error.requestOptions.headers.containsKey(
          'Authorization',
        );
        if (hadToken && error.response?.statusCode == 401) {
          ref.read(authControllerProvider.notifier).sessionExpired();
        }
        handler.next(error);
      },
    ),
  );

  return dio;
});
