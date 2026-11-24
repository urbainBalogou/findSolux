/// Demandes des clients (cahier des charges v3.0, §3.1 C4–C6, §3.2 P5–P6) :
/// signal de détresse, demande de contact et contacts échangés.
library;

DateTime _date(Object? value) => DateTime.parse(value! as String);

DateTime? _optionalDate(Object? value) =>
    value == null ? null : DateTime.parse(value as String);

/// Coordonnées d'un garage, visibles du client une fois le contact accepté.
class ContactGarage {
  const ContactGarage({
    required this.slug,
    required this.name,
    required this.districtId,
    this.phone,
    this.whatsappPhone,
    this.latitude,
    this.longitude,
    this.photoThumbnailUrl,
  });

  factory ContactGarage.fromJson(Map<String, dynamic> json) => ContactGarage(
    slug: json['slug'] as String,
    name: json['name'] as String,
    districtId: json['district_id'] as int,
    phone: json['phone'] as String?,
    whatsappPhone: json['whatsapp_phone'] as String?,
    latitude: (json['latitude'] as num?)?.toDouble(),
    longitude: (json['longitude'] as num?)?.toDouble(),
    photoThumbnailUrl: json['photo_thumbnail_url'] as String?,
  );

  final String slug;
  final String name;
  final int districtId;

  /// Null tant que le garage n'a pas accepté de partager son contact.
  final String? phone;
  final String? whatsappPhone;
  final double? latitude;
  final double? longitude;
  final String? photoThumbnailUrl;
}

enum DistressSignalStatus {
  open,
  accepted,
  expired,
  cancelled;

  static DistressSignalStatus fromJson(String value) => values.firstWhere(
    (status) => status.name == value,
    orElse: () => expired,
  );
}

/// Signal de détresse vu par le client qui l'a envoyé (C5).
class DistressSignal {
  const DistressSignal({
    required this.id,
    required this.status,
    required this.message,
    required this.latitude,
    required this.longitude,
    required this.recipientsCount,
    required this.expiresAt,
    required this.createdAt,
    this.serviceId,
    this.vehicleTypeId,
    this.garage,
  });

  factory DistressSignal.fromJson(Map<String, dynamic> json) => DistressSignal(
    id: json['id'] as String,
    status: DistressSignalStatus.fromJson(json['status'] as String),
    message: json['message'] as String,
    serviceId: json['service_id'] as int?,
    vehicleTypeId: json['vehicle_type_id'] as int?,
    latitude: (json['latitude'] as num).toDouble(),
    longitude: (json['longitude'] as num).toDouble(),
    recipientsCount: json['recipients_count'] as int? ?? 0,
    expiresAt: _date(json['expires_at']),
    createdAt: _date(json['created_at']),
    garage: json['garage'] == null
        ? null
        : ContactGarage.fromJson(json['garage'] as Map<String, dynamic>),
  );

  final String id;
  final DistressSignalStatus status;
  final String message;
  final int? serviceId;
  final int? vehicleTypeId;
  final double latitude;
  final double longitude;
  final int recipientsCount;
  final DateTime expiresAt;
  final DateTime createdAt;

  /// Garage qui a accepté, avec son numéro.
  final ContactGarage? garage;

  bool get isOpen =>
      status == DistressSignalStatus.open && expiresAt.isAfter(DateTime.now());
}

/// Signal à envoyer : la position est celle du téléphone.
class NewDistressSignal {
  const NewDistressSignal({
    required this.message,
    required this.latitude,
    required this.longitude,
    this.serviceId,
    this.vehicleTypeId,
  });

  final String message;
  final double latitude;
  final double longitude;
  final int? serviceId;
  final int? vehicleTypeId;

  Map<String, Object?> toJson() => {
    'message': message,
    'latitude': latitude,
    'longitude': longitude,
    'service_id': serviceId,
    'vehicle_type_id': vehicleTypeId,
  };
}

enum ContactRequestStatus {
  pending,
  accepted,
  declined,
  expired,
  cancelled;

  static ContactRequestStatus fromJson(String value) => values.firstWhere(
    (status) => status.name == value,
    orElse: () => expired,
  );
}

/// Demande de contact vue par le client (C4).
class ContactRequest {
  const ContactRequest({
    required this.id,
    required this.status,
    required this.garage,
    required this.expiresAt,
    required this.createdAt,
    this.message,
  });

  factory ContactRequest.fromJson(Map<String, dynamic> json) => ContactRequest(
    id: json['id'] as String,
    status: ContactRequestStatus.fromJson(json['status'] as String),
    message: json['message'] as String?,
    garage: ContactGarage.fromJson(json['garage'] as Map<String, dynamic>),
    expiresAt: _date(json['expires_at']),
    createdAt: _date(json['created_at']),
  );

  final String id;
  final ContactRequestStatus status;
  final String? message;
  final ContactGarage garage;
  final DateTime expiresAt;
  final DateTime createdAt;
}

/// Garage avec lequel le client a échangé ses coordonnées (« Mes garages »).
class SavedGarage {
  const SavedGarage({required this.garage, required this.createdAt});

  factory SavedGarage.fromJson(Map<String, dynamic> json) => SavedGarage(
    garage: ContactGarage.fromJson(json['garage'] as Map<String, dynamic>),
    createdAt: _date(json['created_at']),
  );

  final ContactGarage garage;
  final DateTime createdAt;
}

