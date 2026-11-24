import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/team.dart';

/// Équipe du garage (employés et répondeurs), gérée par le garagiste dans la
/// limite de ses packs. Lève [ApiException] ; le code `team_quota_reached`
/// indique qu'il faut un pack de plus.
class TeamRepository {
  TeamRepository(this._dio);

  final Dio _dio;

  Future<TeamOverview> fetch() => guardApi(() async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/my-business/team-members',
    );
    return TeamOverview.fromJson(response.data!);
  });

  Future<TeamMember> create(NewTeamMember member) => guardApi(() async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/my-business/team-members',
      data: member.toJson(),
    );
    return TeamMember.fromJson(response.data!['data'] as Map<String, dynamic>);
  });

  /// Le membre est déconnecté de tous ses téléphones.
  Future<void> changePassword(int memberId, String password) => guardApi(
    () => _dio.patch<void>(
      '/my-business/team-members/$memberId',
      data: {'password': password},
    ),
  );

  Future<void> remove(int memberId) =>
      guardApi(() => _dio.delete<void>('/my-business/team-members/$memberId'));
}

final teamRepositoryProvider = Provider<TeamRepository>(
  (ref) => TeamRepository(ref.watch(dioProvider)),
);

final teamProvider = FutureProvider.autoDispose<TeamOverview>(
  (ref) => ref.watch(teamRepositoryProvider).fetch(),
);
