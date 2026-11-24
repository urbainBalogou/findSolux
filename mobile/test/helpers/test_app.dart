import 'package:findsolux/app.dart';
import 'package:findsolux/core/device/device_id.dart';
import 'package:findsolux/core/location/location_service.dart';
import 'package:findsolux/core/network/api_exception.dart';
import 'package:findsolux/features/auth/data/auth_repository.dart';
import 'package:findsolux/features/auth/data/session_storage.dart';
import 'package:findsolux/features/auth/domain/auth_session.dart';
import 'package:findsolux/features/auth/data/google_auth_client.dart';
import 'package:findsolux/features/garage/application/contact_launcher.dart';
import 'package:findsolux/features/garage/data/garage_repository.dart';
import 'package:findsolux/features/garage/domain/public_business.dart';
import 'package:findsolux/features/pricing/data/commercial_offer_repository.dart';
import 'package:findsolux/features/pricing/domain/commercial_offer.dart';
import 'package:findsolux/features/pro/data/my_business_repository.dart';
import 'package:findsolux/features/pro/data/subscription_repository.dart';
import 'package:findsolux/features/pro/data/team_repository.dart';
import 'package:findsolux/features/pro/domain/my_business.dart';
import 'package:findsolux/features/pro/domain/subscriptions.dart';
import 'package:findsolux/features/pro/domain/team.dart';
import 'package:findsolux/features/reference/data/reference_repository.dart';
import 'package:findsolux/features/reference/domain/reference_data.dart';
import 'package:findsolux/features/requests/data/requests_repository.dart';
import 'package:findsolux/features/requests/domain/requests.dart';
import 'package:findsolux/features/search/data/search_repository.dart';
import 'package:findsolux/features/search/domain/search_models.dart';
import 'package:findsolux/features/wallet/data/wallet_repository.dart';
import 'package:findsolux/features/wallet/domain/wallet.dart';
import 'package:findsolux/core/maps/native_map.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

const validGoogleToken = 'jeton-google';

const testWallet = WalletSummary(
  balance: 2500,
  paymentMethods: [
    WalletPaymentMethod(code: 'moov_tg', label: 'Flooz (Moov Africa)'),
    WalletPaymentMethod(code: 'togocel', label: 'TMoney (Yas Togo)'),
  ],
  minimumTopUp: 100,
  maximumTopUp: 1000000,
  transactions: [],
  topUps: [],
);

class FakeWalletRepository implements WalletRepository {
  FakeWalletRepository([this.wallet = testWallet]);

  WalletSummary wallet;
  String? submittedPhone;
  int? submittedAmount;
  String? submittedPaymentMethod;

  @override
  Future<WalletSummary> fetch() async => wallet;

  @override
  Future<WalletTopUp> createTopUp({
    required int amount,
    required String paymentMethod,
    required String phone,
  }) async {
    submittedAmount = amount;
    submittedPaymentMethod = paymentMethod;
    submittedPhone = phone;
    return WalletTopUp(
      id: 'top-up-1',
      amount: amount,
      status: WalletTopUpStatus.pending,
      paymentMethod: testWallet.paymentMethods.first,
      requiresAction: true,
      message: 'Validez la demande sur votre téléphone.',
    );
  }

  @override
  Future<WalletTopUp> fetchTopUp(String id) async {
    final amount = submittedAmount ?? 0;
    wallet = WalletSummary(
      balance: wallet.balance + amount,
      paymentMethods: wallet.paymentMethods,
      minimumTopUp: wallet.minimumTopUp,
      maximumTopUp: wallet.maximumTopUp,
      transactions: wallet.transactions,
      topUps: wallet.topUps,
    );
    return WalletTopUp(
      id: id,
      amount: amount,
      status: WalletTopUpStatus.succeeded,
      paymentMethod: testWallet.paymentMethods.first,
      requiresAction: false,
      message: 'Le portefeuille a été crédité.',
    );
  }
}

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

/// Compte Google simulé : renvoie [validGoogleToken], sauf si l'utilisateur
/// ferme la fenêtre ([canceled]) ou si Google échoue ([error]).
class FakeGoogleAuthClient implements GoogleAuthClient {
  FakeGoogleAuthClient({this.canceled = false, this.error});

  final bool canceled;
  final GoogleAuthException? error;
  var signInCount = 0;
  var signedOut = false;

  @override
  Future<String?> signIn() async {
    signInCount++;
    if (error != null) throw error!;
    return canceled ? null : validGoogleToken;
  }

  @override
  Future<void> signOut() async => signedOut = true;
}

