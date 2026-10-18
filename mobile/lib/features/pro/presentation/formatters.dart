import 'package:intl/intl.dart';

import '../domain/my_business.dart';

const dayNames = [
  'Lundi',
  'Mardi',
  'Mercredi',
  'Jeudi',
  'Vendredi',
  'Samedi',
  'Dimanche',
];

const _shortDayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

/// « Lun–Sam 07:30–18:30 · Dim 09:00–13:00 », ou « Non renseignés ».
String formatOpeningHours(List<OpeningRange> ranges) {
  if (ranges.isEmpty) return 'Non renseignés';

  final byDay = <int, String>{};
  for (final range in ranges) {
    final slot = '${range.opensAt}–${range.closesAt}';
    byDay[range.day] = byDay.containsKey(range.day)
        ? '${byDay[range.day]}, $slot'
        : slot;
  }

  final groups = <String>[];
  int? start;
  for (var day = 1; day <= 8; day++) {
    final continues =
        start != null &&
        day <= 7 &&
        byDay[day] == byDay[start] &&
        byDay[day] != null;
    if (continues) continue;
    if (start != null && byDay[start] != null) {
      final end = day - 1;
      final days = start == end
          ? _shortDayNames[start - 1]
          : '${_shortDayNames[start - 1]}–${_shortDayNames[end - 1]}';
      groups.add('$days ${byDay[start]}');
    }
    start = day <= 7 ? day : null;
  }
  return groups.join(' · ');
}

final _amount = NumberFormat.decimalPattern('fr');

/// « 5 000 – 15 000 FCFA », « À partir de 5 000 FCFA »… ou chaîne vide.
String formatPrice(int? min, int? max) {
  if (min != null && max != null) {
    return min == max
        ? '${_amount.format(min)} FCFA'
        : '${_amount.format(min)} – ${_amount.format(max)} FCFA';
  }
  if (min != null) return 'À partir de ${_amount.format(min)} FCFA';
  if (max != null) return 'Jusqu’à ${_amount.format(max)} FCFA';
  return '';
}

/// Les 8 chiffres d'un numéro togolais (+228XXXXXXXX), pour un champ de saisie.
String localDigits(String? e164) {
  if (e164 == null) return '';
  return e164.startsWith('+228') ? e164.substring(4) : e164;
}
