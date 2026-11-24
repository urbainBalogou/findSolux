import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/formatting/formatters.dart';
import '../../../../core/network/api_exception.dart';
import '../../application/wallet_controller.dart';
import '../../domain/wallet.dart';

Future<void> showTopUpSheet(BuildContext context, WalletSummary wallet) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    builder: (context) => _TopUpSheet(wallet: wallet),
  );
}

class _TopUpSheet extends ConsumerStatefulWidget {
  const _TopUpSheet({required this.wallet});

  final WalletSummary wallet;

  @override
  ConsumerState<_TopUpSheet> createState() => _TopUpSheetState();
}

class _TopUpSheetState extends ConsumerState<_TopUpSheet> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  final _phoneController = TextEditingController();
  Timer? _pollTimer;
  WalletTopUp? _topUp;
  String? _paymentMethod;
  String? _error;
  var _isSubmitting = false;
  var _pollCount = 0;

  @override
  void initState() {
    super.initState();
    _paymentMethod = widget.wallet.paymentMethods.firstOrNull?.code;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          20,
          18,
          20,
          20 + MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Recharger le portefeuille',
                  style: theme.textTheme.titleLarge,
                ),
                const SizedBox(height: 6),
                const Text(
                  'Le numéro sert uniquement à cette recharge et ne sera pas enregistré.',
                ),
                const SizedBox(height: 18),
                TextFormField(
                  controller: _amountController,
                  enabled: !_isSubmitting && _topUp == null,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(
                    labelText: 'Montant',
                    suffixText: 'FCFA',
                    border: OutlineInputBorder(),
                  ),
                  validator: _validateAmount,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _phoneController,
                  enabled: !_isSubmitting && _topUp == null,
                  keyboardType: TextInputType.phone,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(
                    labelText: 'Numéro de recharge',
                    prefixText: '+228 ',
                    hintText: '90 12 34 56',
                    border: OutlineInputBorder(),
                  ),
                  validator: (value) => value?.length == 8
                      ? null
                      : 'Saisissez les 8 chiffres du numéro.',
                ),
                const SizedBox(height: 14),
                Text('Opérateur', style: theme.textTheme.labelLarge),
                RadioGroup<String>(
                  groupValue: _paymentMethod,
                  onChanged: (value) {
                    if (!_isSubmitting && _topUp == null) {
                      setState(() => _paymentMethod = value);
                    }
                  },
                  child: Column(
                    children: [
                      for (final method in widget.wallet.paymentMethods)
                        RadioListTile<String>(
                          contentPadding: EdgeInsets.zero,
                          value: method.code,
                          enabled: !_isSubmitting && _topUp == null,
                          title: Text(method.label),
                        ),
                    ],
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    _error!,
                    style: TextStyle(color: theme.colorScheme.error),
                  ),
                ],
                if (_topUp != null) ...[
                  const SizedBox(height: 8),
                  _TopUpStatusCard(topUp: _topUp!),
                ],
                const SizedBox(height: 18),
                if (_topUp?.status.isTerminal == true)
                  FilledButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('Terminer'),
                  )
                else
                  FilledButton.icon(
                    onPressed: _isSubmitting || _topUp != null ? null : _submit,
                    icon: _isSubmitting
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.phone_android),
                    label: Text(
                      _isSubmitting
                          ? 'Envoi en cours…'
                          : 'Demander la recharge',
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  String? _validateAmount(String? value) {
    final amount = int.tryParse(value ?? '');
    if (amount == null ||
        amount < widget.wallet.minimumTopUp ||
        amount > widget.wallet.maximumTopUp) {
      return 'Montant autorisé : ${formatPrice(widget.wallet.minimumTopUp, widget.wallet.maximumTopUp)}';
    }
    return null;
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _paymentMethod == null) {
      return;
    }

    setState(() {
      _isSubmitting = true;
      _error = null;
    });

    try {
      final topUp = await ref
          .read(walletProvider.notifier)
          .createTopUp(
            amount: int.parse(_amountController.text),
            paymentMethod: _paymentMethod!,
            phone: _phoneController.text,
          );
      if (!mounted) return;

      setState(() {
        _isSubmitting = false;
        _topUp = topUp;
      });
      _startPolling(topUp.id);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _isSubmitting = false;
        _error = error.message;
      });
    }
  }

  void _startPolling(String topUpId) {
    _pollTimer?.cancel();
    _pollCount = 0;
    _pollTimer = Timer.periodic(const Duration(seconds: 3), (timer) async {
      _pollCount++;
      if (_pollCount > 20) {
        timer.cancel();
        if (mounted) {
          setState(() {
            _error =
                'La confirmation prend du temps. Vous pouvez fermer et actualiser le portefeuille.';
          });
        }
        return;
      }

      try {
        final topUp = await ref
            .read(walletProvider.notifier)
            .checkTopUp(topUpId);
        if (!mounted) return;
        setState(() => _topUp = topUp);
        if (topUp.status.isTerminal) timer.cancel();
      } on ApiException {
        // Une coupure momentanée ne doit pas annuler le paiement en cours.
      }
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _amountController.dispose();
    _phoneController.dispose();
    super.dispose();
  }
}

class _TopUpStatusCard extends StatelessWidget {
  const _TopUpStatusCard({required this.topUp});

  final WalletTopUp topUp;

  @override
  Widget build(BuildContext context) {
    return Card(
      color: Theme.of(context).colorScheme.secondaryContainer,
      child: ListTile(
        leading: topUp.status == WalletTopUpStatus.pending
            ? const CircularProgressIndicator()
            : Icon(
                topUp.status == WalletTopUpStatus.succeeded
                    ? Icons.check_circle_outline
                    : Icons.error_outline,
              ),
        title: Text(topUp.message),
        subtitle: topUp.status == WalletTopUpStatus.pending
            ? const Text(
                'Gardez cette fenêtre ouverte pendant la confirmation.',
              )
            : null,
      ),
    );
  }
}
