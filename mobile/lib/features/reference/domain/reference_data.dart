import 'dart:math' as math;

/// Référentiels de l'API (`GET /reference-data`) : villes et quartiers,
/// métiers et services, types de véhicules.
class ReferenceData {
  const ReferenceData({
    required this.cities,
    required this.trades,
    required this.vehicleTypes,
  });

  factory ReferenceData.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>;
    return ReferenceData(
      cities: [
        for (final city in data['cities'] as List)
          City.fromJson(city as Map<String, dynamic>),
      ],
      trades: [
        for (final trade in data['trades'] as List)
          Trade.fromJson(trade as Map<String, dynamic>),
      ],
      vehicleTypes: [
        for (final type in data['vehicle_types'] as List)
          VehicleType.fromJson(type as Map<String, dynamic>),
      ],
    );
  }

  final List<City> cities;
  final List<Trade> trades;
  final List<VehicleType> vehicleTypes;

  List<District> get districts => [
    for (final city in cities) ...city.districts,
  ];

  District? districtById(int id) => _firstWhereOrNull(districts, id);

  Trade? tradeById(int id) => _firstWhereOrNull(trades, id);

  Service? serviceById(int id) =>
      _firstWhereOrNull([for (final trade in trades) ...trade.services], id);

  VehicleType? vehicleTypeById(int id) => _firstWhereOrNull(vehicleTypes, id);

  /// Quartier dont le centre est le plus proche de la position donnée.
  District? nearestDistrict(double latitude, double longitude) {
    District? nearest;
    var best = double.infinity;
    for (final district in districts) {
      final distance = distanceInMeters(
        latitude,
        longitude,
        district.latitude,
        district.longitude,
      );
      if (distance < best) {
        best = distance;
        nearest = district;
      }
    }
    return nearest;
  }
}

T? _firstWhereOrNull<T extends _Identified>(Iterable<T> items, int id) {
  for (final item in items) {
    if (item.id == id) return item;
  }
  return null;
}

abstract interface class _Identified {
  int get id;
}

class City implements _Identified {
  const City({
    required this.id,
    required this.name,
    required this.latitude,
    required this.longitude,
    required this.districts,
  });

  factory City.fromJson(Map<String, dynamic> json) => City(
    id: json['id'] as int,
    name: json['name'] as String,
    latitude: (json['latitude'] as num).toDouble(),
    longitude: (json['longitude'] as num).toDouble(),
    districts: [
      for (final district in json['districts'] as List)
        District.fromJson(district as Map<String, dynamic>),
    ],
  );

  @override
  final int id;
  final String name;
  final double latitude;
  final double longitude;
  final List<District> districts;
}

class District implements _Identified {
  const District({
    required this.id,
    required this.name,
    required this.latitude,
    required this.longitude,
  });

  factory District.fromJson(Map<String, dynamic> json) => District(
    id: json['id'] as int,
    name: json['name'] as String,
    latitude: (json['latitude'] as num).toDouble(),
    longitude: (json['longitude'] as num).toDouble(),
  );

  @override
  final int id;
  final String name;
  final double latitude;
  final double longitude;
}

class Trade implements _Identified {
  const Trade({
    required this.id,
    required this.slug,
    required this.name,
    required this.services,
  });

  factory Trade.fromJson(Map<String, dynamic> json) => Trade(
    id: json['id'] as int,
    slug: json['slug'] as String,
    name: json['name'] as String,
    services: [
      for (final service in json['services'] as List)
        Service.fromJson(service as Map<String, dynamic>),
    ],
  );

  @override
  final int id;
  final String slug;
  final String name;
  final List<Service> services;
}

class Service implements _Identified {
  const Service({required this.id, required this.name, this.icon});

  factory Service.fromJson(Map<String, dynamic> json) => Service(
    id: json['id'] as int,
    name: json['name'] as String,
    icon: json['icon'] as String?,
  );

  @override
  final int id;
  final String name;
  final String? icon;
}

class VehicleType implements _Identified {
  const VehicleType({required this.id, required this.name, this.icon});

  factory VehicleType.fromJson(Map<String, dynamic> json) => VehicleType(
    id: json['id'] as int,
    name: json['name'] as String,
    icon: json['icon'] as String?,
  );

  @override
  final int id;
  final String name;
  final String? icon;
}

/// Distance à vol d'oiseau (formule de haversine).
double distanceInMeters(double lat1, double lng1, double lat2, double lng2) {
  const earthRadius = 6371000.0;
  double radians(double degrees) => degrees * math.pi / 180;
  final dLat = radians(lat2 - lat1);
  final dLng = radians(lng2 - lng1);
  final a =
      math.pow(math.sin(dLat / 2), 2) +
      math.cos(radians(lat1)) *
          math.cos(radians(lat2)) *
          math.pow(math.sin(dLng / 2), 2);
  return 2 * earthRadius * math.asin(math.sqrt(a));
}
