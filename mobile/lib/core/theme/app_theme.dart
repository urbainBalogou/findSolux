import 'package:flutter/material.dart';

/// Thème provisoire, remplacé par la charte graphique issue de la phase de design.
abstract final class AppTheme {
  static const _seed = Color(0xFF1565C0);

  static ThemeData light() {
    return ThemeData(
      colorScheme: ColorScheme.fromSeed(seedColor: _seed),
      useMaterial3: true,
    );
  }
}
