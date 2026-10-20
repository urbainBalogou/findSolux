import 'dart:convert';
import 'dart:io';

import 'package:findsolux/features/garage/domain/public_business.dart';
import 'package:findsolux/features/pro/domain/my_business.dart';
import 'package:findsolux/features/reference/domain/reference_data.dart';
import 'package:findsolux/features/search/domain/search_models.dart';
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

  test('résultats de recherche de garages', () {
    final garages = [
      for (final item in fixture('search_results')['data'] as List)
        GarageResult.fromJson(item as Map<String, dynamic>),
    ];

    expect(garages.single.name, 'Garage Essai');
    expect(garages.single.distanceMeters, inInclusiveRange(300, 350));
    expect(garages.single.hasOpeningHours, isTrue);
    expect(garages.single.landmark, 'Près du marché');
    expect(garages.single.serviceIds, hasLength(1));
  });

  test('résultats de recherche de lieux', () {
    final places = [
      for (final item in fixture('places')['data'] as List)
        PlaceResult.fromJson(item as Map<String, dynamic>),
    ];

    expect(places.single.isDistrict, isTrue);
    expect(places.single.name, 'Adidogomé');
    expect(places.single.address, 'Lomé');
  });

  test('fiche publique d’un garage', () {
    final garage = PublicBusiness.fromJson(
      fixture('public_business')['data'] as Map<String, dynamic>,
    );

    expect(garage.name, 'Garage Essai');
    expect(garage.description, 'Spécialiste moto');
    expect(garage.isMobile, isTrue);
    expect(garage.serviceRadiusKm, 5);
    expect(garage.hasOpeningHours, isTrue);
    expect(garage.services.single.priceMax, 15000);
    expect(garage.openingHours.single.opensAt, '08:00');
    expect(garage.shareUrl, endsWith('/garages/${garage.slug}'));
  });
}
