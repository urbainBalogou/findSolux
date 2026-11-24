import 'package:flutter/material.dart';

import '../../../core/formatting/formatters.dart';
import '../domain/commercial_offer.dart';

extension CommercialOfferTypeUi on CommercialOfferType {
  String get label => switch (this) {
    CommercialOfferType.membership => 'Adhésion',
    CommercialOfferType.serviceBundle => 'Services',
    CommercialOfferType.staffBundle => 'Équipe',
  };

  String get description => switch (this) {
    CommercialOfferType.membership =>
      'Rend votre garage visible et vous permet de recevoir les demandes des clients.',
    CommercialOfferType.serviceBundle =>
      'Nombre de services affichés sur votre fiche. Sans pack, aucun service n’est visible.',
    CommercialOfferType.staffBundle =>
      'Employés ou répondeurs qui reçoivent les demandes et les acceptent '
          'au nom de votre garage.',
  };

  IconData get icon => switch (this) {
    CommercialOfferType.membership => Icons.workspace_premium_outlined,
    CommercialOfferType.serviceBundle => Icons.build_outlined,
    CommercialOfferType.staffBundle => Icons.groups_outlined,
  };
}

/// Prix unique d'une demande acceptée (signal de détresse ou demande de
/// contact), indépendant des offres souscrites.
abstract final class AcceptancePriceUi {
  static const label = 'Demandes clients';
  static const icon = Icons.mark_chat_read_outlined;

  static String summary(int price) => price == 0
      ? 'Accepter une demande est gratuit'
      : '${formatPrice(price, price)} par demande acceptée';

  static const explanation =
      'Prélevé sur votre portefeuille à chaque demande de client que vous '
      'acceptez, quelle que soit votre offre. Rien à acheter à l’avance.';
}

extension OfferDurationUi on OfferDuration {
  String get label {
    if (isUnlimited) return 'Sans limite de durée';

    final count = value!;
    final unitLabel = switch (unit!) {
      OfferDurationUnit.day => count > 1 ? 'jours' : 'jour',
      OfferDurationUnit.month => 'mois',
      OfferDurationUnit.year => count > 1 ? 'ans' : 'an',
    };
    return '$count $unitLabel';
  }
}

extension CommercialOfferUi on CommercialOffer {
  String get quantityLabel {
    final count = quantity!;
    return switch (type) {
      CommercialOfferType.serviceBundle =>
        '$count service${count > 1 ? 's' : ''}',
      CommercialOfferType.staffBundle =>
        '$count collaborateur${count > 1 ? 's' : ''}',
      _ => '$count',
    };
  }
}