/// Client vu par le garage. Numéro et position donnés après acceptation.
class RequestClient {
  const RequestClient({this.name, this.phone, this.latitude, this.longitude});

  factory RequestClient.fromJson(Map<String, dynamic> json) => RequestClient(
    name: json['name'] as String?,
    phone: json['phone'] as String?,
    latitude: (json['latitude'] as num?)?.toDouble(),
    longitude: (json['longitude'] as num?)?.toDouble(),
  );

  final String? name;
  final String? phone;
  final double? latitude;
  final double? longitude;
}

/// Garagiste ou membre de son équipe qui a accepté une demande.
class RequestResponder {
  const RequestResponder({required this.id, this.name});

  factory RequestResponder.fromJson(Map<String, dynamic> json) =>
      RequestResponder(id: json['id'] as int, name: json['name'] as String?);

  final int id;
  final String? name;
}

RequestResponder? _responder(Object? json) => json == null
    ? null
    : RequestResponder.fromJson(json as Map<String, dynamic>);

/// État d'un signal de détresse pour le garage alerté.
enum ReceivedSignalStatus {
  open,
  declined,

  /// Accepté par ce garage.
  accepted,

  /// Pris en charge par un autre garage.
  taken,
  expired,
  cancelled;

  static ReceivedSignalStatus fromJson(String value) => values.firstWhere(
    (status) => status.name == value,
    orElse: () => expired,
  );
}

/// Signal de détresse reçu par un garage (P5).
class ReceivedDistressSignal {
  const ReceivedDistressSignal({
    required this.id,
    required this.status,
    required this.message,
    required this.distanceMeters,
    required this.expiresAt,
    required this.createdAt,
    this.serviceId,
    this.vehicleTypeId,
    this.client,
    this.acceptedBy,
  });

  factory ReceivedDistressSignal.fromJson(Map<String, dynamic> json) =>
      ReceivedDistressSignal(
        id: json['id'] as String,
        status: ReceivedSignalStatus.fromJson(json['status'] as String),
        message: json['message'] as String,
        serviceId: json['service_id'] as int?,
        vehicleTypeId: json['vehicle_type_id'] as int?,
        distanceMeters: json['distance_m'] as int? ?? 0,
        expiresAt: _date(json['expires_at']),
        createdAt: _date(json['created_at']),
        client: json['client'] == null
            ? null
            : RequestClient.fromJson(json['client'] as Map<String, dynamic>),
        acceptedBy: _responder(json['accepted_by']),
      );

  final String id;
  final ReceivedSignalStatus status;
  final String message;
  final int? serviceId;
  final int? vehicleTypeId;
  final int distanceMeters;
  final DateTime expiresAt;
  final DateTime createdAt;
  final RequestClient? client;

  /// Qui a accepté au nom du garage, quand il l'a accepté.
  final RequestResponder? acceptedBy;

  bool get canAnswer =>
      (status == ReceivedSignalStatus.open ||
          status == ReceivedSignalStatus.declined) &&
      expiresAt.isAfter(DateTime.now());
}

/// Demande de contact reçue par un garage (P5).
class ReceivedContactRequest {
  const ReceivedContactRequest({
    required this.id,
    required this.status,
    required this.client,
    required this.expiresAt,
    required this.createdAt,
    this.message,
    this.respondedAt,
    this.acceptedBy,
  });

  factory ReceivedContactRequest.fromJson(Map<String, dynamic> json) =>
      ReceivedContactRequest(
        id: json['id'] as String,
        status: ContactRequestStatus.fromJson(json['status'] as String),
        message: json['message'] as String?,
        client: RequestClient.fromJson(json['client'] as Map<String, dynamic>),
        expiresAt: _date(json['expires_at']),
        createdAt: _date(json['created_at']),
        respondedAt: _optionalDate(json['responded_at']),
        acceptedBy: _responder(json['accepted_by']),
      );

  final String id;
  final ContactRequestStatus status;
  final String? message;
  final RequestClient client;
  final DateTime expiresAt;
  final DateTime createdAt;
  final DateTime? respondedAt;

  /// Qui a accepté au nom du garage, quand elle est acceptée.
  final RequestResponder? acceptedBy;

  bool get canAnswer =>
      status == ContactRequestStatus.pending &&
      expiresAt.isAfter(DateTime.now());
}

/// Demandes reçues par le garage, avec le prix d'une acceptation et le solde.
class RequestInbox {
  const RequestInbox({
    required this.distressSignals,
    required this.contactRequests,
    required this.acceptancePrice,
    required this.balance,
  });

  final List<ReceivedDistressSignal> distressSignals;
  final List<ReceivedContactRequest> contactRequests;
  final int acceptancePrice;
  final int balance;

  int get pendingCount =>
      distressSignals.where((signal) => signal.canAnswer).length +
      contactRequests.where((request) => request.canAnswer).length;
}

/// Client avec lequel le garage a échangé ses coordonnées (« Mes clients »).
class BusinessClient {
  const BusinessClient({
    required this.phone,
    required this.fromDistressSignal,
    required this.createdAt,
    this.name,
  });

  factory BusinessClient.fromJson(Map<String, dynamic> json) {
    final client = json['client'] as Map<String, dynamic>;
    return BusinessClient(
      name: client['name'] as String?,
      phone: client['phone'] as String,
      fromDistressSignal: json['source'] == 'distress_signal',
      createdAt: _date(json['created_at']),
    );
  }

  final String? name;
  final String phone;
  final bool fromDistressSignal;
  final DateTime createdAt;
}
