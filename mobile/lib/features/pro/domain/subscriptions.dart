import '../../pricing/domain/commercial_offer.dart';

/// Droits actuels du garage (ADR-015) : visibilité et services publiés.
class Entitlements {
  const Entitlements({
    required this.hasMembership,
    required this.isUnlimitedMembership,
    required this.membershipEndsAt,
    required this.servicesQuota,
    required this.servicesCount,
    required this.publishedServicesCount,
    required this.isVisible,
    this.teamQuota = 0,
    this.teamCount = 0,
  });

  factory Entitlements.fromJson(Map<String, dynamic> json) {
    final membership = json['membership'] as Map<String, dynamic>;
    final services = json['services'] as Map<String, dynamic>;
    final team = json['team'] as Map<String, dynamic>?;
    final endsAt = membership['ends_at'] as String?;
    return Entitlements(
      hasMembership: membership['is_active'] as bool,
      isUnlimitedMembership: membership['is_unlimited'] as bool,
      membershipEndsAt: endsAt == null ? null : DateTime.parse(endsAt),
      servicesQuota: services['quota'] as int,
      servicesCount: services['count'] as int,
      publishedServicesCount: services['published_count'] as int,
      isVisible: json['is_visible'] as bool,
      teamQuota: team?['quota'] as int? ?? 0,
      teamCount: team?['count'] as int? ?? 0,
    );
  }

  /// Aucun droit : état d'une fiche qui vient d'être inscrite.
  static const none = Entitlements(
    hasMembership: false,
    isUnlimitedMembership: false,
    membershipEndsAt: null,
    servicesQuota: 0,
    servicesCount: 0,
    publishedServicesCount: 0,
    isVisible: false,
  );

  final bool hasMembership;
  final bool isUnlimitedMembership;
  final DateTime? membershipEndsAt;

  /// Nombre de services publiables (somme des packs actifs).
  final int servicesQuota;
  final int servicesCount;
  final int publishedServicesCount;

  /// Membres de l'équipe (employés ou répondeurs) permis par les packs actifs.
  final int teamQuota;

  /// Membres de l'équipe créés par le garagiste, actifs ou suspendus.
  final int teamCount;

  /// Visible des clients : fiche publiée et adhésion active.
  final bool isVisible;

  int get hiddenServicesCount => servicesCount - publishedServicesCount;
}

enum SubscriptionStatus {
  active,
  expired,
  renewed;

  static SubscriptionStatus fromJson(String value) => values.firstWhere(
    (status) => status.name == value,
    orElse: () => expired,
  );
}

/// Offre achetée ou accordée au garage.
class BusinessSubscription {
  const BusinessSubscription({
    required this.id,
    required this.type,
    required this.name,
    required this.quantity,
    required this.price,
    required this.startsAt,
    required this.endsAt,
    required this.status,
    required this.isCurrent,
    required this.isGranted,
    required this.autoRenew,
    required this.canAutoRenew,
  });

  factory BusinessSubscription.fromJson(Map<String, dynamic> json) {
    final endsAt = json['ends_at'] as String?;
    return BusinessSubscription(
      id: json['id'] as int,
      type: CommercialOfferType.fromJson(json['type'] as String),
      name: json['name'] as String,
      quantity: json['quantity'] as int?,
      price: (json['price'] as Map<String, dynamic>)['amount'] as int,
      startsAt: DateTime.parse(json['starts_at'] as String),
      endsAt: endsAt == null ? null : DateTime.parse(endsAt),
      status: SubscriptionStatus.fromJson(json['status'] as String),
      isCurrent: json['is_current'] as bool,
      isGranted: json['source'] == 'admin',
      autoRenew: json['auto_renew'] as bool,
      canAutoRenew: json['can_auto_renew'] as bool,
    );
  }

  final int id;
  final CommercialOfferType type;
  final String name;
  final int? quantity;
  final int price;
  final DateTime startsAt;

  /// Null : sans limite de durée.
  final DateTime? endsAt;
  final SubscriptionStatus status;

  /// En cours maintenant ; une prolongation programmée ne l'est pas encore.
  final bool isCurrent;

  /// Offerte depuis le back-office (offre de lancement…).
  final bool isGranted;
  final bool autoRenew;
  final bool canAutoRenew;

  bool get isActive => status == SubscriptionStatus.active;
}

/// Offres du garage, droits qui en découlent, solde et prix d'une acceptation.
class SubscriptionsOverview {
  const SubscriptionsOverview({
    required this.subscriptions,
    required this.entitlements,
    required this.acceptancePrice,
    required this.balance,
  });

  factory SubscriptionsOverview.fromJson(Map<String, dynamic> json) {
    final meta = json['meta'] as Map<String, dynamic>;
    final data = json['data'];
    return SubscriptionsOverview(
      subscriptions: [
        if (data is List)
          for (final item in data)
            BusinessSubscription.fromJson(item as Map<String, dynamic>)
        else
          BusinessSubscription.fromJson(data as Map<String, dynamic>),
      ],
      entitlements: Entitlements.fromJson(
        meta['entitlements'] as Map<String, dynamic>,
      ),
      acceptancePrice:
          (meta['acceptance_price'] as Map<String, dynamic>)['amount'] as int,
      balance: (meta['balance'] as Map<String, dynamic>)['amount'] as int,
    );
  }

  final List<BusinessSubscription> subscriptions;
  final Entitlements entitlements;
  final int acceptancePrice;
  final int balance;

  List<BusinessSubscription> get active =>
      subscriptions.where((subscription) => subscription.isActive).toList();

  List<BusinessSubscription> get ended =>
      subscriptions.where((subscription) => !subscription.isActive).toList();
}
