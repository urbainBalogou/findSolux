import 'dart:async';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/application/auth_controller.dart';
import '../../features/pro/application/my_business_controller.dart';
import '../../features/pro/data/subscription_repository.dart';
import '../../features/requests/data/requests_repository.dart';
import '../network/api_client.dart';
import '../router/app_router.dart';

/// Affiche les notifications reçues pendant que l'application est ouverte
/// (Android ne les affiche alors pas lui-même).
final scaffoldMessengerKeyProvider =
    Provider<GlobalKey<ScaffoldMessengerState>>(
      (ref) => GlobalKey<ScaffoldMessengerState>(),
    );

/// Notifications push Firebase (ADR-016) : enregistre le jeton de l'appareil
/// auprès de l'API pour l'utilisateur connecté et ouvre le bon écran quand
/// une notification est touchée.
///
/// Sans configuration Firebase (google-services.json absent), [start] ne fait
/// rien : l'application fonctionne sans notifications push.
class PushService {
  PushService(this._ref);

  final Ref _ref;
  String? _token;
  var _started = false;

  Future<void> start() async {
    if (_started) return;
    try {
      await Firebase.initializeApp();
    } on Object {
      return;
    }
    _started = true;

    final messaging = FirebaseMessaging.instance;
    await messaging.requestPermission();

    _ref.listen(authControllerProvider.select((auth) => auth.value?.user.id), (
      _,
      userId,
    ) {
      if (userId != null) unawaited(_register(_token));
    });
    // Jeton d'enregistrement FCM classique, cible du champ `token` de l'API
    // HTTP v1 utilisée par le serveur (FcmPushSender). Les nouvelles API
    // register()/onRegistered renvoient un identifiant d'installation (FID) :
    // à adopter avec l'envoi serveur correspondant.
    // ignore: deprecated_member_use
    messaging.onTokenRefresh.listen(_register);
    // ignore: deprecated_member_use
    unawaited(messaging.getToken().then(_register, onError: (Object _) {}));

    FirebaseMessaging.onMessage.listen(_showInApp);
    FirebaseMessaging.onMessageOpenedApp.listen(
      (message) => _open(message.data),
    );
    final initial = await messaging.getInitialMessage();
    if (initial != null) _open(initial.data);
  }

  /// À la déconnexion, tant que le jeton de session est valable.
  Future<void> unregister() async {
    final token = _token;
    if (!_started || token == null) return;
    try {
      await _ref
          .read(dioProvider)
          .delete<void>('/me/device-tokens', data: {'token': token});
    } on DioException {
      // Hors connexion : l'API oubliera le jeton quand Firebase le refusera.
    }
  }

  Future<void> _register(String? token) async {
    if (token == null) return;
    _token = token;
    if (_ref.read(authControllerProvider).value == null) return;
    try {
      await _ref
          .read(dioProvider)
          .post<void>(
            '/me/device-tokens',
            data: {
              'token': token,
              'platform': Platform.isIOS ? 'ios' : 'android',
            },
          );
    } on DioException {
      // Nouvel essai au prochain démarrage ou changement de jeton.
    }
  }

  void _showInApp(RemoteMessage message) {
    _refreshFor(message.data['type']);
    final notification = message.notification;
    if (notification == null) return;
    _ref
        .read(scaffoldMessengerKeyProvider)
        .currentState
        ?.showSnackBar(
          SnackBar(
            content: Text(
              [
                notification.title,
                notification.body,
              ].whereType<String>().join('\n'),
            ),
            action: SnackBarAction(
              label: 'Voir',
              onPressed: () => _open(message.data),
            ),
          ),
        );
  }

  void _refreshFor(Object? type) {
    switch (type) {
      case 'distress_signal' || 'contact_request':
        _ref.invalidate(requestInboxProvider);
      case 'contact_request_answered':
        _ref
          ..invalidate(savedGaragesProvider)
          ..invalidate(myContactRequestsProvider);
      case 'subscription_renewed' || 'subscription_expired':
        _ref
          ..invalidate(subscriptionsProvider)
          ..invalidate(myBusinessProvider);
    }
  }

  void _open(Map<String, dynamic> data) {
    final router = _ref.read(routerProvider);
    final id = data['id'] as String?;

    switch (data['type']) {
      case 'distress_signal' || 'contact_request':
        router.go(AppRoutes.proRequests);
      case 'distress_signal_accepted' || 'distress_signal_unanswered':
        router.push(AppRoutes.distressSignalLocation(signalId: id));
      case 'contact_request_answered'
          when data['status'] == 'accepted' && data['business_slug'] != null:
        router.push(AppRoutes.garage(data['business_slug'] as String));
      case 'contact_request_answered':
        router.go(AppRoutes.myGarages);
      case 'subscription_ending' ||
          'subscription_renewed' ||
          'subscription_expired':
        router.go(AppRoutes.proSubscription);
    }
  }
}

final pushServiceProvider = Provider<PushService>(PushService.new);
