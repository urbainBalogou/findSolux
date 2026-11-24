import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/location/location_service.dart';
import '../../reference/domain/reference_data.dart';
import '../application/my_business_controller.dart';
import '../domain/my_business.dart';
import 'express_signup_form.dart';
import '../../../core/formatting/formatters.dart';
import 'widgets/business_editor.dart';
import 'widgets/form_section.dart';
import 'widgets/location_capture.dart';

/// Informations générales de la fiche : nom, numéros, description, point de
/// repère, position, véhicules, déplacement et dépannage 24 h/24.
class EditInfoScreen extends StatelessWidget {
  const EditInfoScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BusinessEditor(
      title: 'Informations',
      builder: (business, reference) =>
          _InfoForm(business: business, reference: reference),
    );
  }
}

class _InfoForm extends ConsumerStatefulWidget {
  const _InfoForm({required this.business, required this.reference});

  final MyBusiness business;
  final ReferenceData reference;

  @override
  ConsumerState<_InfoForm> createState() => _InfoFormState();
}

class _InfoFormState extends ConsumerState<_InfoForm>
    with BusinessFormSaving<_InfoForm> {
  late final _name = TextEditingController(text: widget.business.name);
  late final _whatsapp = TextEditingController(
    text: localDigits(widget.business.whatsappPhone),
  );
  late final _call = TextEditingController(
    text: localDigits(widget.business.phone),
  );
  late final _description = TextEditingController(
    text: widget.business.description,
  );
  late final _landmark = TextEditingController(text: widget.business.landmark);
  late final _districtController = TextEditingController(text: _district?.name);
  late District? _district = widget.reference.districtById(
    widget.business.districtId,
  );
  late var _location = LocationFix(
    latitude: widget.business.latitude,
    longitude: widget.business.longitude,
    accuracyMeters: 0,
  );
  late var _vehicleTypes = {
    for (final id in widget.business.vehicleTypeIds)
      ?widget.reference.vehicleTypeById(id),
  };
  late var _isMobile = widget.business.isMobile;
  late var _radiusKm = (widget.business.serviceRadiusKm ?? 5).toDouble();
  late var _is24h = widget.business.is24h;

  @override
  void dispose() {
    for (final controller in [
      _name,
      _whatsapp,
      _call,
      _description,
      _landmark,
      _districtController,
    ]) {
      controller.dispose();
    }
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

  String _digits(TextEditingController controller) =>
      controller.text.replaceAll(RegExp(r'\D'), '');

  String? _validate() {
    if (_name.text.trim().length < 2) return 'Indiquez le nom du garage.';
    if (_digits(_call).length != 8) {
      return 'Le numéro pour les appels doit contenir 8 chiffres.';
    }
    final whatsapp = _digits(_whatsapp);
    if (whatsapp.isNotEmpty && whatsapp.length != 8) {
      return 'Le numéro WhatsApp doit contenir 8 chiffres, ou rester vide.';
    }
    if (_district == null) return 'Choisissez le quartier.';
    if (_vehicleTypes.isEmpty) {
      return 'Choisissez au moins un type de véhicule.';
    }
    return null;
  }

  void _submit() {
    final error = _validate();
    if (error != null) {
      setState(() => saveError = error);
      return;
    }

    String? optional(TextEditingController controller) {
      final text = controller.text.trim();
      return text.isEmpty ? null : text;
    }

    final whatsapp = _digits(_whatsapp);
    save(
      () => ref.read(myBusinessProvider.notifier).updateProfile({
        'name': _name.text.trim(),
        'phone': '+228${_digits(_call)}',
        'whatsapp_phone': whatsapp.isEmpty ? null : '+228$whatsapp',
        'description': optional(_description),
        'landmark': optional(_landmark),
        'district_id': _district!.id,
        'latitude': _location.latitude,
        'longitude': _location.longitude,
        'vehicle_type_ids': [for (final type in _vehicleTypes) type.id],
        'is_mobile': _isMobile,
        'service_radius_km': _isMobile ? _radiusKm.round() : null,
        'is_24_7': _is24h,
      }),
    );
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        FormSection(
          title: 'Garage',
          child: Column(
            children: [
              TextField(
                controller: _name,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(
                  labelText: 'Nom du garage',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _description,
                maxLines: 4,
                maxLength: 1000,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(
                  labelText: 'Description',
                  hintText:
                      'Ex. : spécialiste moto depuis 15 ans, pièces '
                      'd’origine, travail garanti.',
                  border: OutlineInputBorder(),
                ),
              ),
            ],
          ),
        ),
        FormSection(
          title: 'Téléphone',
          child: Column(
            children: [
              _phoneField(_call, 'Numéro pour les appels'),
              const SizedBox(height: 12),
              _phoneField(
                _whatsapp,
                'Numéro WhatsApp',
                helper: 'Laissez vide si vous n’avez pas WhatsApp.',
              ),
            ],
          ),
        ),
        FormSection(
          title: 'Adresse',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              TextField(
                controller: _landmark,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(
                  labelText: 'Point de repère',
                  hintText: 'Ex. : en face de la pharmacie du carrefour',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 12),
              DistrictPicker(
                controller: _districtController,
                districts: widget.reference.districts,
                initialSelection: _district,
                onSelected: (district) => setState(() => _district = district),
              ),
              const SizedBox(height: 12),
              LocationCapture(value: _location, onChanged: _onLocation),
            ],
          ),
        ),
        FormSection(
          title: 'Véhicules réparés',
          child: ChoiceChips<VehicleType>(
            options: widget.reference.vehicleTypes,
            label: (type) => type.name,
            selected: _vehicleTypes,
            onChanged: (value) => setState(() => _vehicleTypes = value),
          ),
        ),
        FormSection(
          title: 'Intervention',
          child: Column(
            children: [
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Je me déplace chez le client'),
                value: _isMobile,
                onChanged: (value) => setState(() => _isMobile = value),
              ),
              if (_isMobile) ...[
                Text('Jusqu’à ${_radiusKm.round()} km'),
                Slider(
                  value: _radiusKm,
                  min: 1,
                  max: 30,
                  divisions: 29,
                  label: '${_radiusKm.round()} km',
                  onChanged: (value) => setState(() => _radiusKm = value),
                ),
              ],
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Dépannage 24 h/24'),
                value: _is24h,
                onChanged: (value) => setState(() => _is24h = value),
              ),
            ],
          ),
        ),
        saveButton(_submit),
      ],
    );
  }

  Widget _phoneField(
    TextEditingController controller,
    String label, {
    String? helper,
  }) {
    return TextField(
      controller: controller,
      keyboardType: TextInputType.phone,
      inputFormatters: [
        FilteringTextInputFormatter.allow(RegExp(r'[\d ]')),
        LengthLimitingTextInputFormatter(11),
      ],
      decoration: InputDecoration(
        labelText: label,
        helperText: helper,
        prefixText: '+228 ',
        border: const OutlineInputBorder(),
      ),
    );
  }
}
