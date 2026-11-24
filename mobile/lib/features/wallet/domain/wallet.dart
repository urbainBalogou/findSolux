class WalletSummary {
  const WalletSummary({
    required this.balance,
    required this.paymentMethods,
    required this.minimumTopUp,
    required this.maximumTopUp,
    required this.transactions,
    required this.topUps,
  });

  factory WalletSummary.fromJson(Map<String, dynamic> json) {
    final balance = json['balance'] as Map<String, dynamic>;
    final limits = json['top_up_limits'] as Map<String, dynamic>;

    return WalletSummary(
      balance: balance['amount'] as int,
      paymentMethods: [
        for (final item in json['payment_methods'] as List<dynamic>)
          WalletPaymentMethod.fromJson(item as Map<String, dynamic>),
      ],
      minimumTopUp: limits['minimum'] as int,
      maximumTopUp: limits['maximum'] as int,
      transactions: [
        for (final item in json['transactions'] as List<dynamic>)
          WalletEntry.fromJson(item as Map<String, dynamic>),
      ],
      topUps: [
        for (final item in json['top_ups'] as List<dynamic>)
          WalletTopUp.fromJson(item as Map<String, dynamic>),
      ],
    );
  }

  final int balance;
  final List<WalletPaymentMethod> paymentMethods;
  final int minimumTopUp;
  final int maximumTopUp;
  final List<WalletEntry> transactions;
  final List<WalletTopUp> topUps;
}

class WalletPaymentMethod {
  const WalletPaymentMethod({required this.code, required this.label});

  factory WalletPaymentMethod.fromJson(Map<String, dynamic> json) =>
      WalletPaymentMethod(
        code: json['code'] as String,
        label: json['label'] as String,
      );

  final String code;
  final String label;
}

enum WalletTopUpStatus {
  pending,
  succeeded,
  failed,
  cancelled,
  refunded;

  static WalletTopUpStatus parse(String value) => values.firstWhere(
    (status) => status.name == value,
    orElse: () => WalletTopUpStatus.pending,
  );

  bool get isTerminal => this != WalletTopUpStatus.pending;
}

class WalletTopUp {
  const WalletTopUp({
    required this.id,
    required this.amount,
    required this.status,
    required this.paymentMethod,
    required this.requiresAction,
    required this.message,
  });

  factory WalletTopUp.fromJson(Map<String, dynamic> json) {
    final amount = json['amount'] as Map<String, dynamic>;
    final paymentMethod = json['payment_method'] as Map<String, dynamic>;

    return WalletTopUp(
      id: json['id'] as String,
      amount: amount['value'] as int,
      status: WalletTopUpStatus.parse(json['status'] as String),
      paymentMethod: WalletPaymentMethod.fromJson(paymentMethod),
      requiresAction: json['requires_action'] as bool,
      message: json['message'] as String,
    );
  }

  final String id;
  final int amount;
  final WalletTopUpStatus status;
  final WalletPaymentMethod paymentMethod;
  final bool requiresAction;
  final String message;
}

class WalletEntry {
  const WalletEntry({
    required this.id,
    required this.direction,
    required this.amount,
    required this.description,
    required this.createdAt,
  });

  factory WalletEntry.fromJson(Map<String, dynamic> json) {
    final amount = json['amount'] as Map<String, dynamic>;

    return WalletEntry(
      id: json['id'] as int,
      direction: json['direction'] as String,
      amount: amount['value'] as int,
      description: json['description'] as String,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }

  final int id;
  final String direction;
  final int amount;
  final String description;
  final DateTime createdAt;
}
