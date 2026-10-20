import 'package:findsolux/app.dart';
import 'package:findsolux/core/device/device_id.dart';
import 'package:findsolux/core/location/location_service.dart';
import 'package:findsolux/core/network/api_exception.dart';
import 'package:findsolux/features/auth/data/auth_repository.dart';
import 'package:findsolux/features/auth/data/session_storage.dart';
import 'package:findsolux/features/auth/domain/auth_session.dart';
import 'package:findsolux/features/garage/application/contact_launcher.dart';
import 'package:findsolux/features/garage/data/garage_repository.dart';
import 'package:findsolux/features/garage/domain/public_business.dart';
import 'package:findsolux/features/pro/data/my_business_repository.dart';
import 'package:findsolux/features/pro/domain/my_business.dart';
import 'package:findsolux/features/reference/data/reference_repository.dart';
import 'package:findsolux/features/reference/domain/reference_data.dart';
import 'package:findsolux/features/search/data/search_repository.dart';
import 'package:findsolux/features/search/domain/search_models.dart';
import 'package:findsolux/core/maps/native_map.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

const validCode = '123456';

class InMemorySessionStorage implements SessionStorage {
  InMemorySessionStorage([this.session]);

  AuthSession? session;

  @override
  Future<AuthSession?> read() async => session;

  @override
  Future<void> write(AuthSession session) async => this.session = session;

  @override
  Future<void> clear() async => session = null;
}

/// API de connexion simulée : seul [validCode] est accepté.
class FakeAuthRepository implements AuthRepository {
  final requestedPhones = <String>[];
  var loggedOut = false;

  @override
  Future<int> requestCode(String phone) async {
    requestedPhones.add(phone);
    return 60;
  }

  @override
  Future<AuthSession> verifyCode({
    required String phone,
    required String code,
  }) async {
    if (code != validCode) {
      throw const ApiException('Code incorrect. Il vous reste 4 essai(s).');
    }
    return AuthSession(
      token: 'jeton',
      user: AppUser(id: 1, phone: phone),
    );
  }

  @override
  Future<void> logout() async => loggedOut = true;
}

AuthSession sessionFor(String phone, {String? name}) {
  return AuthSession(
    token: 'jeton',
    user: AppUser(id: 1, phone: phone, name: name),
  );
}

const adidogome = District(
  id: 10,
  name: 'Adidogomé',
  latitude: 6.2101,
  longitude: 1.1547,
);
const be = District(id: 11, name: 'Bè', latitude: 6.1427, longitude: 1.2485);
const oilChange = Service(id: 100, name: 'Vidange et entretien');
const brakes = Service(id: 101, name: 'Freins');
const tyres = Service(id: 102, name: 'Pneus et vulcanisation');
const car = VehicleType(id: 1, name: 'Voiture');
const moto = VehicleType(id: 2, name: 'Moto');

const testReference = ReferenceData(
  cities: [
    City(
      id: 1,
      name: 'Lomé',
      latitude: 6.1319,
      longitude: 1.2228,
      districts: [adidogome, be],
    ),
  ],
  trades: [
    Trade(
      id: 1,
      slug: 'mecanique',
      name: 'Mécanique',
      services: [oilChange, brakes, tyres],
    ),
  ],
  vehicleTypes: [car, moto],
);

MyBusiness testBusiness({
  String name = 'Garage de la Paix',
  String phone = '+22890123456',
  String? whatsappPhone = '+22890123456',
  int districtId = 10,
  List<int> vehicleTypeIds = const [2],
  List<BusinessServiceItem> services = const [
    BusinessServiceItem(serviceId: 100),
    BusinessServiceItem(serviceId: 101),
  ],
  List<OpeningRange> openingHours = const [],
}) {
  return MyBusiness(
    id: 1,
    slug: 'garage-de-la-paix-adidogome',
    status: 'published',
    tradeId: 1,
    name: name,
    phone: phone,
    whatsappPhone: whatsappPhone,
    description: null,
    districtId: districtId,
    latitude: 6.2101,
    longitude: 1.1547,
    landmark: null,
    isMobile: false,
    serviceRadiusKm: null,
    is24h: false,
    vehicleTypeIds: vehicleTypeIds,
    services: services,
    openingHours: openingHours,
    photos: const [],
  );
}

