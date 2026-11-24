import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/push/push_service.dart';
import '../data/auth_repository.dart';
import '../data/google_auth_client.dart';
import '../data/session_storage.dart';
import '../domain/auth_session.dart';

final authControllerProvider =
    AsyncNotifierProvider<AuthController, AuthSession?>(AuthController.new);

/// Session courante : `null` tant que l'utilisateur n'est pas connecté.
class AuthController extends AsyncNotifier<AuthSession?> {
  @override
  Future<AuthSession?> build() => ref.read(sessionStorageProvider).read();

  /// Ouvre la session avec le compte Google choisi sur le téléphone.
  /// Renvoie `false` si l'utilisateur a fermé la fenêtre Google.
  /// Lève [GoogleAuthException] ou [ApiException] en cas d'échec.
  Future<bool> signInWithGoogle() async {
    final google = ref.read(googleAuthClientProvider);
    final idToken = await google.signIn();
    if (idToken == null) return false;

    final AuthSession session;
    try {
      session = await ref
          .read(authRepositoryProvider)
          .signInWithGoogle(idToken);
    } on ApiException {
      // Le prochain essai doit pouvoir proposer un autre compte.
      await google.signOut();
      rethrow;
    }
    await _save(session);
    return true;
  }

  /// Ouvre la session d'un membre de l'équipe d'un garage.
  /// Lève [ApiException] si l'identifiant ou le mot de passe est refusé.
  Future<void> signInAsTeamMember({
    required String username,
    required String password,
  }) async {
    final session = await ref
        .read(authRepositoryProvider)
        .signInAsTeamMember(username: username, password: password);
    await _save(session);
  }

  /// Enregistre le numéro où les garages joindront l'utilisateur.
  /// Lève [ApiException] si le numéro est refusé.
  Future<void> updatePhone(String phone) async {
    final session = state.value;
    if (session == null) return;

    final user = await ref.read(authRepositoryProvider).updatePhone(phone);
    await _save(AuthSession(token: session.token, user: user));
  }

  Future<void> logout() async {
    // L'appareil ne doit plus recevoir les notifications de ce compte.
    await ref.read(pushServiceProvider).unregister();
    try {
      await ref.read(authRepositoryProvider).logout();
    } on ApiException {
      // Hors connexion ou jeton déjà révoqué : on oublie la session quand même.
    }
    await ref.read(googleAuthClientProvider).signOut();
    await _clear();
  }

  /// Jeton refusé par l'API (révoqué ou expiré).
  Future<void> sessionExpired() => _clear();

  Future<void> _save(AuthSession session) async {
    await ref.read(sessionStorageProvider).write(session);
    state = AsyncData(session);
  }

  Future<void> _clear() async {
    await ref.read(sessionStorageProvider).clear();
    state = const AsyncData(null);
  }
}