/// API de connexion simulée : seul [validGoogleToken] est accepté. Le compte
/// renvoyé a le numéro [phone] (`null` : première connexion).
class FakeAuthRepository implements AuthRepository {
  FakeAuthRepository({this.phone = '+22890123456', this.error});

  String? phone;
  final ApiException? error;
  final updatedPhones = <String>[];
  var loggedOut = false;

  AppUser get _user => AppUser(
    id: 1,
    name: 'Kossi Mensah',
    email: 'kossi.mensah@gmail.com',
    phone: phone,
  );

  @override
  Future<AuthSession> signInWithGoogle(String idToken) async {
    if (error != null) throw error!;
    if (idToken != validGoogleToken) {
      throw const ApiException('La connexion Google a échoué. Réessayez.');
    }
    return AuthSession(token: 'jeton', user: _user);
  }

  /// Seul « kodjo » / « motdepasse » est accepté, membre du Garage de la Paix.
  @override
  Future<AuthSession> signInAsTeamMember({
    required String username,
    required String password,
  }) async {
    if (username != 'kodjo' || password != 'motdepasse') {
      throw const ApiException('Identifiant ou mot de passe incorrect.');
    }
    return teamMemberSession();
  }

  @override
  Future<AppUser> updatePhone(String phone) async {
    updatedPhones.add(phone);
    this.phone = phone;
    return _user;
  }

  @override
  Future<void> logout() async => loggedOut = true;
}

/// Kodjo, répondeur du Garage de la Paix.
AuthSession teamMemberSession() => const AuthSession(
  token: 'jeton-equipe',
  user: AppUser(
    id: 7,
    name: 'Kodjo',
    teamMembership: TeamMembership(
      username: 'kodjo',
      businessId: 1,
      businessName: 'Garage de la Paix',
    ),
  ),
);

/// Équipe simulée : enregistre les membres créés, dans la limite de [quota].
class FakeTeamRepository implements TeamRepository {
  FakeTeamRepository({this.quota = 2, List<TeamMember> members = const []})
    : members = [...members];

  final int quota;
  final List<TeamMember> members;
  final changedPasswords = <int, String>{};

  @override
  Future<TeamOverview> fetch() async =>
      TeamOverview(members: [...members], quota: quota);

  @override
  Future<TeamMember> create(NewTeamMember member) async {
    if (members.any((existing) => existing.username == member.username)) {
      throw const ApiException(
        'Cet identifiant est déjà pris : choisissez-en un autre, par exemple '
        'en ajoutant le nom du garage.',
      );
    }
    final created = TeamMember(
      id: 100 + members.length,
      name: member.name,
      username: member.username,
      isActive: true,
    );
    members.add(created);
    return created;
  }

  @override
  Future<void> changePassword(int memberId, String password) async =>
      changedPasswords[memberId] = password;

  @override
  Future<void> remove(int memberId) async =>
      members.removeWhere((member) => member.id == memberId);
}

