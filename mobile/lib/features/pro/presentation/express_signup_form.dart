import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/location/location_service.dart';
import '../../../core/network/api_exception.dart';
import '../../auth/application/auth_controller.dart';
import '../../reference/domain/reference_data.dart';
import '../application/my_business_controller.dart';
import '../domain/my_business.dart';
import '../../../core/formatting/formatters.dart';
import 'widgets/form_section.dart';
import 'widgets/location_capture.dart';

/// Inscription express : le garagiste, souvent accompagné par l'équipe
/// terrain, apparaît dans les recherches en moins de 5 minutes. Le reste de
/// la fiche se complète ensuite.
class ExpressSignupForm extends ConsumerStatefulWidget {
  const ExpressSignupForm({super.key, required this.reference});

  final ReferenceData reference;

  @override
  ConsumerState<ExpressSignupForm> createState() => _ExpressSignupFormState();
}

class _ExpressSignupFormState extends ConsumerState<ExpressSignupForm> {
  final _nameController = TextEditingController();
  final _whatsappController = TextEditingController();
  final _callController = TextEditingController();
  final _districtController = TextEditingController();
  var _sameNumber = true;
  LocationFix? _location;
  District? _district;
  var _vehicleTypes = <VehicleType>{};
  var _services = <Service>{};
  String? _error;
  var _saving = false;

  Trade get _trade => widget.reference.trades.first;

  @override
  void initState() {
    super.initState();
    final phone = ref.read(authControllerProvider).value?.user.phone;
    _whatsappController.text = localDigits(phone);
  }

  @override
  void dispose() {
    _nameController.dispose();
    _whatsappController.dispose();
    _callController.dispose();
    _districtController.dispose();
    super.dispose();
  }

  void _onLocation(LocationFix fix) {
    final nearest = widget.reference.nearestDistrict(
      fix.latitude,
      fix.longitude,
    );
    setState(() {
      _location = fix;
      if (nearest != null) {
        _district = nearest;
        _districtController.text = nearest.name;
      }
    });
  }

  String? _validate() {
    String digits(TextEditingController c) =>
        c.text.replaceAll(RegExp(r'\D'), '');

    if (_nameController.text.trim().length < 2) {
      return 'Indiquez le nom du garage.';
    }
    if (digits(_whatsappController).length != 8) {
      return 'Le numéro WhatsApp doit contenir 8 chiffres.';
    }
    if (!_sameNumber && digits(_callController).length != 8) {
      return 'Le numéro pour les appels doit contenir 8 chiffres.';
    }
    if (_location == null) return 'Prenez la position du garage.';
    if (_district == null) return 'Choisissez le quartier.';
    if (_vehicleTypes.isEmpty) {
      return 'Choisissez au moins un type de véhicule.';
    }
    if (_services.isEmpty) return 'Choisissez au moins un service.';
    return null;
  }

  Future<void> _submit() async {
    final error = _validate();
    if (error != null) {
      setState(() => _error = error);
      return;
    }

    String phone(TextEditingController c) =>
        '+228${c.text.replaceAll(RegExp(r'\D'), '')}';
    final whatsapp = phone(_whatsappController);

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ref
          .read(myBusinessProvider.notifier)
          .create(
            NewBusiness(
              tradeId: _trade.id,
              name: _nameController.text.trim(),
              phone: _sameNumber ? whatsapp : phone(_callController),
              whatsappPhone: whatsapp,
              districtId: _district!.id,
              latitude: _location!.latitude,
              longitude: _location!.longitude,
              vehicleTypeIds: [for (final type in _vehicleTypes) type.id],
              serviceIds: [for (final service in _services) service.id],
            ),
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Votre garage est inscrit !')),
      );
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          'Inscription gratuite en quelques minutes. '
          'Vous pourrez compléter votre fiche ensuite.',
          style: theme.textTheme.bodyLarge,
        ),
        const SizedBox(height: 24),
        FormSection(
          title: '1. Votre garage',
          child: TextField(
            controller: _nameController,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(
              labelText: 'Nom du garage',
              hintText: 'Ex. : Garage de la Paix',
              border: OutlineInputBorder(),
            ),
          ),
        ),
        FormSection(
          title: '2. Téléphone',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _PhoneField(
                controller: _whatsappController,
                label: 'Numéro WhatsApp',
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Les clients appellent aussi ce numéro'),
                value: _sameNumber,
                onChanged: (value) => setState(() => _sameNumber = value),
              ),
              if (!_sameNumber)
                _PhoneField(
                  controller: _callController,
                  label: 'Numéro pour les appels',
                ),
            ],
          ),
        ),
        FormSection(
          title: '3. Où se trouve le garage ?',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              LocationCapture(value: _location, onChanged: _onLocation),
              const SizedBox(height: 12),
              DistrictPicker(
                controller: _districtController,
                districts: widget.reference.districts,
                onSelected: (district) => setState(() => _district = district),
              ),
            ],
          ),
        ),
        FormSection(
          title: '4. Véhicules réparés',
          child: ChoiceChips<VehicleType>(
            options: widget.reference.vehicleTypes,
            label: (type) => type.name,
            selected: _vehicleTypes,
            onChanged: (value) => setState(() => _vehicleTypes = value),
          ),
        ),
        FormSection(
          title: '5. Services proposés',
          child: ChoiceChips<Service>(
            options: _trade.services,
            label: (service) => service.name,
            selected: _services,
            onChanged: (value) => setState(() => _services = value),
          ),
        ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Text(
              _error!,
              style: TextStyle(color: theme.colorScheme.error),
            ),
          ),
        FilledButton(
          onPressed: _saving ? null : _submit,
          style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52)),
          child: _saving
              ? const SizedBox.square(
                  dimension: 24,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Text('Inscrire mon garage'),
        ),
        const SizedBox(height: 24),
      ],
    );
  }
}

class _PhoneField extends StatelessWidget {
  const _PhoneField({required this.controller, required this.label});

  final TextEditingController controller;
  final String label;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      keyboardType: TextInputType.phone,
      inputFormatters: [
        FilteringTextInputFormatter.allow(RegExp(r'[\d ]')),
        LengthLimitingTextInputFormatter(11),
      ],
      decoration: InputDecoration(
        labelText: label,
        prefixText: '+228 ',
        border: const OutlineInputBorder(),
      ),
    );
  }
}

/// Choix du quartier avec recherche par les premières lettres.
class DistrictPicker extends StatelessWidget {
  const DistrictPicker({
    super.key,
    required this.controller,
    required this.districts,
    required this.onSelected,
    this.initialSelection,
  });

  final TextEditingController controller;
  final List<District> districts;
  final ValueChanged<District?> onSelected;
  final District? initialSelection;

  @override
  Widget build(BuildContext context) {
    return DropdownMenu<District>(
      controller: controller,
      initialSelection: initialSelection,
      expandedInsets: EdgeInsets.zero,
      enableFilter: true,
      requestFocusOnTap: true,
      menuHeight: 320,
      label: const Text('Quartier'),
      helperText: 'Proposé d’après la position, modifiable.',
      dropdownMenuEntries: [
        for (final district in districts)
          DropdownMenuEntry(value: district, label: district.name),
      ],
      onSelected: onSelected,
    );
  }
}
