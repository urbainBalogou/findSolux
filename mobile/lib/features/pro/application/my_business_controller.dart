import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../auth/application/auth_controller.dart';
import '../data/my_business_repository.dart';
import '../domain/my_business.dart';

final myBusinessProvider =
    AsyncNotifierProvider<MyBusinessController, MyBusiness?>(
      MyBusinessController.new,
    );

/// Fiche de l'utilisateur connecté : null s'il n'a pas encore inscrit de
/// garage. Rechargée à chaque changement d'utilisateur.
///
/// Les méthodes de modification lèvent [ApiException] en cas de refus : la
/// fiche affichée reste alors inchangée.
class MyBusinessController extends AsyncNotifier<MyBusiness?> {
  @override
  Future<MyBusiness?> build() async {
    final userId = ref.watch(
      authControllerProvider.select((auth) => auth.value?.user.id),
    );
    if (userId == null) return null;
    return ref.read(myBusinessRepositoryProvider).fetch();
  }

  Future<void> create(NewBusiness business) =>
      _apply((repository) => repository.create(business));

  Future<void> updateProfile(Map<String, Object?> changes) =>
      _apply((repository) => repository.update(changes));

  Future<void> replaceServices(List<BusinessServiceItem> services) =>
      _apply((repository) => repository.replaceServices(services));

  Future<void> replaceOpeningHours(List<OpeningRange> ranges) =>
      _apply((repository) => repository.replaceOpeningHours(ranges));

  Future<void> uploadPhoto(String filePath) =>
      _apply((repository) => repository.uploadPhoto(filePath));

  Future<void> deletePhoto(int photoId) =>
      _apply((repository) => repository.deletePhoto(photoId));

  Future<void> reorderPhotos(List<int> photoIds) =>
      _apply((repository) => repository.reorderPhotos(photoIds));

  Future<void> _apply(
    Future<MyBusiness> Function(MyBusinessRepository repository) action,
  ) async {
    final business = await action(ref.read(myBusinessRepositoryProvider));
    state = AsyncData(business);
  }
}
