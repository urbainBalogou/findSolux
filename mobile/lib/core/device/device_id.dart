import 'dart:math';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Identifiant anonyme de l'appareil, tiré au hasard à la première
/// utilisation et gardé ensuite : les statistiques des garagistes comptent
/// un appareil une fois par jour et par action. Il ne contient aucune donnée
/// personnelle.
final deviceIdProvider = FutureProvider<String>((ref) async {
  const key = 'device_id';
  final preferences = SharedPreferencesAsync();

  final existing = await preferences.getString(key);
  if (existing != null) return existing;

  final random = Random.secure();
  final id = [
    for (var i = 0; i < 16; i++)
      random.nextInt(256).toRadixString(16).padLeft(2, '0'),
  ].join();
  await preferences.setString(key, id);
  return id;
});
