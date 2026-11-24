import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'core/push/push_service.dart';
import 'features/auth/application/auth_controller.dart';
import 'features/pricing/data/commercial_offer_repository.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Session lue avant le premier écran : le routeur sait d'emblée si
  // l'utilisateur est connecté.
  final container = ProviderContainer();
  await container.read(authControllerProvider.future);

  // Lance le chargement dès le démarrage sans bloquer l'affichage. Les écrans
  // peuvent observer commercialOffersProvider; le disque prend le relais si
  // le réseau est indisponible.
  unawaited(
    container
        .read(commercialOffersProvider.future)
        .then<void>((_) {}, onError: (Object _, StackTrace _) {}),
  );

  runApp(
    UncontrolledProviderScope(
      container: container,
      child: const FindSoluxApp(),
    ),
  );

  // Après le premier écran : sans configuration Firebase, rien ne se passe.
  unawaited(container.read(pushServiceProvider).start());
}
