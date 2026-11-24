/// Membre de l'équipe du garage (employé ou répondeur). Il reçoit les
/// demandes et les accepte au nom du garage.
class TeamMember {
  const TeamMember({
    required this.id,
    required this.name,
    required this.username,
    required this.isActive,
    this.lastSeenAt,
  });

  factory TeamMember.fromJson(Map<String, dynamic> json) {
    final lastSeenAt = json['last_seen_at'] as String?;
    return TeamMember(
      id: json['id'] as int,
      name: json['name'] as String,
      username: json['username'] as String,
      isActive: json['is_active'] as bool,
      lastSeenAt: lastSeenAt == null ? null : DateTime.parse(lastSeenAt),
    );
  }

  final int id;
  final String name;

  /// Identifiant de connexion, choisi par le garagiste.
  final String username;

  /// `false` au-delà du quota des packs actifs : il ne peut plus se connecter.
  final bool isActive;
  final DateTime? lastSeenAt;
}

/// Équipe du garage et nombre de membres permis par les packs actifs.
class TeamOverview {
  const TeamOverview({required this.members, required this.quota});

  factory TeamOverview.fromJson(Map<String, dynamic> json) {
    final meta = json['meta'] as Map<String, dynamic>;
    return TeamOverview(
      members: [
        for (final member in json['data'] as List)
          TeamMember.fromJson(member as Map<String, dynamic>),
      ],
      quota: meta['quota'] as int,
    );
  }

  final List<TeamMember> members;
  final int quota;

  bool get isFull => members.length >= quota;
}

/// Membre à créer : le garagiste lui transmet ensuite son identifiant et son
/// mot de passe.
class NewTeamMember {
  const NewTeamMember({
    required this.name,
    required this.username,
    required this.password,
  });

  final String name;
  final String username;
  final String password;

  Map<String, dynamic> toJson() => {
    'name': name,
    'username': username,
    'password': password,
  };
}
