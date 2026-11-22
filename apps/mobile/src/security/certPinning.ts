/**
 * TLS certificate pinning against the backend's published pins (see
 * GET /.well-known/security-cert.json). Uses the device's native TLS stack
 * (OkHttp CertificatePinner on Android via TrustKit, NSURLSession on iOS),
 * so this only takes effect in a real dev/production build — Expo Go has no
 * native module access and `isSslPinningAvailable()` returns false there.
 *
 * With zero pins configured (CERT_PIN_SHA256 unset, the default until a
 * real production certificate exists), this safely no-ops end-to-end: no
 * domain config is applied, so the OS falls back to ordinary certificate
 * validation rather than pinning against nothing.
 */
export async function initCertPinning(): Promise<void> {
  const pinsEnv = process.env.EXPO_PUBLIC_CERT_PINS ?? "";
  const pins = pinsEnv
    .split(",")
    .map((pin: string) => pin.trim())
    .filter(Boolean);

  // iOS (TrustKit) requires at least two pins per domain — a backup pin in
  // case the primary cert is rotated — so one configured pin isn't enough
  // to safely enable pinning there either.
  if (pins.length < 2) {
    return;
  }

  try {
    const { initializeSslPinning, isSslPinningAvailable } = await import("react-native-ssl-public-key-pinning");

    if (!isSslPinningAvailable()) {
      return;
    }

    const apiUrl = process.env.EXPO_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";
    const host = new URL(apiUrl).hostname;

    await initializeSslPinning({
      [host]: {
        includeSubdomains: true,
        publicKeyHashes: pins,
      },
    });
  } catch {
    // Native module unavailable (Expo Go, or platform not yet supported) —
    // fail open to ordinary TLS validation rather than crash the app.
  }
}
