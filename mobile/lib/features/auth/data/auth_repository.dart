import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/auth_session.dart';

/// Connexion avec un compte Google (ADR-017) et profil de l'utilisateur.
/// Toutes les méthodes lèvent [ApiException] en cas d'échec.
class AuthRepository {
  AuthRepository(this._dio);

  final Dio _dio;

  /// Échange le jeton d'identité Google contre une session de l'API. Le compte
  /// est créé à la première connexion.
  Future<AuthSession> signInWithGoogle(String idToken) {
    return guardApi(() async {
      final response = await _dio.post<Map<String, dynamic>>(
        '/auth/google',
        data: {
          'id_token': idToken,
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

  /// Membre de l'équipe d'un garage : identifiant et mot de passe créés par
  /// le garagiste.
  Future<AuthSession> signInAsTeamMember({
    required String username,
    required String password,
  }) {
    return guardApi(() async {
      final response = await _dio.post<Map<String, dynamic>>(
        '/auth/team-member',
        data: {
          'username': username,
          'password': password,
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

  /// Numéro où les garages joindront l'utilisateur (format E.164).
  Future<AppUser> updatePhone(String phone) {
    return guardApi(() async {
      final response = await _dio.patch<Map<String, dynamic>>(
        '/me',
        data: {'phone': phone},
      );
      return AppUser.fromJson(response.data!['data'] as Map<String, dynamic>);
    });
  }

  Future<void> logout() {
    return guardApi(() => _dio.post<void>('/auth/logout'));
  }
}

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(ref.watch(dioProvider)),
);
