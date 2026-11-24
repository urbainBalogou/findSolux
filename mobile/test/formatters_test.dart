import 'package:findsolux/features/pro/domain/my_business.dart';
import 'package:findsolux/core/formatting/formatters.dart';
import 'package:flutter_test/flutter_test.dart';

import 'helpers/test_app.dart';

void main() {
  OpeningRange range(int day, String opens, String closes) =>
      OpeningRange(day: day, opensAt: opens, closesAt: closes);

  group('formatOpeningHours', () {
    test('regroupe les jours consécutifs identiques', () {
      expect(
        formatOpeningHours([
          for (var day = 1; day <= 6; day++) range(day, '07:30', '18:30'),
          range(7, '09:00', '13:00'),
        ]),
        'Lun–Sam 07:30–18:30 · Dim 09:00–13:00',
      );
    });

    test('sépare les jours non consécutifs et les pauses', () {
      expect(
        formatOpeningHours([
          range(1, '08:00', '12:00'),
          range(1, '14:00', '18:00'),
          range(3, '08:00', '18:00'),
        ]),
        'Lun 08:00–12:00, 14:00–18:00 · Mer 08:00–18:00',
      );
    });

    test('sans horaires', () {
      expect(formatOpeningHours([]), 'Non renseignés');
    });
  });

  test('formatPrice', () {
    expect(formatPrice(5000, 15000), '5 000 – 15 000 FCFA');
    expect(formatPrice(5000, null), 'À partir de 5 000 FCFA');
    expect(formatPrice(null, 2000), 'Jusqu’à 2 000 FCFA');
    expect(formatPrice(null, null), '');
  });

  test('localDigits', () {
    expect(localDigits('+22890123456'), '90123456');
    expect(localDigits(null), '');
  });

  test('quartier le plus proche d’une position', () {
    expect(testReference.nearestDistrict(6.209, 1.156), adidogome);
    expect(testReference.nearestDistrict(6.140, 1.250), be);
  });
}
