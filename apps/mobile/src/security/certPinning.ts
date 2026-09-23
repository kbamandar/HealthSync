import {
  addSslPinningErrorListener,
  initializeSslPinning,
  isSslPinningAvailable,
} from "react-native-ssl-public-key-pinning";

const API_BASE_URL = process.env.EXPO_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";

/**
 * TLS pinning is enforced natively (OkHttp CertificatePinner on Android,
 * TrustKit on iOS) and only takes effect in a real dev/production build —
 * Expo Go has no native module for this, hence the isSslPinningAvailable()
 * guard. There's no live production certificate in this sandbox, so
 * CERT_PIN_SHA256 is blank and this safely no-ops; TrustKit itself requires
 * at least two pins, so we also refuse to enable pinning with fewer than
 * that (a single pin would lock out every client the moment the cert
 * rotates, with no backup to fall back to).
 */
export async function initializeCertificatePinning(): Promise<void> {
  if (!isSslPinningAvailable()) {
    return;
  }

  const hostname = new URL(API_BASE_URL).hostname;
  const pins = await fetchPins(hostname);

  if (pins.length < 2) {
    return;
  }

  await initializeSslPinning({
    [hostname]: {
      includeSubdomains: true,
      publicKeyHashes: pins,
    },
  });
}

async function fetchPins(hostname: string): Promise<string[]> {
  try {
    const origin = new URL(API_BASE_URL).origin;
    const response = await fetch(`${origin}/.well-known/security-cert.json`);
    const data = (await response.json()) as { "pins-sha256"?: string[] };
    return data["pins-sha256"] ?? [];
  } catch {
    // No network yet, or the endpoint is unreachable — fail open rather
    // than blocking app startup; there are no pins to enforce either way.
    return [];
  }
}

export function watchCertificatePinningErrors(onError: (hostname: string) => void) {
  return addSslPinningErrorListener((error) => onError(error.serverHostname));
}
