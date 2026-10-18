class AppUser {
  const AppUser({required this.id, required this.phone, this.name});

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: json['id'] as int,
      phone: json['phone'] as String,
      name: json['name'] as String?,
    );
  }

  final int id;

  /// Numéro au format E.164 (+228XXXXXXXX).
  final String phone;
  final String? name;

  Map<String, dynamic> toJson() => {'id': id, 'phone': phone, 'name': name};
}

/// Utilisateur connecté et jeton d'accès à l'API.
class AuthSession {
  const AuthSession({required this.token, required this.user});

  final String token;
  final AppUser user;
}
