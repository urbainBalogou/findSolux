/// Fiche du garage vue par son propriétaire (`GET /my-business`).
class MyBusiness {
  const MyBusiness({
    required this.id,
    required this.slug,
    required this.status,
    required this.tradeId,
    required this.name,
    required this.phone,
    required this.whatsappPhone,
    required this.description,
    required this.districtId,
    required this.latitude,
    required this.longitude,
    required this.landmark,
    required this.isMobile,
    required this.serviceRadiusKm,
    required this.is24h,
    required this.vehicleTypeIds,
    required this.services,
    required this.openingHours,
    required this.photos,
  });

  factory MyBusiness.fromJson(Map<String, dynamic> json) => MyBusiness(
    id: json['id'] as int,
    slug: json['slug'] as String,
    status: json['status'] as String,
    tradeId: json['trade_id'] as int,
    name: json['name'] as String,
    phone: json['phone'] as String,
    whatsappPhone: json['whatsapp_phone'] as String?,
    description: json['description'] as String?,
    districtId: json['district_id'] as int,
    latitude: (json['latitude'] as num).toDouble(),
    longitude: (json['longitude'] as num).toDouble(),
    landmark: json['landmark'] as String?,
    isMobile: json['is_mobile'] as bool,
    serviceRadiusKm: json['service_radius_km'] as int?,
    is24h: json['is_24_7'] as bool,
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
    photos: [
      for (final photo in json['photos'] as List)
        BusinessPhoto.fromJson(photo as Map<String, dynamic>),
    ],
  );

  static const maxPhotos = 8;

  final int id;
  final String slug;
  final String status;
  final int tradeId;
  final String name;
  final String phone;
  final String? whatsappPhone;
  final String? description;
  final int districtId;
  final double latitude;
  final double longitude;
  final String? landmark;
  final bool isMobile;
  final int? serviceRadiusKm;
  final bool is24h;
  final List<int> vehicleTypeIds;
  final List<BusinessServiceItem> services;
  final List<OpeningRange> openingHours;
  final List<BusinessPhoto> photos;

  bool get isPublished => status == 'published';

  /// Éléments qui rendent une fiche plus convaincante, encore absents.
  List<String> get missingItems => [
    if (description?.isNotEmpty != true) 'Une description',
    if (landmark?.isNotEmpty != true) 'Un point de repère',
    if (openingHours.isEmpty) 'Vos horaires',
    if (!services.any((service) => service.hasPrice)) 'Des prix indicatifs',
    if (photos.isEmpty) 'Des photos de l’atelier',
  ];
}

class BusinessServiceItem {
  const BusinessServiceItem({
    required this.serviceId,
    this.vehicleTypeId,
    this.priceMin,
    this.priceMax,
    this.description,
  });

  factory BusinessServiceItem.fromJson(Map<String, dynamic> json) =>
      BusinessServiceItem(
        serviceId: json['service_id'] as int,
        vehicleTypeId: json['vehicle_type_id'] as int?,
        priceMin: json['price_min'] as int?,
        priceMax: json['price_max'] as int?,
        description: json['description'] as String?,
      );

  final int serviceId;

  /// Null : le prix vaut pour tous les véhicules pris en charge.
  final int? vehicleTypeId;
  final int? priceMin;
  final int? priceMax;
  final String? description;

  bool get hasPrice => priceMin != null || priceMax != null;

  Map<String, Object?> toJson() => {
    'service_id': serviceId,
    'vehicle_type_id': vehicleTypeId,
    'price_min': priceMin,
    'price_max': priceMax,
    'description': description,
  };
}

/// Plage d'ouverture ; [day] : 1 = lundi … 7 = dimanche.
class OpeningRange {
  const OpeningRange({
    required this.day,
    required this.opensAt,
    required this.closesAt,
  });

  factory OpeningRange.fromJson(Map<String, dynamic> json) => OpeningRange(
    day: json['day'] as int,
    opensAt: json['opens_at'] as String,
    closesAt: json['closes_at'] as String,
  );

  final int day;

  /// Heure au format « HH:MM ».
  final String opensAt;
  final String closesAt;

  Map<String, Object> toJson() => {
    'day': day,
    'opens_at': opensAt,
    'closes_at': closesAt,
  };
}

class BusinessPhoto {
  const BusinessPhoto({
    required this.id,
    required this.url,
    required this.thumbnailUrl,
  });

  factory BusinessPhoto.fromJson(Map<String, dynamic> json) => BusinessPhoto(
    id: json['id'] as int,
    url: json['url'] as String,
    thumbnailUrl: json['thumbnail_url'] as String,
  );

  final int id;
  final String url;
  final String thumbnailUrl;
}

/// Données de l'inscription express.
class NewBusiness {
  const NewBusiness({
    required this.tradeId,
    required this.name,
    required this.phone,
    required this.whatsappPhone,
    required this.districtId,
    required this.latitude,
    required this.longitude,
    required this.vehicleTypeIds,
    required this.serviceIds,
  });

  final int tradeId;
  final String name;
  final String phone;
  final String? whatsappPhone;
  final int districtId;
  final double latitude;
  final double longitude;
  final List<int> vehicleTypeIds;
  final List<int> serviceIds;

  Map<String, Object?> toJson() => {
    'trade_id': tradeId,
    'name': name,
    'phone': phone,
    'whatsapp_phone': whatsappPhone,
    'district_id': districtId,
    'latitude': latitude,
    'longitude': longitude,
    'vehicle_type_ids': vehicleTypeIds,
    'service_ids': serviceIds,
  };
}
