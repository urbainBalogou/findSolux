import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/auth_session.dart';
import '../domain/otp_request_result.dart';

/// Connexion par numéro de téléphone et code reçu par SMS (ADR-007).
/// Toutes les méthodes lèvent [ApiException] en cas d'échec.
class AuthRepository {
  AuthRepository(this._dio);

  final Dio _dio;

  /// Demande l'envoi d'un code. Le code lui-même n'est présent que lorsque
  /// le serveur autorise explicitement son affichage en développement.
  Future<OtpRequestResult> requestCode(String phone) {
    return guardApi(() async {
      final response = await _dio.post<Map<String, dynamic>>(
        '/auth/otp',
        data: {'phone': phone},
      );
      final data = response.data!;
      return OtpRequestResult(
        resendIn: data['resend_in'] as int,
        otp: data['otp'] as String?,
      );
    });
  }

  Future<AuthSession> verifyCode({
    required String phone,
    required String code,
  }) {
    return guardApi(() async {
      final response = await _dio.post<Map<String, dynamic>>(
        '/auth/otp/verify',
        data: {
          'phone': phone,
          'code': code,
          'device_name': 'mobile-${defaultTargetPlatform.name}',
        },
      );
      final data = response.data!;
      return AuthSession(
        token: data['token'] as String,
        user: AppUser.fromJson(data['user'] as Map<String, dynamic>),
      );
    });
  }

  Future<void> logout() {
    return guardApi(() => _dio.post<void>('/auth/logout'));
  }
}

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(ref.watch(dioProvider)),
);
