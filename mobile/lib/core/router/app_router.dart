import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/account/presentation/account_screen.dart';
import '../../features/auth/application/auth_controller.dart';
import '../../features/auth/domain/otp_request_result.dart';
import '../../features/auth/presentation/login_code_screen.dart';
import '../../features/auth/presentation/login_phone_screen.dart';
import '../../features/favorites/presentation/favorites_screen.dart';
import '../../features/garage/presentation/garage_detail_screen.dart';
import '../../features/pro/presentation/edit_hours_screen.dart';
import '../../features/pro/presentation/edit_info_screen.dart';
import '../../features/pro/presentation/edit_services_screen.dart';
import '../../features/pro/presentation/photos_screen.dart';
import '../../features/pro/presentation/pro_business_screen.dart';
import '../../features/pro/presentation/pro_home_screen.dart';
import '../../features/pro/presentation/pro_reviews_screen.dart';
import '../../features/pro/presentation/pro_subscription_screen.dart';
import '../../features/search/presentation/search_screen.dart';
import '../widgets/navigation_shell_scaffold.dart';

abstract final class AppRoutes {
  static const search = '/';

  /// Fiche publique d'un garage, ouverte depuis les résultats.
  static String garage(String slug) => '/garages/$slug';
  static const favorites = '/favoris';
  static const account = '/compte';
  static const login = '/connexion';
  static const loginCode = '/connexion/code';
  static const proHome = '/pro';
  static const proBusiness = '/pro/fiche';
  static const proBusinessInfo = '/pro/fiche/informations';
  static const proBusinessServices = '/pro/fiche/services';
  static const proBusinessHours = '/pro/fiche/horaires';
  static const proBusinessPhotos = '/pro/fiche/photos';
  static const proReviews = '/pro/avis';
  static const proSubscription = '/pro/abonnement';

  static String loginLocation({String? from}) =>
      Uri(path: login, queryParameters: {'from': ?from}).toString();

  static String loginCodeLocation({required String phone, String? from}) => Uri(
    path: loginCode,
    queryParameters: {'phone': phone, 'from': ?from},
  ).toString();
}

/// Une seule application, deux espaces (ADR-010) : client par défaut,
/// garagiste sous `/pro`, réservé aux utilisateurs connectés.
final routerProvider = Provider<GoRouter>((ref) {
  final authChanges = ValueNotifier(0);
  ref.listen(authControllerProvider, (_, _) => authChanges.value++);
  ref.onDispose(authChanges.dispose);

  return GoRouter(
    refreshListenable: authChanges,
    redirect: (context, state) {
      final auth = ref.read(authControllerProvider);
      if (auth.isLoading) return null;

      final loggedIn = auth.value != null;
      final path = state.matchedLocation;

      if (!loggedIn && path.startsWith(AppRoutes.proHome)) {
        return AppRoutes.loginLocation(from: state.uri.toString());
      }
      if (loggedIn && path.startsWith(AppRoutes.login)) {
        return _safeReturnPath(state.uri.queryParameters['from']);
      }
      return null;
    },
    routes: [
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => NavigationShellScaffold(
          shell: shell,
          destinations: const [
            ShellDestination('Rechercher', Icons.search, Icons.search),
            ShellDestination('Favoris', Icons.favorite_border, Icons.favorite),
            ShellDestination('Compte', Icons.person_outline, Icons.person),
          ],
        ),
        branches: [
          _branch(
            AppRoutes.search,
            const SearchScreen(),
            routes: [
              GoRoute(
                path: 'garages/:slug',
                builder: (context, state) =>
                    GarageDetailScreen(slug: state.pathParameters['slug']!),
              ),
            ],
          ),
          _branch(AppRoutes.favorites, const FavoritesScreen()),
          _branch(AppRoutes.account, const AccountScreen()),
        ],
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => NavigationShellScaffold(
          shell: shell,
          destinations: const [
            ShellDestination(
              'Accueil',
              Icons.insights_outlined,
              Icons.insights,
            ),
            ShellDestination(
              'Ma fiche',
              Icons.storefront_outlined,
              Icons.storefront,
            ),
            ShellDestination('Avis', Icons.star_outline, Icons.star),
            ShellDestination(
              'Abonnement',
              Icons.workspace_premium_outlined,
              Icons.workspace_premium,
            ),
          ],
        ),
        branches: [
          _branch(AppRoutes.proHome, const ProHomeScreen()),
          _branch(
            AppRoutes.proBusiness,
            const ProBusinessScreen(),
            routes: [
              _page('informations', const EditInfoScreen()),
              _page('services', const EditServicesScreen()),
              _page('horaires', const EditHoursScreen()),
              _page('photos', const PhotosScreen()),
            ],
          ),
          _branch(AppRoutes.proReviews, const ProReviewsScreen()),
          _branch(AppRoutes.proSubscription, const ProSubscriptionScreen()),
        ],
      ),
      GoRoute(
        path: AppRoutes.login,
        builder: (context, state) =>
            LoginPhoneScreen(from: state.uri.queryParameters['from']),
      ),
      GoRoute(
        path: AppRoutes.loginCode,
        redirect: (context, state) =>
            state.uri.queryParameters['phone'] == null ? AppRoutes.login : null,
        builder: (context, state) {
          final result = state.extra is OtpRequestResult
              ? state.extra! as OtpRequestResult
              : null;
          return LoginCodeScreen(
            phone: state.uri.queryParameters['phone']!,
            resendIn: result?.resendIn ?? 60,
            otp: result?.otp,
          );
        },
      ),
    ],
  );
});

StatefulShellBranch _branch(
  String path,
  Widget screen, {
  List<RouteBase> routes = const [],
}) {
  return StatefulShellBranch(
    routes: [
      GoRoute(path: path, builder: (context, state) => screen, routes: routes),
    ],
  );
}

GoRoute _page(String path, Widget screen) {
  return GoRoute(path: path, builder: (context, state) => screen);
}

/// N'accepte qu'un chemin interne comme destination après connexion.
String _safeReturnPath(String? from) {
  if (from == null || !from.startsWith('/') || from.startsWith('//')) {
    return AppRoutes.account;
  }
  return from;
}
