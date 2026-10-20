/// Garage dans les résultats de recherche (`GET /businesses/search`).
class GarageResult {
  const GarageResult({
    required this.id,
    required this.slug,
    required this.name,
    required this.districtId,
    required this.landmark,
    required this.latitude,
    required this.longitude,
    required this.distanceMeters,
    required this.isOpenNow,
    required this.hasOpeningHours,
    required this.isMobile,
    required this.is24h,
    required this.phone,
    required this.whatsappPhone,
    required this.vehicleTypeIds,
    required this.serviceIds,
    required this.photoThumbnailUrl,
  });

  factory GarageResult.fromJson(Map<String, dynamic> json) => GarageResult(
    id: json['id'] as int,
    slug: json['slug'] as String,
    name: json['name'] as String,
    districtId: json['district_id'] as int,
    landmark: json['landmark'] as String?,
    latitude: (json['latitude'] as num).toDouble(),
    longitude: (json['longitude'] as num).toDouble(),
    distanceMeters: json['distance_m'] as int,
    isOpenNow: json['is_open_now'] as bool,
    hasOpeningHours: json['has_opening_hours'] as bool,
    isMobile: json['is_mobile'] as bool,
    is24h: json['is_24_7'] as bool,
    phone: json['phone'] as String,
    whatsappPhone: json['whatsapp_phone'] as String?,
    vehicleTypeIds: [
      for (final id in json['vehicle_type_ids'] as List) id as int,
    ],
    serviceIds: [for (final id in json['service_ids'] as List) id as int],
    photoThumbnailUrl: json['photo_thumbnail_url'] as String?,
  );

  final int id;

  /// Identifiant de la fiche publique (`/businesses/{slug}`).
  final String slug;
  final String name;
  final int districtId;
  final String? landmark;
  final double latitude;
  final double longitude;
  final int distanceMeters;
  final bool isOpenNow;

  /// Sans horaires renseignés, [isOpenNow] faux ne veut pas dire « fermé ».
  final bool hasOpeningHours;
  final bool isMobile;
  final bool is24h;
  final String phone;
  final String? whatsappPhone;
  final List<int> vehicleTypeIds;
  final List<int> serviceIds;
  final String? photoThumbnailUrl;
}

/// Lieu trouvé par la recherche (`GET /places/search`) : quartier connu ou
/// lieu Google.
class PlaceResult {
  const PlaceResult({
    required this.isDistrict,
    required this.name,
    required this.address,
    required this.latitude,
    required this.longitude,
  });

  factory PlaceResult.fromJson(Map<String, dynamic> json) => PlaceResult(
    isDistrict: json['type'] == 'district',
    name: json['name'] as String,
    address: json['address'] as String?,
    latitude: (json['latitude'] as num).toDouble(),
    longitude: (json['longitude'] as num).toDouble(),
  );

  final bool isDistrict;
  final String name;
  final String? address;
  final double latitude;
  final double longitude;
}

/// Point et critères de la recherche en cours.
class SearchQuery {
  const SearchQuery({
    required this.latitude,
    required this.longitude,
    required this.label,
    this.radiusKm = defaultRadiusKm,
    this.vehicleTypeId,
    this.openNow = false,
  });

  static const defaultRadiusKm = 10;

  /// Rayons proposés successivement quand aucun garage n'est trouvé.
  static const radiusSteps = [10, 20, 30];

  final double latitude;
  final double longitude;

  /// « Ma position », nom du quartier ou du lieu recherché.
  final String label;
  final int radiusKm;
  final int? vehicleTypeId;
  final bool openNow;

  /// Rayon suivant, ou null si le rayon maximal est atteint.
  int? get widerRadiusKm {
    for (final step in radiusSteps) {
      if (step > radiusKm) return step;
    }
    return null;
  }

  SearchQuery copyWith({
    double? latitude,
    double? longitude,
    String? label,
    int? radiusKm,
    int? Function()? vehicleTypeId,
    bool? openNow,
  }) {
    return SearchQuery(
      latitude: latitude ?? this.latitude,
      longitude: longitude ?? this.longitude,
      label: label ?? this.label,
      radiusKm: radiusKm ?? this.radiusKm,
      vehicleTypeId: vehicleTypeId != null
          ? vehicleTypeId()
          : this.vehicleTypeId,
      openNow: openNow ?? this.openNow,
    );
  }

  Map<String, Object> toParameters() => {
    'latitude': latitude,
    'longitude': longitude,
    'radius_km': radiusKm,
    'vehicle_type_id': ?vehicleTypeId,
    if (openNow) 'open_now': 1,
  };
}
