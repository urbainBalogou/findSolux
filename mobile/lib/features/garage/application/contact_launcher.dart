import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

/// Ouvre le téléphone, WhatsApp, l'itinéraire ou le partage. Chaque méthode
/// renvoie false si l'application correspondante n'a pas pu s'ouvrir.
class ContactLauncher {
  Future<bool> call(String phone) => _open(Uri(scheme: 'tel', path: phone));

  /// Conversation WhatsApp avec un message pré-rempli (C4).
  Future<bool> whatsApp(String phone, String message) => _open(
    Uri.https('wa.me', '/${phone.replaceFirst('+', '')}', {'text': message}),
  );

  Future<bool> directions(double latitude, double longitude) => _open(
    Uri.https('www.google.com', '/maps/dir/', {
      'api': '1',
      'destination': '$latitude,$longitude',
    }),
  );

  Future<bool> share({required String text, String? subject}) async {
    try {
      await SharePlus.instance.share(ShareParams(text: text, subject: subject));
      return true;
    } on Object {
      return false;
    }
  }

  Future<bool> _open(Uri uri) async {
    try {
      return await launchUrl(uri, mode: LaunchMode.externalApplication);
    } on Object {
      return false;
    }
  }
}

final contactLauncherProvider = Provider<ContactLauncher>(
  (ref) => ContactLauncher(),
);
