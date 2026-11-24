import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../domain/auth_session.dart';

/// Conserve la session entre deux lancements : l'utilisateur reste connecté
/// sans réseau, sans redemander de code.
abstract interface class SessionStorage {
  Future<AuthSession?> read();

  Future<void> write(AuthSession session);

  Future<void> clear();
}

class SecureSessionStorage implements SessionStorage {
  SecureSessionStorage([this._storage = const FlutterSecureStorage()]);

  static const _tokenKey = 'auth_token';
  static const _userKey = 'auth_user';

  final FlutterSecureStorage _storage;

  @override
  Future<AuthSession?> read() async {
    final token = await _storage.read(key: _tokenKey);
    final user = await _storage.read(key: _userKey);
    if (token == null || user == null) {
      return null;
    }

    try {
      final json = jsonDecode(user) as Map<String, dynamic>;
      return AuthSession(token: token, user: AppUser.fromJson(json));
    } on Object {
      // Données illisibles (ancienne version de l'application) : reconnexion.
      await clear();
      return null;
    }
  }

  @override
  Future<void> write(AuthSession session) async {
    await _storage.write(key: _tokenKey, value: session.token);
    await _storage.write(
      key: _userKey,
      value: jsonEncode(session.user.toJson()),
    );
  }

  @override
  Future<void> clear() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _userKey);
  }
}

final sessionStorageProvider = Provider<SessionStorage>(
  (ref) => SecureSessionStorage(),
);
