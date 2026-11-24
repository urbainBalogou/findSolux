/// Configuration injectée à la compilation :
/// `flutter run --dart-define=API_BASE_URL=https://api.exemple.tg/api/v1`
abstract final class AppConfig {
  /// Par défaut : API Laravel locale vue depuis l'émulateur Android.
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    // defaultValue: 'http://10.0.2.2:8000/api/v1',
    defaultValue: 'http://127.0.0.1:8000/api/v1',
  );

  /// Identifiant client OAuth de type « Application Web » (ADR-017) : le jeton
  /// Google est émis pour lui, et le serveur le vérifie
  /// (`GOOGLE_SIGN_IN_CLIENT_IDS`). Obligatoire sur Android.
  static const googleServerClientId = String.fromEnvironment(
    'GOOGLE_SERVER_CLIENT_ID',
  );

  /// iOS uniquement : identifiant client OAuth de type « iOS ».
  static const googleIosClientId = String.fromEnvironment(
    'GOOGLE_IOS_CLIENT_ID',
  );
}
