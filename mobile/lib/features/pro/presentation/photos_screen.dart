import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/network/api_exception.dart';
import '../application/my_business_controller.dart';
import '../domain/my_business.dart';
import 'widgets/business_editor.dart';

final imagePickerProvider = Provider<ImagePicker>((ref) => ImagePicker());

/// Photos de l'atelier : ajout (appareil photo ou galerie), photo
/// principale, suppression.
class PhotosScreen extends StatelessWidget {
  const PhotosScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BusinessEditor(
      title: 'Photos',
      builder: (business, _) => _PhotosView(business: business),
    );
  }
}

class _PhotosView extends ConsumerStatefulWidget {
  const _PhotosView({required this.business});

  final MyBusiness business;

  @override
  ConsumerState<_PhotosView> createState() => _PhotosViewState();
}

class _PhotosViewState extends ConsumerState<_PhotosView> {
  var _busy = false;

  List<BusinessPhoto> get _photos => widget.business.photos;

  Future<void> _run(Future<void> Function() action, String done) async {
    setState(() => _busy = true);
    try {
      await action();
      _snack(done);
    } on ApiException catch (error) {
      _snack(error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _snack(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _add() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera),
              title: const Text('Prendre une photo'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('Choisir dans la galerie'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;

    // Réduite sur le téléphone avant l'envoi : moins de data consommée.
    final file = await ref
        .read(imagePickerProvider)
        .pickImage(
          source: source,
          maxWidth: 1600,
          maxHeight: 1600,
          imageQuality: 80,
        );
    if (file == null) return;

    await _run(
      () => ref.read(myBusinessProvider.notifier).uploadPhoto(file.path),
      'Photo ajoutée.',
    );
  }

  Future<void> _showActions(BusinessPhoto photo, int index) async {
    final action = await showModalBottomSheet<String>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (index > 0)
              ListTile(
                leading: const Icon(Icons.star_outline),
                title: const Text('Mettre en photo principale'),
                onTap: () => Navigator.pop(context, 'main'),
              ),
            ListTile(
              leading: const Icon(Icons.delete_outline),
              title: const Text('Supprimer'),
              onTap: () => Navigator.pop(context, 'delete'),
            ),
          ],
        ),
      ),
    );
    if (!mounted || action == null) return;

    final controller = ref.read(myBusinessProvider.notifier);
    if (action == 'main') {
      await _run(
        () => controller.reorderPhotos([
          photo.id,
          for (final other in _photos)
            if (other.id != photo.id) other.id,
        ]),
        'Photo principale modifiée.',
      );
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer cette photo ?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );
    if (confirmed == true) {
      await _run(() => controller.deletePhoto(photo.id), 'Photo supprimée.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final full = _photos.length >= MyBusiness.maxPhotos;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (_busy) const LinearProgressIndicator(),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Text(
            'Montrez votre atelier, vos outils, un travail terminé. Les photos '
            'apparaissent sur votre fiche publique avec les formules Standard '
            'et Premium.',
            style: theme.textTheme.bodyMedium,
          ),
        ),
        Expanded(
          child: _photos.isEmpty
              ? const Center(child: Text('Aucune photo pour le moment.'))
              : GridView.count(
                  crossAxisCount: 3,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  mainAxisSpacing: 8,
                  crossAxisSpacing: 8,
                  children: [
                    for (final (index, photo) in _photos.indexed)
                      _PhotoTile(
                        photo: photo,
                        isMain: index == 0,
                        onTap: _busy ? null : () => _showActions(photo, index),
                      ),
                  ],
                ),
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: FilledButton.icon(
            onPressed: _busy || full ? null : _add,
            icon: const Icon(Icons.add_a_photo),
            label: Text(
              full
                  ? 'Maximum atteint (${MyBusiness.maxPhotos} photos)'
                  : 'Ajouter une photo '
                        '(${_photos.length}/${MyBusiness.maxPhotos})',
            ),
            style: FilledButton.styleFrom(
              minimumSize: const Size.fromHeight(52),
            ),
          ),
        ),
      ],
    );
  }
}

class _PhotoTile extends StatelessWidget {
  const _PhotoTile({required this.photo, required this.isMain, this.onTap});

  final BusinessPhoto photo;
  final bool isMain;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return InkWell(
      onTap: onTap,
      child: Stack(
        fit: StackFit.expand,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: CachedNetworkImage(
              imageUrl: photo.thumbnailUrl,
              fit: BoxFit.cover,
              placeholder: (_, _) =>
                  ColoredBox(color: theme.colorScheme.surfaceContainerHighest),
              errorWidget: (_, _, _) => const Icon(Icons.broken_image),
            ),
          ),
          if (isMain)
            Positioned(
              left: 4,
              bottom: 4,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: theme.colorScheme.primary,
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  'Principale',
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: theme.colorScheme.onPrimary,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
