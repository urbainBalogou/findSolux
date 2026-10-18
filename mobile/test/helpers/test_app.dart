import 'package:findsolux/app.dart';
import 'package:findsolux/core/location/location_service.dart';
import 'package:findsolux/core/network/api_exception.dart';
import 'package:findsolux/features/auth/data/auth_repository.dart';
import 'package:findsolux/features/auth/data/session_storage.dart';
import 'package:findsolux/features/auth/domain/auth_session.dart';
import 'package:findsolux/features/pro/data/my_business_repository.dart';
import 'package:findsolux/features/pro/domain/my_business.dart';
import 'package:findsolux/features/reference/data/reference_repository.dart';
import 'package:findsolux/features/reference/domain/reference_data.dart';
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
  ]);

  final LocationFix fix;

  @override
  Future<LocationFix> currentPosition() async => fix;
}

Future<void> pumpApp(
  WidgetTester tester, {
  SessionStorage? storage,
  AuthRepository? authRepository,
  MyBusinessRepository? businessRepository,
  LocationService? locationService,
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