/// Fiche simulée : enregistre ce que l'application envoie.
class FakeMyBusinessRepository implements MyBusinessRepository {
  FakeMyBusinessRepository([this.business]);

  MyBusiness? business;
  NewBusiness? created;
  Map<String, Object?>? lastUpdate;
  List<BusinessServiceItem>? lastServices;
  List<OpeningRange>? lastOpeningHours;

  @override
  Future<MyBusiness?> fetch() async => business;

  @override
  Future<MyBusiness> create(NewBusiness business) async {
    created = business;
    return this.business = testBusiness(
      name: business.name,
      phone: business.phone,
      whatsappPhone: business.whatsappPhone,
      districtId: business.districtId,
      vehicleTypeIds: business.vehicleTypeIds,
      services: [
        for (final id in business.serviceIds)
          BusinessServiceItem(serviceId: id),
      ],
    );
  }

  @override
  Future<MyBusiness> update(Map<String, Object?> changes) async {
    lastUpdate = changes;
    return business!;
  }

  @override
  Future<MyBusiness> replaceServices(List<BusinessServiceItem> services) async {
    lastServices = services;
    return business = testBusiness(
      services: services,
      openingHours: business!.openingHours,
    );
  }

  @override
  Future<MyBusiness> replaceOpeningHours(List<OpeningRange> ranges) async {
    lastOpeningHours = ranges;
    return business = testBusiness(
      services: business!.services,
      openingHours: ranges,
    );
  }

  @override
  Future<MyBusiness> uploadPhoto(String filePath) async => business!;

  @override
  Future<MyBusiness> deletePhoto(int photoId) async => business!;

  @override
  Future<MyBusiness> reorderPhotos(List<int> photoIds) async => business!;
}

class FakeLocationService implements LocationService {
  FakeLocationService([
    this.fix = const LocationFix(
      latitude: 6.2105,
      longitude: 1.1550,
      accuracyMeters: 8,
    ),
  ]) : error = null;

  /// Localisation impossible (GPS coupé, autorisation refusée…).
  FakeLocationService.failing(String message)
    : fix = null,
      error = LocationException(message);

  final LocationFix? fix;
  final LocationException? error;
  var calls = 0;

  @override
  Future<LocationFix> currentPosition() async {
    calls++;
    if (error != null) throw error!;
    return fix!;
  }
}

/// Recherche simulée : renvoie [garages] et [places], et garde les requêtes.
class FakeSearchRepository implements SearchRepository {
  FakeSearchRepository({this.garages = const [], this.places = const []});

  List<GarageResult> garages;
  List<PlaceResult> places;
  final queries = <SearchQuery>[];
  final placeQueries = <String>[];

  SearchQuery? get lastQuery => queries.lastOrNull;

  @override
  Future<List<GarageResult>> searchGarages(SearchQuery query) async {
    queries.add(query);
    return garages;
  }

  @override
  Future<List<PlaceResult>> searchPlaces(String text) async {
    placeQueries.add(text);
    return places;
  }
}

GarageResult garageResult({
  int id = 1,
  String name = 'Garage de la Paix',
  int distanceMeters = 300,
  bool isOpenNow = true,
  bool hasOpeningHours = true,
  List<int> serviceIds = const [100, 101, 102],
}) {
  return GarageResult(
    id: id,
    slug: 'garage-$id',
    name: name,
    districtId: adidogome.id,
    landmark: 'Près du marché',
    latitude: 6.2101 + id / 1000,
    longitude: 1.1547,
    distanceMeters: distanceMeters,
    isOpenNow: isOpenNow,
    hasOpeningHours: hasOpeningHours,
    isMobile: false,
    is24h: false,
    phone: '+22890123456',
    whatsappPhone: '+22890123456',
    vehicleTypeIds: const [2],
    serviceIds: serviceIds,
    photoThumbnailUrl: null,
  );
}

