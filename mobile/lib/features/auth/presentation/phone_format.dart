/// Affiche un numéro togolais E.164 sous la forme « +228 90 12 34 56 ».
String formatPhone(String e164) {
  final match = RegExp(r'^\+228(\d{8})$').firstMatch(e164);
  if (match == null) {
    return e164;
  }
  final digits = match.group(1)!;
  final pairs = [
    for (var i = 0; i < digits.length; i += 2) digits.substring(i, i + 2),
  ];
  return '+228 ${pairs.join(' ')}';
}
