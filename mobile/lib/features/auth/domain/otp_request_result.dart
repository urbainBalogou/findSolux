class OtpRequestResult {
  const OtpRequestResult({required this.resendIn, this.otp});

  final int resendIn;

  /// Présent uniquement lorsque le serveur autorise l'affichage du code.
  final String? otp;
}
