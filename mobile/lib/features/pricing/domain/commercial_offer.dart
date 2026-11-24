/// Types d'offres du catalogue. Le prix d'acceptation d'une demande n'en fait
/// pas partie : c'est un tarif unique ([CommercialOfferCatalog.acceptancePrice]).
enum CommercialOfferType {
  membership('membership'),
  serviceBundle('service_bundle'),
  staffBundle('staff_bundle');

  const CommercialOfferType(this.apiValue);

  factory CommercialOfferType.fromJson(String value) =>
      tryParse(value) ??
      (throw FormatException('Type d\'offre inconnu : $value'));

  /// `null` pour un type inconnu de cette version de l'application.
  static CommercialOfferType? tryParse(String value) {
    for (final type in values) {
      if (type.apiValue == value) return type;
    }
    return null;
  }

  final String apiValue;
}

enum OfferDurationUnit {
  day('day'),
  month('month'),
  year('year');

  const OfferDurationUnit(this.apiValue);

  factory OfferDurationUnit.fromJson(String value) => values.firstWhere(
    (unit) => unit.apiValue == value,
    orElse: () => throw FormatException('Unité de durée inconnue : $value'),
  );

  final String apiValue;
}

class OfferPrice {
  const OfferPrice({required this.amount, required this.currency});

  factory OfferPrice.fromJson(Map<String, dynamic> json) => OfferPrice(
    amount: json['amount'] as int,
    currency: json['currency'] as String,
  );

  final int amount;
  final String currency;
}

class OfferDuration {
  const OfferDuration({
    required this.isUnlimited,
    required this.value,
    required this.unit,
  });

  factory OfferDuration.fromJson(Map<String, dynamic> json) {
    final isUnlimited = json['type'] == 'unlimited';
    return OfferDuration(
      isUnlimited: isUnlimited,
      value: isUnlimited ? null : json['value'] as int,
      unit: isUnlimited
          ? null
          : OfferDurationUnit.fromJson(json['unit'] as String),
    );
  }

  final bool isUnlimited;
  final int? value;
  final OfferDurationUnit? unit;
}

class CommercialOffer {
  const CommercialOffer({
    required this.id,
    required this.type,
    required this.name,
    required this.quantity,
    required this.price,
    required this.duration,
    required this.position,
    this.isPurchasable = true,
  });

  factory CommercialOffer.fromJson(Map<String, dynamic> json) =>
      CommercialOffer(
        id: json['id'] as int,
        type: CommercialOfferType.fromJson(json['type'] as String),
        name: json['name'] as String,
        quantity: json['quantity'] as int?,
        price: OfferPrice.fromJson(json['price'] as Map<String, dynamic>),
        duration: OfferDuration.fromJson(
          json['duration'] as Map<String, dynamic>,
        ),
        position: json['position'] as int,
        isPurchasable: json['is_purchasable'] as bool? ?? true,
      );

  final int id;
  final CommercialOfferType type;
  final String name;
  final int? quantity;
  final OfferPrice price;
  final OfferDuration duration;
  final int position;

  /// Achetable depuis le portefeuille, selon le serveur.
  final bool isPurchasable;
}

class CommercialOfferCatalog {
  const CommercialOfferCatalog(this.offers, {this.acceptancePrice});

  /// Les offres d'un type inconnu sont ignorées : un catalogue gardé en cache
  /// par une version précédente, ou un nouveau type ajouté côté serveur, ne
  /// doit pas empêcher d'afficher les autres.
  factory CommercialOfferCatalog.fromJson(Map<String, dynamic> json) {
    final meta = json['meta'] as Map<String, dynamic>?;
    final acceptancePrice = meta?['acceptance_price'] as Map<String, dynamic>?;

    return CommercialOfferCatalog([
      for (final offer in (json['data'] as List).cast<Map<String, dynamic>>())
        if (CommercialOfferType.tryParse(offer['type'] as String) != null)
          CommercialOffer.fromJson(offer),
    ], acceptancePrice: acceptancePrice?['amount'] as int?);
  }

  final List<CommercialOffer> offers;

  /// Prix unique, en FCFA, prélevé au garage pour chaque demande acceptée,
  /// quelle que soit son offre. `null` dans un ancien catalogue en cache.
  final int? acceptancePrice;

  List<CommercialOffer> ofType(CommercialOfferType type) => [
    for (final offer in offers)
      if (offer.type == type) offer,
  ];
}
