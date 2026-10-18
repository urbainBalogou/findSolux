import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/network/api_exception.dart';
import '../../data/search_repository.dart';
import '../../domain/search_models.dart';

/// Barre de recherche d'un lieu : la saisie est envoyée à l'API, qui répond
/// avec les quartiers connus puis les lieux Google.
class PlaceSearchBar extends ConsumerStatefulWidget {
  const PlaceSearchBar({super.key, required this.onSelected});

  final ValueChanged<PlaceResult> onSelected;

  @override
  ConsumerState<PlaceSearchBar> createState() => _PlaceSearchBarState();
}

class _PlaceSearchBarState extends ConsumerState<PlaceSearchBar> {
  final _controller = TextEditingController();
  final _focus = FocusNode();
  List<PlaceResult>? _results;
  String? _error;
  var _searching = false;

  @override
  void dispose() {
    _controller.dispose();
    _focus.dispose();
    super.dispose();
  }

  Future<void> _search(String text) async {
    final query = text.trim();
    if (query.length < 2) {
      setState(() => _error = 'Saisissez au moins 2 lettres.');
      return;
    }

    setState(() {
      _searching = true;
      _error = null;
    });
    try {
      final results = await ref
          .read(searchRepositoryProvider)
          .searchPlaces(query);
      if (mounted) setState(() => _results = results);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _searching = false);
    }
  }

  void _select(PlaceResult place) {
    _controller.text = place.name;
    _focus.unfocus();
    setState(() => _results = null);
    widget.onSelected(place);
  }

  void _clear() {
    _controller.clear();
    setState(() {
      _results = null;
      _error = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final results = _results;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Material(
          elevation: 3,
          borderRadius: BorderRadius.circular(28),
          color: theme.colorScheme.surface,
          child: TextField(
            controller: _controller,
            focusNode: _focus,
            textInputAction: TextInputAction.search,
            onSubmitted: _search,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(
              hintText: 'Rechercher un quartier, un lieu…',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: _searching
                  ? const Padding(
                      padding: EdgeInsets.all(14),
                      child: SizedBox.square(
                        dimension: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                    )
                  : _controller.text.isEmpty
                  ? null
                  : IconButton(
                      icon: const Icon(Icons.close),
                      tooltip: 'Effacer',
                      onPressed: _clear,
                    ),
              border: InputBorder.none,
              contentPadding: const EdgeInsets.symmetric(vertical: 14),
            ),
          ),
        ),
        if (_error != null || results != null)
          Card(
            margin: const EdgeInsets.only(top: 6),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 280),
              child: _error != null
                  ? Padding(
                      padding: const EdgeInsets.all(16),
                      child: Text(_error!),
                    )
                  : results!.isEmpty
                  ? const Padding(
                      padding: EdgeInsets.all(16),
                      child: Text('Aucun lieu trouvé. Essayez un autre nom.'),
                    )
                  : ListView(
                      shrinkWrap: true,
                      padding: EdgeInsets.zero,
                      children: [
                        for (final place in results)
                          ListTile(
                            leading: Icon(
                              place.isDistrict
                                  ? Icons.holiday_village_outlined
                                  : Icons.place_outlined,
                            ),
                            title: Text(place.name),
                            subtitle: place.address == null
                                ? null
                                : Text(
                                    place.address!,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                            onTap: () => _select(place),
                          ),
                      ],
                    ),
            ),
          ),
      ],
    );
  }
}
