import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../data/auth_repository.dart';
import '../data/session_storage.dart';
import '../domain/auth_session.dart';

final authControllerProvider =
    AsyncNotifierProvider<AuthController, AuthSession?>(AuthController.new);

/// Session courante : `null` tant que l'utilisateur n'est pas connecté.
class AuthController extends AsyncNotifier<AuthSession?> {
  @override
  Future<AuthSession?> build() => ref.read(sessionStorageProvider).read();

  /// Valide le code reçu par SMS et ouvre la session.
  /// Lève [ApiException] si le code est refusé.
  Future<void> verifyCode({required String phone, required String code}) async {
    final session = await ref
        .read(authRepositoryProvider)
        .verifyCode(phone: phone, code: code);
    await ref.read(sessionStorageProvider).write(session);
    state = AsyncData(session);
  }

  Future<void> logout() async {
    try {
      await ref.read(authRepositoryProvider).logout();
    } on ApiException {
      // Hors connexion ou jeton déjà révoqué : on oublie la session quand même.
    }
    await _clear();
  }

  /// Jeton refusé par l'API (révoqué ou expiré).
  Future<void> sessionExpired() => _clear();

  Future<void> _clear() async {
    await ref.read(sessionStorageProvider).clear();
    state = const AsyncData(null);
  }
}
