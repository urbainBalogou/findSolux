import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../auth/application/auth_controller.dart';
import '../data/wallet_repository.dart';
import '../domain/wallet.dart';

final walletProvider = AsyncNotifierProvider<WalletController, WalletSummary>(
  WalletController.new,
);

class WalletController extends AsyncNotifier<WalletSummary> {
  @override
  Future<WalletSummary> build() async {
    final userId = ref.watch(
      authControllerProvider.select((auth) => auth.value?.user.id),
    );
    if (userId == null) {
      throw StateError('Une connexion est requise.');
    }
    return ref.read(walletRepositoryProvider).fetch();
  }

  Future<void> reload() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(ref.read(walletRepositoryProvider).fetch);
  }

  Future<WalletTopUp> createTopUp({
    required int amount,
    required String paymentMethod,
    required String phone,
  }) {
    return ref
        .read(walletRepositoryProvider)
        .createTopUp(
          amount: amount,
          paymentMethod: paymentMethod,
          phone: phone,
        );
  }

  Future<WalletTopUp> checkTopUp(String id) async {
    final topUp = await ref.read(walletRepositoryProvider).fetchTopUp(id);
    if (topUp.status == WalletTopUpStatus.succeeded) {
      state = AsyncData(await ref.read(walletRepositoryProvider).fetch());
    }
    return topUp;
  }
}
