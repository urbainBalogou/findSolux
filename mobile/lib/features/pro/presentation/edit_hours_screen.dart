import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/my_business_controller.dart';
import '../domain/my_business.dart';
import '../../../core/formatting/formatters.dart';
import 'widgets/business_editor.dart';

/// Horaires d'ouverture, une plage par jour.
class EditHoursScreen extends StatelessWidget {
  const EditHoursScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BusinessEditor(
      title: 'Horaires',
      builder: (business, _) => _HoursForm(business: business),
    );
  }
}

class _DayDraft {
  _DayDraft({required this.open, required this.opens, required this.closes});

  bool open;
  TimeOfDay opens;
  TimeOfDay closes;
}

class _HoursForm extends ConsumerStatefulWidget {
  const _HoursForm({required this.business});

  final MyBusiness business;

  @override
  ConsumerState<_HoursForm> createState() => _HoursFormState();
}

class _HoursFormState extends ConsumerState<_HoursForm>
    with BusinessFormSaving<_HoursForm> {
  static const _defaultOpens = TimeOfDay(hour: 8, minute: 0);
  static const _defaultCloses = TimeOfDay(hour: 18, minute: 0);

  /// Sans horaires enregistrés, on propose lundi–samedi 8 h–18 h.
  late final bool _suggested = widget.business.openingHours.isEmpty;

  late final List<_DayDraft> _days = [
    for (var day = 1; day <= 7; day++) _draftFor(day),
  ];

  _DayDraft _draftFor(int day) {
    if (_suggested) {
      return _DayDraft(
        open: day <= 6,
        opens: _defaultOpens,
        closes: _defaultCloses,
      );
    }
    final range = widget.business.openingHours
        .where((r) => r.day == day)
        .firstOrNull;
    return _DayDraft(
      open: range != null,
      opens: range != null ? _parse(range.opensAt) : _defaultOpens,
      closes: range != null ? _parse(range.closesAt) : _defaultCloses,
    );
  }

  static TimeOfDay _parse(String hhmm) {
    final [hours, minutes] = hhmm.split(':');
    return TimeOfDay(hour: int.parse(hours), minute: int.parse(minutes));
  }

  static String _format(TimeOfDay time) =>
      '${time.hour.toString().padLeft(2, '0')}:'
      '${time.minute.toString().padLeft(2, '0')}';

  static int _minutes(TimeOfDay time) => time.hour * 60 + time.minute;

  Future<void> _pick(_DayDraft day, {required bool opening}) async {
    final picked = await showTimePicker(
      context: context,
      initialTime: opening ? day.opens : day.closes,
    );
    if (picked == null) return;
    setState(() {
      if (opening) {
        day.opens = picked;
      } else {
        day.closes = picked;
      }
    });
  }

  void _copyMonday() {
    final monday = _days.first;
    setState(() {
      for (final day in _days.skip(1).where((d) => d.open)) {
        day
          ..opens = monday.opens
          ..closes = monday.closes;
      }
    });
  }

  void _submit() {
    final ranges = <OpeningRange>[];
    for (var index = 0; index < 7; index++) {
      final day = _days[index];
      if (!day.open) continue;
      if (_minutes(day.closes) <= _minutes(day.opens)) {
        setState(
          () => saveError =
              '${dayNames[index]} : l’heure de fermeture doit être après '
              'l’heure d’ouverture.',
        );
        return;
      }
      ranges.add(
        OpeningRange(
          day: index + 1,
          opensAt: _format(day.opens),
          closesAt: _format(day.closes),
        ),
      );
    }

    save(
      () => ref.read(myBusinessProvider.notifier).replaceOpeningHours(ranges),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (_suggested)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Text(
              'Horaires proposés : vérifiez-les, puis enregistrez.',
              style: theme.textTheme.bodyLarge,
            ),
          ),
        for (var index = 0; index < 7; index++)
          _DayRow(
            name: dayNames[index],
            day: _days[index],
            format: _format,
            onToggle: (open) => setState(() => _days[index].open = open),
            onPickOpening: () => _pick(_days[index], opening: true),
            onPickClosing: () => _pick(_days[index], opening: false),
          ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: _copyMonday,
          icon: const Icon(Icons.copy_all),
          label: const Text('Mêmes horaires que le lundi pour tous les jours'),
        ),
        const SizedBox(height: 24),
        saveButton(_submit),
      ],
    );
  }
}

class _DayRow extends StatelessWidget {
  const _DayRow({
    required this.name,
    required this.day,
    required this.format,
    required this.onToggle,
    required this.onPickOpening,
    required this.onPickClosing,
  });

  final String name;
  final _DayDraft day;
  final String Function(TimeOfDay) format;
  final ValueChanged<bool> onToggle;
  final VoidCallback onPickOpening;
  final VoidCallback onPickClosing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          SizedBox(width: 92, child: Text(name)),
          Expanded(
            child: day.open
                ? Row(
                    children: [
                      TextButton(
                        onPressed: onPickOpening,
                        child: Text(format(day.opens)),
                      ),
                      const Text('–'),
                      TextButton(
                        onPressed: onPickClosing,
                        child: Text(format(day.closes)),
                      ),
                    ],
                  )
                : Text(
                    'Fermé',
                    style: TextStyle(color: Theme.of(context).disabledColor),
                  ),
          ),
          Switch(value: day.open, onChanged: onToggle),
        ],
      ),
    );
  }
}
