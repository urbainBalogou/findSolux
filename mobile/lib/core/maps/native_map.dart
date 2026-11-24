import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Carte Google native ; remplacée par un fond neutre dans les tests, où la
/// vue native n'existe pas.
final nativeMapEnabledProvider = Provider<bool>((ref) => true);
