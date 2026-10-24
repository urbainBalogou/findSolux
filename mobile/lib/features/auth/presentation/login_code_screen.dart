import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../application/auth_controller.dart';
import '../data/auth_repository.dart';
import 'phone_format.dart';

/// Connexion, étape 2 : saisie du code reçu par SMS. Une fois la session
/// ouverte, le routeur renvoie vers la page d'origine.
class LoginCodeScreen extends ConsumerStatefulWidget {
  const LoginCodeScreen({
    super.key,
    required this.phone,
    this.resendIn = 60,
    this.otp,
  });

  /// Numéro au format E.164.
  final String phone;

  /// Secondes avant de pouvoir demander un nouveau code.
  final int resendIn;

  /// Code de développement éventuellement transmis par le serveur.
  final String? otp;

  @override
  ConsumerState<LoginCodeScreen> createState() => _LoginCodeScreenState();
}

class _LoginCodeScreenState extends ConsumerState<LoginCodeScreen> {
  static const _codeLength = 6;

  final _codeController = TextEditingController();
  Timer? _timer;
  late int _secondsLeft;
  String? _visibleOtp;
  String? _error;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _visibleOtp = widget.otp;
    _startCountdown(widget.resendIn);
  }

  @override
  void dispose() {
    _timer?.cancel();
    _codeController.dispose();
    super.dispose();
  }

  void _startCountdown(int seconds) {
    _timer?.cancel();
    _secondsLeft = seconds;
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_secondsLeft <= 1) timer.cancel();
      setState(() => _secondsLeft--);
    });
  }

  Future<void> _verify() async {
    final code = _codeController.text;
    if (code.length != _codeLength || _busy) return;

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await ref
          .read(authControllerProvider.notifier)
          .verifyCode(phone: widget.phone, code: code);
    } on ApiException catch (error) {
      if (!mounted) return;
      _codeController.clear();
      setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _resend() async {
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final result = await ref
          .read(authRepositoryProvider)
          .requestCode(widget.phone);
      if (!mounted) return;
      _codeController.clear();
      _visibleOtp = result.otp;
      _startCountdown(result.resendIn);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Nouveau code envoyé.')));
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Code de connexion')),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Text(
            'Entrez le code à $_codeLength chiffres envoyé par SMS au '
            '${formatPhone(widget.phone)}.',
            style: theme.textTheme.bodyLarge,
          ),
          const SizedBox(height: 24),
          if (_visibleOtp != null) ...[
            Card(
              color: theme.colorScheme.secondaryContainer,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Text(
                  'Code de test : $_visibleOtp',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.bold,
                    letterSpacing: 4,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],
          TextField(
            controller: _codeController,
            autofocus: true,
            enabled: !_busy,
            keyboardType: TextInputType.number,
            autofillHints: const [AutofillHints.oneTimeCode],
            inputFormatters: [
              FilteringTextInputFormatter.digitsOnly,
              LengthLimitingTextInputFormatter(_codeLength),
            ],
            textAlign: TextAlign.center,
            style: theme.textTheme.headlineMedium?.copyWith(letterSpacing: 12),
            decoration: InputDecoration(
              hintText: '------',
              errorText: _error,
              errorMaxLines: 3,
              border: const OutlineInputBorder(),
            ),
            onChanged: (value) {
              if (value.length == _codeLength) _verify();
            },
          ),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: _busy ? null : _verify,
            style: FilledButton.styleFrom(
              minimumSize: const Size.fromHeight(52),
            ),
            child: _busy
                ? const SizedBox.square(
                    dimension: 24,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('Valider'),
          ),
          const SizedBox(height: 8),
          TextButton(
            onPressed: _busy || _secondsLeft > 0 ? null : _resend,
            child: Text(
              _secondsLeft > 0
                  ? 'Renvoyer le code dans $_secondsLeft s'
                  : 'Renvoyer le code',
            ),
          ),
        ],
      ),
    );
  }
}
