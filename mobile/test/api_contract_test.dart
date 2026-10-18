import 'dart:convert';
import 'dart:io';

import 'package:findsolux/features/pro/domain/my_business.dart';
import 'package:findsolux/features/reference/domain/reference_data.dart';
import 'package:flutter_test/flutter_test.dart';

/// Réponses réelles de l'API (test/fixtures), enregistrées depuis le serveur
/// Laravel : vérifie que l'application sait les lire.
void main() {
  Map<String, dynamic> fixture(String name) =>
      jsonDecode(File('test/fixtures/$name.json').readAsStringSync())
          as Map<String, dynamic>;

  test('référentiels', () {
    final reference = ReferenceData.fromJson(fixture('reference_data'));

    expect(reference.cities.single.name, 'Lomé');
    expect(reference.districts, hasLength(94));
    expect(reference.trades.single.services, hasLength(13));
    expect(reference.vehicleTypes.map((t) => t.name), [
      'Voiture',
      'Moto',
      'Tricycle',
      'Poids lourd',
    ]);
    expect(reference.nearestDistrict(6.2101, 1.1547)?.name, 'Adidogomé');
  });

  test('fiche du garage', () {
    final business = MyBusiness.fromJson(
      fixture('my_business')['data'] as Map<String, dynamic>,
    );

    expect(business.name, 'Garage Essai');
    expect(business.isPublished, isTrue);
    expect(business.phone, '+22891223344');
    expect(business.description, 'Spécialiste moto');
    expect(business.isMobile, isTrue);
    expect(business.serviceRadiusKm, 5);
    expect(business.services.first.priceMax, 15000);
    expect(business.services.last.vehicleTypeId, isNotNull);
    expect(business.openingHours.first.opensAt, '07:30');
    expect(business.photos.single.thumbnailUrl, endsWith('-thumb.webp'));
    expect(business.missingItems, isEmpty);
  });
}
