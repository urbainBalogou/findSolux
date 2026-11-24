import 'package:dio/dio.dart';

/// Erreur d'appel à l'API, avec un message prêt à afficher à l'utilisateur.
class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode, this.code});

  factory ApiException.fromDio(DioException error) {
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return const ApiException(
          'Le réseau est trop lent. Réessayez dans un instant.',
        );
      case DioExceptionType.connectionError:
        return const ApiException(
          'Pas de connexion internet. Vérifiez votre réseau et réessayez.',
        );
      default:
        break;
    }

    final statusCode = error.response?.statusCode;
    final data = error.response?.data;

    if (statusCode == 401) {
      return const ApiException(
        'Votre session a expiré. Reconnectez-vous.',
        statusCode: 401,
      );
    }

    // Les messages des erreurs 4xx sont rédigés par l'API pour l'utilisateur.
    if (statusCode != null && statusCode < 500 && data is Map) {
      final errors = data['errors'];
      if (errors is Map) {
        for (final messages in errors.values) {
          if (messages is List && messages.isNotEmpty) {
            return ApiException(
              messages.first.toString(),
              statusCode: statusCode,
            );
          }
        }
      }
      final message = data['message'];
      if (message is String && message.isNotEmpty) {
        final code = data['code'];
        return ApiException(
          message,
          statusCode: statusCode,
          code: code is String ? code : null,
        );
      }
    }

    return ApiException(
      'Une erreur est survenue. Réessayez plus tard.',
      statusCode: statusCode,
    );
  }

  final String message;
  final int? statusCode;

  /// Raison métier renvoyée par l'API (`insufficient_balance`,
  /// `membership_required`, `not_available`…), pour adapter l'écran.
  final String? code;

  /// Solde du portefeuille trop faible : proposer une recharge.
  bool get isInsufficientBalance => code == 'insufficient_balance';

  /// Adhésion inactive : proposer d'acheter une adhésion.
  bool get isMembershipRequired => code == 'membership_required';

  @override
  String toString() => message;
}

/// Exécute un appel à l'API en convertissant les erreurs en [ApiException].
Future<T> guardApi<T>(Future<T> Function() request) async {
  try {
    return await request();
  } on DioException catch (error) {
    throw ApiException.fromDio(error);
  }
}