PublicBusiness publicGarage({
  String slug = 'garage-1',
  String name = 'Garage de la Paix',
  String? whatsappPhone = '+22890123456',
  List<OpeningRange> openingHours = const [
    OpeningRange(day: 1, opensAt: '08:00', closesAt: '18:00'),
  ],
}) {
  return PublicBusiness(
    slug: slug,
    name: name,
    description: 'Spécialiste moto depuis 15 ans.',
    districtId: adidogome.id,
    landmark: 'Près du marché',
    latitude: 6.2101,
    longitude: 1.1547,
    phone: '+22890123456',
    whatsappPhone: whatsappPhone,
    isMobile: true,
    serviceRadiusKm: 5,
    is24h: false,
    isOpenNow: true,
    hasOpeningHours: openingHours.isNotEmpty,
    vehicleTypeIds: [moto.id],
    services: [
      BusinessServiceItem(
        serviceId: oilChange.id,
        priceMin: 5000,
        priceMax: 15000,
      ),
      BusinessServiceItem(serviceId: brakes.id, vehicleTypeId: moto.id),
    ],
    openingHours: openingHours,
    photoUrls: const [],
    shareUrl: 'https://findsolux.tg/garages/$slug',
  );
}

/// Fiches publiques simulées ; garde les vues et clics envoyés.
class FakeGarageRepository implements GarageRepository {
  FakeGarageRepository([Map<String, PublicBusiness>? garages])
    : garages = garages ?? {'garage-1': publicGarage()};

  final Map<String, PublicBusiness> garages;
  final events = <GarageEvent>[];

  @override
  Future<PublicBusiness> fetch(String slug) async {
    final garage = garages[slug];
    if (garage == null) {
      throw const ApiException('Élément introuvable.', statusCode: 404);
    }
    return garage;
  }

  @override
  Future<void> recordEvent(
    String slug,
    GarageEvent event,
    String deviceId,
  ) async => events.add(event);
}

/// Ouverture du téléphone, de WhatsApp… simulée.
class FakeContactLauncher implements ContactLauncher {
  FakeContactLauncher({this.succeeds = true});

  final bool succeeds;
  final opened = <String>[];

  @override
  Future<bool> call(String phone) async => _record('appel $phone');

  @override
  Future<bool> whatsApp(String phone, String message) async =>
      _record('whatsapp $phone $message');

  @override
  Future<bool> directions(double latitude, double longitude) async =>
      _record('itineraire $latitude,$longitude');

  @override
  Future<bool> share({required String text, String? subject}) async =>
      _record('partage $text');

  bool _record(String action) {
    opened.add(action);
    return succeeds;
  }
}

/// Texte d'aide de la barre de recherche de l'écran d'accueil.
const searchHint = 'Rechercher un quartier, un lieu…';

Future<void> pumpApp(
  WidgetTester tester, {
  SessionStorage? storage,
  AuthRepository? authRepository,
  MyBusinessRepository? businessRepository,
  LocationService? locationService,
  SearchRepository? searchRepository,
  GarageRepository? garageRepository,
  ContactLauncher? contactLauncher,
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        sessionStorageProvider.overrideWithValue(
          storage ?? InMemorySessionStorage(),
        ),
        authRepositoryProvider.overrideWithValue(
          authRepository ?? FakeAuthRepository(),
        ),
        myBusinessRepositoryProvider.overrideWithValue(
          businessRepository ?? FakeMyBusinessRepository(),
        ),
        locationServiceProvider.overrideWithValue(
          locationService ?? FakeLocationService(),
        ),
        referenceDataProvider.overrideWith((ref) async => testReference),
        searchRepositoryProvider.overrideWithValue(
          searchRepository ?? FakeSearchRepository(),
        ),
        nativeMapEnabledProvider.overrideWithValue(false),
        garageRepositoryProvider.overrideWithValue(
          garageRepository ?? FakeGarageRepository(),
        ),
        contactLauncherProvider.overrideWithValue(
          contactLauncher ?? FakeContactLauncher(),
        ),
        deviceIdProvider.overrideWith((ref) async => 'appareil-de-test'),
      ],
      child: const FindSoluxApp(),
    ),
  );
  await tester.pumpAndSettle();
}

/// Écran haut : les longs formulaires tiennent sans défilement.
void useTallScreen(WidgetTester tester) {
  tester.view
    ..physicalSize = const Size(1080, 5000)
    ..devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}
