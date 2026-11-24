import '../../pro/domain/my_business.dart';

/// Fiche publique d'un garage (`GET /businesses/{slug}`), vue par les clients.
class PublicBusiness {
  const PublicBusiness({
    required this.slug,
    required this.name,
    required this.description,
    required this.districtId,
    required this.landmark,
    required this.latitude,
    required this.longitude,
    required this.contact,
    required this.hasPendingContactRequest,
    required this.isMobile,
    required this.serviceRadiusKm,
    required this.is24h,
    required this.isOpenNow,
    required this.hasOpeningHours,
    required this.vehicleTypeIds,
    required this.services,
    required this.openingHours,
    required this.photoUrls,
    required this.shareUrl,
  });

  factory PublicBusiness.fromJson(Map<String, dynamic> json) => PublicBusiness(
    slug: json['slug'] as String,
    name: json['name'] as String,
    description: json['description'] as String?,
    districtId: json['district_id'] as int,
    landmark: json['landmark'] as String?,
    latitude: (json['latitude'] as num).toDouble(),
    longitude: (json['longitude'] as num).toDouble(),
    contact: json['contact'] == null
        ? null
        : GarageContact.fromJson(json['contact'] as Map<String, dynamic>),
    hasPendingContactRequest: json['pending_contact_request'] != null,
    isMobile: json['is_mobile'] as bool,
    serviceRadiusKm: json['service_radius_km'] as int?,
    is24h: json['is_24_7'] as bool,
    isOpenNow: json['is_open_now'] as bool,
    hasOpeningHours: json['has_opening_hours'] as bool,
    vehicleTypeIds: [
      for (final id in json['vehicle_type_ids'] as List) id as int,
    ],
    services: [
      for (final item in json['services'] as List)
        BusinessServiceItem.fromJson(item as Map<String, dynamic>),
    ],
    openingHours: [
      for (final range in json['opening_hours'] as List)
        OpeningRange.fromJson(range as Map<String, dynamic>),
    ],
    photoUrls: [
      for (final photo in json['photos'] as List)
        (photo as Map<String, dynamic>)['url'] as String,
    ],
    shareUrl: json['share_url'] as String,
  );

  final String slug;
  final String name;
  final String? description;
  final int districtId;
  final String? landmark;
  final double latitude;
  final double longitude;

  /// Numéros du garage : seulement pour un client déjà en contact avec lui
  /// (C4). Sinon, le client envoie une demande de contact.
  final GarageContact? contact;

  /// Une demande de contact de ce client attend la réponse du garage.
  final bool hasPendingContactRequest;
  final bool isMobile;
  final int? serviceRadiusKm;
  final bool is24h;
  final bool isOpenNow;
  final bool hasOpeningHours;
  final List<int> vehicleTypeIds;
  final List<BusinessServiceItem> services;
  final List<OpeningRange> openingHours;
  final List<String> photoUrls;

  /// Page web publique, à partager sur WhatsApp.
  final String shareUrl;
}

class GarageContact {
  const GarageContact({required this.phone, this.whatsappPhone});

  factory GarageContact.fromJson(Map<String, dynamic> json) => GarageContact(
    phone: json['phone'] as String,
    whatsappPhone: json['whatsapp_phone'] as String?,
  );

  final String phone;
  final String? whatsappPhone;
}

/// Actions comptées pour les statistiques du garagiste.
enum GarageEvent { view, call, whatsapp, directions, share }
