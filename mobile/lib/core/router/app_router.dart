import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/account/presentation/account_screen.dart';
import '../../features/auth/application/auth_controller.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/phone_number_screen.dart';
import '../../features/auth/presentation/team_member_login_screen.dart';
import '../../features/garage/presentation/garage_detail_screen.dart';
import '../../features/pro/presentation/edit_hours_screen.dart';
import '../../features/pro/presentation/edit_info_screen.dart';
import '../../features/pro/presentation/edit_services_screen.dart';
import '../../features/pro/presentation/photos_screen.dart';
import '../../features/pro/presentation/pro_business_screen.dart';
import '../../features/pro/presentation/pro_home_screen.dart';
import '../../features/pro/presentation/pro_reviews_screen.dart';
import '../../features/pro/presentation/pro_subscription_screen.dart';
import '../../features/pro/presentation/team_screen.dart';
import '../../features/requests/presentation/distress_signal_screen.dart';
import '../../features/requests/presentation/my_garages_screen.dart';
import '../../features/requests/presentation/pro_requests_screen.dart';
import '../../features/search/presentation/search_screen.dart';
import '../../features/wallet/presentation/wallet_screen.dart';
import '../widgets/navigation_shell_scaffold.dart';

abstract final class AppRoutes {
  static const search = '/';

  /// Fiche publique d'un garage, ouverte depuis les résultats.
  static String garage(String slug) => '/garages/$slug';

  /// Garages en contact avec le client (C6).
  static const myGarages = '/mes-garages';

  /// Signal de détresse (C5) ; [signalId] : signal ouvert depuis une notification.
  static const distressSignal = '/sos';
  static String distressSignalLocation({String? signalId}) =>
      Uri(path: distressSignal, queryParameters: {'id': ?signalId}).toString();
  static const account = '/compte';

  /// Modification du numéro où joindre l'utilisateur.
  static const accountPhone = '/compte/telephone';
  static const login = '/connexion';

  /// Numéro demandé après la première connexion Google (ADR-017).
  static const loginPhone = '/connexion/telephone';

  /// Connexion d'un employé ou répondeur, avec l'identifiant créé par le garagiste.
  static const teamMemberLogin = '/connexion/equipe';

  /// Seul écran d'un compte d'équipe : les demandes reçues par son garage.
  static const teamRequests = '/equipe';
  static const proHome = '/pro';

  /// Équipe du garage (employés et répondeurs), gérée par le garagiste.
  static const proTeam = '/pro/equipe';
  static const proRequests = '/pro/demandes';
  static const proBusiness = '/pro/fiche';
  static const proBusinessInfo = '/pro/fiche/informations';
  static const proBusinessServices = '/pro/fiche/services';
  static const proBusinessHours = '/pro/fiche/horaires';
  static const proBusinessPhotos = '/pro/fiche/photos';
  static const proReviews = '/pro/avis';
  static const proSubscription = '/pro/abonnement';
  static const wallet = '/portefeuille';

  static String loginLocation({String? from}) =>
      Uri(path: login, queryParameters: {'from': ?from}).toString();

  static String loginPhoneLocation({String? from}) =>
      Uri(path: loginPhone, queryParameters: {'from': ?from}).toString();
}

/// Une seule application, deux espaces (ADR-010) : client par défaut,
/// garagiste sous `/pro`, réservé aux utilisateurs connectés.
final routerProvider = Provider<GoRouter>((ref) {
  // Seuls ces états changent les redirections. Rafraîchir le routeur à chaque
  // modification du profil ramènerait l'écran que l'on vient de fermer.
  final authChanges = ValueNotifier(0);
  ref.listen(
    authControllerProvider.select(
      (auth) => (
        auth.isLoading,
        auth.value != null,
        auth.value?.user.hasPhone,
        auth.value?.user.isTeamMember,
      ),
    ),
    (_, _) => authChanges.value++,
  );
  ref.onDispose(authChanges.dispose);

  return GoRouter(
    refreshListenable: authChanges,
    redirect: (context, state) {
      final auth = ref.read(authControllerProvider);
      if (auth.isLoading) return null;

      final user = auth.value?.user;
      final path = state.matchedLocation;
      final isLoginPath = path.startsWith(AppRoutes.login);
      // Page à rouvrir une fois connecté, transmise d'un écran de connexion à l'autre.
      final from = isLoginPath
          ? state.uri.queryParameters['from']
          : state.uri.toString();

      if (user == null) {
        final needsAccount =
            path.startsWith(AppRoutes.proHome) ||
            path == AppRoutes.wallet ||
            path == AppRoutes.distressSignal ||
            path == AppRoutes.accountPhone ||
            path == AppRoutes.loginPhone ||
            path == AppRoutes.teamRequests;
        return needsAccount ? AppRoutes.loginLocation(from: from) : null;
      }
      // Un compte d'équipe ne sert qu'à répondre aux demandes de son garage.
      if (user.isTeamMember) {
        return path == AppRoutes.teamRequests ? null : AppRoutes.teamRequests;
      }
      if (path == AppRoutes.teamRequests) return AppRoutes.proRequests;
      // Le garage qui accepte une demande paie pour joindre le client : le
      // compte ne sert qu'une fois le numéro saisi.
      if (!user.hasPhone) {
        return path == AppRoutes.loginPhone
            ? null
            : AppRoutes.loginPhoneLocation(from: from);
      }
      if (isLoginPath) {
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
            ShellDestination(
              'Mes garages',
              Icons.handshake_outlined,
              Icons.handshake,
            ),
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
          _branch(AppRoutes.myGarages, const MyGaragesScreen()),
          _branch(
            AppRoutes.account,
            const AccountScreen(),
            routes: [
              _page('telephone', const PhoneNumberScreen(editing: true)),
            ],
          ),
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
              'Demandes',
              Icons.notifications_active_outlined,
              Icons.notifications_active,
            ),
            ShellDestination(
              'Ma fiche',
              Icons.storefront_outlined,
              Icons.storefront,
            ),
            ShellDestination('Avis', Icons.star_outline, Icons.star),
            ShellDestination(
              'Offres',
              Icons.workspace_premium_outlined,
              Icons.workspace_premium,
            ),
          ],
        ),
        branches: [
          _branch(
            AppRoutes.proHome,
            const ProHomeScreen(),
            routes: [_page('equipe', const TeamScreen())],
          ),
          _branch(AppRoutes.proRequests, const ProRequestsScreen()),
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
        path: AppRoutes.wallet,
        builder: (context, state) => const WalletScreen(),
      ),
      GoRoute(
        path: AppRoutes.distressSignal,
        builder: (context, state) =>
            DistressSignalScreen(signalId: state.uri.queryParameters['id']),
      ),
      GoRoute(
        path: AppRoutes.login,
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: AppRoutes.loginPhone,
        builder: (context, state) => const PhoneNumberScreen(),
      ),
      GoRoute(
        path: AppRoutes.teamMemberLogin,
        builder: (context, state) => const TeamMemberLoginScreen(),
      ),
      GoRoute(
        path: AppRoutes.teamRequests,
        builder: (context, state) => const ProRequestsScreen(),
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
