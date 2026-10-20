/// « À 350 m », « À 2,4 km », « À 12 km ».
String formatDistance(int meters) {
  if (meters < 1000) {
    final rounded = meters < 50 ? meters : (meters / 10).round() * 10;
    return 'À $rounded m';
  }
  final km = meters / 1000;
  if (km >= 10) return 'À ${km.round()} km';
  return 'À ${km.toStringAsFixed(1).replaceAll('.', ',')} km';
}
