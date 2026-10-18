import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../reference/domain/reference_data.dart';
import '../application/my_business_controller.dart';
import '../domain/my_business.dart';
import 'widgets/business_editor.dart';

/// Services proposés et prix indicatifs (un prix valable pour tous les
/// véhicules ; l'API accepte aussi des prix par véhicule).
class EditServicesScreen extends StatelessWidget {
  const EditServicesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BusinessEditor(
      title: 'Services et prix',
      builder: (business, reference) =>
          _ServicesForm(business: business, reference: reference),
    );
  }
}

class _ServiceDraft {
  _ServiceDraft({required this.selected, int? min, int? max})
    : min = TextEditingController(text: min?.toString()),
      max = TextEditingController(text: max?.toString());

  bool selected;
  final TextEditingController min;
  final TextEditingController max;
}

class _ServicesForm extends ConsumerStatefulWidget {
  const _ServicesForm({required this.business, required this.reference});

  final MyBusiness business;
  final ReferenceData reference;

  @override
  ConsumerState<_ServicesForm> createState() => _ServicesFormState();
}

class _ServicesFormState extends ConsumerState<_ServicesForm>
    with BusinessFormSaving<_ServicesForm> {
  late final List<Service> _services =
      widget.reference.tradeById(widget.business.tradeId)?.services ?? [];

  late final Map<int, _ServiceDraft> _drafts = {
    for (final service in _services) service.id: _draftFor(service.id),
  };

  _ServiceDraft _draftFor(int serviceId) {
    final rows = widget.business.services.where(
      (item) => item.serviceId == serviceId,
    );
    final generic = rows
        .where((item) => item.vehicleTypeId == null)
        .firstOrNull;
    return _ServiceDraft(
      selected: rows.isNotEmpty,
      min: generic?.priceMin,
      max: generic?.priceMax,
    );
  }

  @override
  void dispose() {
    for (final draft in _drafts.values) {
      draft.min.dispose();
      draft.max.dispose();
    }
    super.dispose();
  }

  void _submit() {
    final items = <BusinessServiceItem>[];

    for (final service in _services) {
      final draft = _drafts[service.id]!;
      if (!draft.selected) continue;

      final min = int.tryParse(draft.min.text);
      final max = int.tryParse(draft.max.text);
      if (min != null && max != null && max < min) {
        setState(
          () => saveError =
              'Pour « ${service.name} », le prix maximum doit être '
              'supérieur ou égal au prix minimum.',
        );
        return;
      }

      final existing = widget.business.services.where(
        (item) => item.serviceId == service.id,
      );
      final generic = existing
          .where((i) => i.vehicleTypeId == null)
          .firstOrNull;
      items
        ..add(
          BusinessServiceItem(
            serviceId: service.id,
            priceMin: min,
            priceMax: max,
            description: generic?.description,
          ),
        )
        // Les prix par véhicule, non modifiables ici, sont conservés.
        ..addAll(existing.where((item) => item.vehicleTypeId != null));
    }

    if (items.isEmpty) {
      setState(() => saveError = 'Choisissez au moins un service.');
      return;
    }

    save(() => ref.read(myBusinessProvider.notifier).replaceServices(items));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          'Cochez vos services. Un prix indicatif rassure les clients : '
          'indiquez une fourchette en FCFA.',
          style: theme.textTheme.bodyLarge,
        ),
        const SizedBox(height: 16),
        for (final service in _services) ...[
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(service.name),
            value: _drafts[service.id]!.selected,
            onChanged: (value) =>
                setState(() => _drafts[service.id]!.selected = value ?? false),
          ),
          if (_drafts[service.id]!.selected)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Row(
                children: [
                  Expanded(
                    child: _priceField(_drafts[service.id]!.min, 'Prix min'),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _priceField(_drafts[service.id]!.max, 'Prix max'),
                  ),
                ],
              ),
            ),
        ],
        const SizedBox(height: 16),
        saveButton(_submit),
      ],
    );
  }

  Widget _priceField(TextEditingController controller, String label) {
    return TextField(
      controller: controller,
      keyboardType: TextInputType.number,
      inputFormatters: [
        FilteringTextInputFormatter.digitsOnly,
        LengthLimitingTextInputFormatter(8),
      ],
      decoration: InputDecoration(
        labelText: label,
        suffixText: 'FCFA',
        isDense: true,
        border: const OutlineInputBorder(),
      ),
    );
  }
}
