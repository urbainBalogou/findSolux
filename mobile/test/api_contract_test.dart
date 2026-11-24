import 'dart:convert';
import 'dart:io';

import 'package:findsolux/features/garage/domain/public_business.dart';
import 'package:findsolux/features/pricing/domain/commercial_offer.dart';
import 'package:findsolux/features/pro/domain/my_business.dart';
import 'package:findsolux/features/pro/domain/subscriptions.dart';
import 'package:findsolux/features/reference/domain/reference_data.dart';
import 'package:findsolux/features/requests/domain/requests.dart';
import 'package:findsolux/features/search/domain/search_models.dart';
import 'package:flutter_test/flutter_test.dart';

/// Réponses réelles de l'API (test/fixtures), enregistrées depuis le serveur
/// Laravel : vérifie que l'application sait les lire.
void main() {
  Map<String, dynamic> fixture(String name) =>
      jsonDecode(File('test/fixtures/$name.json').readAsStringSync())
          as Map<String, dynamic>;

  test('catalogue des offres commerciales', () {
    final catalog = CommercialOfferCatalog.fromJson(
      fixture('commercial_offers'),
    );

    expect(catalog.offers, hasLength(2));
    expect(
      catalog.ofType(CommercialOfferType.membership).single.price.amount,
      5000,
    );
    expect(catalog.offers.first.duration.unit, OfferDurationUnit.month);
    expect(catalog.offers.last.duration.isUnlimited, isTrue);
    expect(catalog.offers.last.duration.value, isNull);
    expect(catalog.acceptancePrice, 500);
  });

  test('un ancien catalogue en cache reste lisible', () {
    // Avant le prix unique : offre « request_acceptance », pas de « meta ».
    final catalog = CommercialOfferCatalog.fromJson({
      'data': [
        {
          'id': 1,
          'type': 'membership',
          'name': 'Adhésion mensuelle',
          'quantity': null,
          'price': {'amount': 5000, 'currency': 'XOF'},
          'duration': {'type': 'limited', 'value': 1, 'unit': 'month'},
          'position': 1,
        },
        {
          'id': 2,
          'type': 'request_acceptance',
          'name': 'Acceptation',
          'quantity': null,
          'price': {'amount': 500, 'currency': 'XOF'},
          'duration': {'type': 'unlimited', 'value': null, 'unit': null},
          'position': 1,
        },
      ],
    });

    expect(catalog.offers.single.name, 'Adhésion mensuelle');
    expect(catalog.acceptancePrice, isNull);
  });

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

  test('les booléens par défaut tolèrent une ancienne réponse nulle', () {
    final data =
        Map<String, dynamic>.from(
            fixture('my_business')['data'] as Map<String, dynamic>,
          )
          ..['is_mobile'] = null
          ..['is_24_7'] = null;

    final business = MyBusiness.fromJson(data);

    expect(business.isMobile, isFalse);
    expect(business.is24h, isFalse);
  });

  test('résultats de recherche de garages', () {
    final garages = [
      for (final item in fixture('search_results')['data'] as List)
        GarageResult.fromJson(item as Map<String, dynamic>),
    ];

    expect(garages.single.name, 'Garage Essai');
    expect(garages.single.distanceMeters, inInclusiveRange(30, 80));
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
    // Numéro masqué tant que le client n'est pas en contact (C4).
    expect(garage.contact, isNull);
    expect(garage.hasPendingContactRequest, isFalse);
  });

  test('fiche du garage avec ses droits (adhésion, packs)', () {
    final business = MyBusiness.fromJson(
      fixture('my_business_entitlements')['data'] as Map<String, dynamic>,
    );

    expect(business.entitlements.isVisible, isTrue);
    expect(business.entitlements.servicesQuota, 1);
    expect(business.entitlements.hiddenServicesCount, 1);
    expect(
      business.entitlements.membershipEndsAt,
      DateTime.utc(2026, 12, 19, 10),
    );
    expect(business.services.map((s) => s.isPublished), [true, false]);
  });

  test('offres du garage', () {
    final overview = SubscriptionsOverview.fromJson(fixture('subscriptions'));

    expect(overview.active, hasLength(2));
    expect(overview.active.first.type, CommercialOfferType.membership);
    expect(overview.active.first.autoRenew, isTrue);
    expect(overview.active.last.isGranted, isTrue);
    expect(overview.acceptancePrice, 500);
    expect(overview.balance, 2000);
  });

  test('signal de détresse, en attente puis accepté', () {
    final open = DistressSignal.fromJson(
      fixture('distress_signal_open')['data'] as Map<String, dynamic>,
    );
    final accepted = DistressSignal.fromJson(
      fixture('distress_signal_accepted')['data'] as Map<String, dynamic>,
    );

    expect(open.status, DistressSignalStatus.open);
    expect(open.recipientsCount, 1);
    expect(open.garage, isNull);
    expect(accepted.status, DistressSignalStatus.accepted);
    expect(accepted.garage?.phone, '+22890513067');
  });

  test('demandes reçues par le garage', () {
    final signal = ReceivedDistressSignal.fromJson(
      (fixture('received_distress_signals')['data'] as List).single
          as Map<String, dynamic>,
    );
    final request = ReceivedContactRequest.fromJson(
      (fixture('received_contact_requests')['data'] as List).single
          as Map<String, dynamic>,
    );

    expect(signal.status, ReceivedSignalStatus.open);
    expect(signal.distanceMeters, greaterThan(0));
    expect(signal.client, isNull);
    expect(request.status, ContactRequestStatus.pending);
    expect(request.client.name, 'Ama');
    expect(request.client.phone, isNull);
  });

  test('contacts échangés, côté client et côté garage', () {
    final garage = SavedGarage.fromJson(
      (fixture('contacts')['data'] as List).single as Map<String, dynamic>,
    );
    final client = BusinessClient.fromJson(
      (fixture('clients')['data'] as List).single as Map<String, dynamic>,
    );
    final request = ContactRequest.fromJson(
      (fixture('contact_requests')['data'] as List).single
          as Map<String, dynamic>,
    );

    expect(garage.garage.phone, '+22890513067');
    expect(client.phone, '+22891000000');
    expect(client.fromDistressSignal, isTrue);
    expect(request.status, ContactRequestStatus.pending);
    expect(request.garage.phone, isNull);
  });
}
