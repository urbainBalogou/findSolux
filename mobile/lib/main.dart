import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'features/auth/application/auth_controller.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Session lue avant le premier écran : le routeur sait d'emblée si
  // l'utilisateur est connecté.
  final container = ProviderContainer();
  await container.read(authControllerProvider.future);

  runApp(
    UncontrolledProviderScope(
      container: container,
      child: const FindSoluxApp(),
    ),
  );
}