AuthSession sessionFor(String? phone, {String? name, String? email}) {
  return AuthSession(
    token: 'jeton',
    user: AppUser(id: 1, phone: phone, name: name, email: email),
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

const testCommercialOffers = CommercialOfferCatalog([
  CommercialOffer(
    id: 1,
    type: CommercialOfferType.membership,
    name: 'Adhésion mensuelle',
    quantity: null,
    price: OfferPrice(amount: 5000, currency: 'XOF'),
    duration: OfferDuration(
      isUnlimited: false,
      value: 1,
      unit: OfferDurationUnit.month,
    ),
    position: 1,
  ),
  CommercialOffer(
    id: 2,
    type: CommercialOfferType.membership,
    name: 'Adhésion annuelle',
    quantity: null,
    price: OfferPrice(amount: 50000, currency: 'XOF'),
    duration: OfferDuration(
      isUnlimited: false,
      value: 1,
      unit: OfferDurationUnit.year,
    ),
    position: 2,
  ),
  CommercialOffer(
    id: 3,
    type: CommercialOfferType.serviceBundle,
    name: 'Pack de 10 services',
    quantity: 10,
    price: OfferPrice(amount: 12000, currency: 'XOF'),
    duration: OfferDuration(
      isUnlimited: false,
      value: 1,
      unit: OfferDurationUnit.month,
    ),
    position: 1,
  ),
  CommercialOffer(
    id: 4,
    type: CommercialOfferType.staffBundle,
    name: 'Équipe de 3 personnes',
    quantity: 3,
    price: OfferPrice(amount: 8000, currency: 'XOF'),
    duration: OfferDuration(
      isUnlimited: false,
      value: 1,
      unit: OfferDurationUnit.month,
    ),
    position: 1,
  ),
], acceptancePrice: 500);

/// Adhésion active et pack de 5 services : fiche visible.
final visibleEntitlements = Entitlements(
  hasMembership: true,
  isUnlimitedMembership: false,
  membershipEndsAt: DateTime.utc(2026, 12, 19),
  servicesQuota: 5,
  servicesCount: 2,
  publishedServicesCount: 2,
  isVisible: true,
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
  Entitlements? entitlements,
}) {
  return MyBusiness(
    entitlements: entitlements ?? visibleEntitlements,
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
    vehicleTypeIds: const [2],
    serviceIds: serviceIds,
    photoThumbnailUrl: null,
  );
}

/// Numéros d'un garage, visibles du client en contact avec lui.
const garageContact = GarageContact(
  phone: '+22890123456',
  whatsappPhone: '+22890123456',
);

PublicBusiness publicGarage({
  String slug = 'garage-1',
  String name = 'Garage de la Paix',
  GarageContact? contact,
  bool hasPendingContactRequest = false,
  List<BusinessServiceItem> services = const [
    BusinessServiceItem(serviceId: 100, priceMin: 5000, priceMax: 15000),
    BusinessServiceItem(serviceId: 101, vehicleTypeId: 2),
  ],
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
    contact: contact,
    hasPendingContactRequest: hasPendingContactRequest,
    isMobile: true,
    serviceRadiusKm: 5,
    is24h: false,
    isOpenNow: true,
    hasOpeningHours: openingHours.isNotEmpty,
    vehicleTypeIds: [moto.id],
    services: services,
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
  final fetches = <String>[];

  @override
  Future<PublicBusiness> fetch(String slug) async {
    fetches.add(slug);
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

/// Signaux, demandes de contact et contacts simulés ; garde ce qui est envoyé.
class FakeRequestsRepository implements RequestsRepository {
  FakeRequestsRepository({
    this.inbox = const RequestInbox(
      distressSignals: [],
      contactRequests: [],
      acceptancePrice: 500,
      balance: 2000,
    ),
    this.savedGarages = const [],
  });

  RequestInbox inbox;
  List<SavedGarage> savedGarages;
  final sentSignals = <NewDistressSignal>[];
  final contactRequests = <String>[];
  final answers = <String>[];

  /// Réponse du serveur au prochain appel de [fetchDistressSignal].
  DistressSignal? nextSignalState;

  /// Erreur renvoyée par la prochaine réponse à une demande (solde…).
  ApiException? answerError;

  DistressSignal? _signal;

  @override
  Future<DistressSignal> sendDistressSignal(NewDistressSignal signal) async {
    sentSignals.add(signal);
    return _signal = distressSignal();
  }

  @override
  Future<List<DistressSignal>> fetchDistressSignals() async => [?_signal];

  @override
  Future<DistressSignal> fetchDistressSignal(String id) async =>
      _signal = nextSignalState ?? _signal ?? distressSignal(id: id);

  @override
  Future<DistressSignal> cancelDistressSignal(String id) async =>
      _signal = distressSignal(id: id, status: DistressSignalStatus.cancelled);

  @override
  Future<ContactRequest> requestContact(String slug, {String? message}) async {
    contactRequests.add('$slug ${message ?? ''}'.trim());
    return ContactRequest(
      id: 'demande-1',
      status: ContactRequestStatus.pending,
      message: message,
      garage: ContactGarage(slug: slug, name: 'Garage', districtId: 10),
      expiresAt: DateTime.now().add(const Duration(days: 1)),
      createdAt: DateTime.now(),
    );
  }

  @override
  Future<List<ContactRequest>> fetchContactRequests() async => const [];

  @override
  Future<List<SavedGarage>> fetchSavedGarages() async => savedGarages;

  @override
  Future<RequestInbox> fetchInbox() async => inbox;

  @override
  Future<ReceivedDistressSignal> answerDistressSignal(
    String id, {
    required bool accept,
  }) async {
    if (answerError != null) throw answerError!;
    answers.add('${accept ? 'accept' : 'decline'} $id');
    final accepted = ReceivedDistressSignal(
      id: id,
      status: accept
          ? ReceivedSignalStatus.accepted
          : ReceivedSignalStatus.declined,
      message: 'Crevaison du pneu avant',
      distanceMeters: 800,
      expiresAt: DateTime.now().add(const Duration(minutes: 5)),
      createdAt: DateTime.now(),
      client: accept
          ? const RequestClient(
              name: 'Kossi',
              phone: '+22891000000',
              latitude: 6.13,
              longitude: 1.22,
            )
          : null,
    );
    inbox = RequestInbox(
      distressSignals: [accepted],
      contactRequests: inbox.contactRequests,
      acceptancePrice: inbox.acceptancePrice,
      balance: inbox.balance - (accept ? inbox.acceptancePrice : 0),
    );
    return accepted;
  }

  @override
  Future<ReceivedContactRequest> answerContactRequest(
    String id, {
    required bool accept,
  }) async {
    if (answerError != null) throw answerError!;
    answers.add('${accept ? 'accept' : 'decline'} $id');
    return inbox.contactRequests.first;
  }

  @override
  Future<List<BusinessClient>> fetchClients() async => const [];
}

DistressSignal distressSignal({
  String id = 'signal-1',
  DistressSignalStatus status = DistressSignalStatus.open,
  ContactGarage? garage,
}) {
  return DistressSignal(
    id: id,
    status: status,
    message: 'Crevaison du pneu avant',
    latitude: 6.2105,
    longitude: 1.1550,
    recipientsCount: 3,
    expiresAt: DateTime.now().add(const Duration(minutes: 10)),
    createdAt: DateTime.now(),
    garage: garage,
  );
}

ReceivedDistressSignal receivedSignal({String id = 'signal-1'}) {
  return ReceivedDistressSignal(
    id: id,
    status: ReceivedSignalStatus.open,
    message: 'Crevaison du pneu avant',
    distanceMeters: 800,
    expiresAt: DateTime.now().add(const Duration(minutes: 8)),
    createdAt: DateTime.now(),
  );
}

/// Offres du garage simulées ; garde les achats.
class FakeSubscriptionRepository implements SubscriptionRepository {
  FakeSubscriptionRepository({this.purchaseError});

  final ApiException? purchaseError;
  final purchases = <String>[];
  final autoRenewChanges = <String>[];

  SubscriptionsOverview get _overview => SubscriptionsOverview(
    subscriptions: const [],
    entitlements: visibleEntitlements,
    acceptancePrice: 500,
    balance: 2500,
  );

  @override
  Future<SubscriptionsOverview> fetch() async => _overview;

  @override
  Future<SubscriptionsOverview> purchase(
    int offerId, {
    required bool autoRenew,
  }) async {
    if (purchaseError != null) throw purchaseError!;
    purchases.add('$offerId${autoRenew ? ' renouvelée' : ''}');
    return _overview;
  }

  @override
  Future<BusinessSubscription> setAutoRenew(
    int subscriptionId, {
    required bool autoRenew,
  }) async {
    autoRenewChanges.add('$subscriptionId $autoRenew');
    throw UnimplementedError();
  }
}

/// Texte d'aide de la barre de recherche de l'écran d'accueil.
const searchHint = 'Rechercher un quartier, un lieu…';

Future<void> pumpApp(
  WidgetTester tester, {
  SessionStorage? storage,
  AuthRepository? authRepository,
  GoogleAuthClient? googleAuthClient,
  MyBusinessRepository? businessRepository,
  LocationService? locationService,
  SearchRepository? searchRepository,
  GarageRepository? garageRepository,
  ContactLauncher? contactLauncher,
  ReferenceData reference = testReference,
  CommercialOfferCatalog commercialOffers = testCommercialOffers,
  WalletRepository? walletRepository,
  RequestsRepository? requestsRepository,
  SubscriptionRepository? subscriptionRepository,
  TeamRepository? teamRepository,
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
        googleAuthClientProvider.overrideWithValue(
          googleAuthClient ?? FakeGoogleAuthClient(),
        ),
        myBusinessRepositoryProvider.overrideWithValue(
          businessRepository ?? FakeMyBusinessRepository(),
        ),
        locationServiceProvider.overrideWithValue(
          locationService ?? FakeLocationService(),
        ),
        referenceDataProvider.overrideWith((ref) async => reference),
        commercialOffersProvider.overrideWith((ref) async => commercialOffers),
        walletRepositoryProvider.overrideWithValue(
          walletRepository ?? FakeWalletRepository(),
        ),
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
        requestsRepositoryProvider.overrideWithValue(
          requestsRepository ?? FakeRequestsRepository(),
        ),
        subscriptionRepositoryProvider.overrideWithValue(
          subscriptionRepository ?? FakeSubscriptionRepository(),
        ),
        teamRepositoryProvider.overrideWithValue(
          teamRepository ?? FakeTeamRepository(),
        ),
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
