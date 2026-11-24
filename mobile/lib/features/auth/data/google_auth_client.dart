import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../../../core/config/app_config.dart';

/// Échec de la connexion Google, avec un message à afficher.
class GoogleAuthException implements Exception {
  const GoogleAuthException(this.message);

  final String message;

  @override
  String toString() => message;
}

/// Compte Google du téléphone (ADR-017) : fournit le jeton d'identité que
/// l'API vérifie avant d'ouvrir la session.
abstract interface class GoogleAuthClient {
  /// Jeton d'identité du compte choisi, ou `null` si l'utilisateur a fermé la
  /// fenêtre Google. Lève [GoogleAuthException] en cas d'échec.
  Future<String?> signIn();

  /// Oublie le compte choisi : la prochaine connexion le redemande.
  Future<void> signOut();
}

class PluginGoogleAuthClient implements GoogleAuthClient {
  PluginGoogleAuthClient([GoogleSignIn? googleSignIn])
    : _googleSignIn = googleSignIn ?? GoogleSignIn.instance;

  static const _failure = 'La connexion Google a échoué. Réessayez.';

  final GoogleSignIn _googleSignIn;
  Future<void>? _initialization;

  @override
  Future<String?> signIn() async {
    try {
      await _ensureInitialized();
      final account = await _googleSignIn.authenticate();
      final idToken = account.authentication.idToken;
      if (idToken == null) throw const GoogleAuthException(_failure);
      return idToken;
    } on GoogleAuthException {
      rethrow;
    } on GoogleSignInException catch (error) {
      return switch (error.code) {
        GoogleSignInExceptionCode.canceled => null,
        GoogleSignInExceptionCode.clientConfigurationError ||
        GoogleSignInExceptionCode.providerConfigurationError =>
          throw const GoogleAuthException(
            'La connexion Google n’est pas disponible sur cette version de '
            'l’application.',
          ),
        _ => throw const GoogleAuthException(_failure),
      };
    } on Exception {
      // Services Google absents ou en erreur sur le téléphone.
      throw const GoogleAuthException(_failure);
    }
  }

  @override
  Future<void> signOut() async {
    try {
      await _ensureInitialized();
      await _googleSignIn.signOut();
    } on Object {
      // Rien à oublier si Google n'a jamais été initialisé.
    }
  }

  /// `initialize` ne doit être appelé qu'une fois ; un échec permet de réessayer.
  Future<void> _ensureInitialized() async {
    final initialization = _initialization ??= _googleSignIn.initialize(
      clientId: _orNull(AppConfig.googleIosClientId),
      serverClientId: _orNull(AppConfig.googleServerClientId),
    );
    try {
      await initialization;
    } on Object {
      _initialization = null;
      rethrow;
    }
  }

  static String? _orNull(String value) => value.isEmpty ? null : value;
}

final googleAuthClientProvider = Provider<GoogleAuthClient>(
  (ref) => PluginGoogleAuthClient(),
);
