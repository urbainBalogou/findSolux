/// Compte d'équipe (employé ou répondeur) créé par un garagiste : il ne sert
/// qu'à répondre aux demandes de ce garage.
class TeamMembership {
  const TeamMembership({
    required this.username,
    required this.businessId,
    required this.businessName,
  });

  factory TeamMembership.fromJson(Map<String, dynamic> json) {
    final business = json['business'] as Map<String, dynamic>;
    return TeamMembership(
      username: json['username'] as String,
      businessId: business['id'] as int,
      businessName: business['name'] as String,
    );
  }

  final String username;
  final int businessId;
  final String businessName;

  Map<String, dynamic> toJson() => {
    'username': username,
    'business': {'id': businessId, 'name': businessName},
  };
}

class AppUser {
  const AppUser({
    required this.id,
    this.name,
    this.email,
    this.phone,
    this.teamMembership,
  });

  factory AppUser.fromJson(Map<String, dynamic> json) {
    final teamMember = json['team_member'] as Map<String, dynamic>?;
    return AppUser(
      id: json['id'] as int,
      name: json['name'] as String?,
      email: json['email'] as String?,
      phone: json['phone'] as String?,
      teamMembership: teamMember == null
          ? null
          : TeamMembership.fromJson(teamMember),
    );
  }

  final int id;
  final String? name;

  /// Adresse du compte Google (ADR-017).
  final String? email;

  /// Numéro où le joindre, au format E.164 (+228XXXXXXXX). Saisi après la
  /// première connexion : `null` tant qu'il ne l'a pas donné.
  final String? phone;

  /// Présent pour un compte d'équipe.
  final TeamMembership? teamMembership;

  bool get hasPhone => phone != null;

  bool get isTeamMember => teamMembership != null;

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'email': email,
    'phone': phone,
    'team_member': teamMembership?.toJson(),
  };
}

/// Utilisateur connecté et jeton d'accès à l'API.
class AuthSession {
  const AuthSession({required this.token, required this.user});

  final String token;
  final AppUser user;
}
