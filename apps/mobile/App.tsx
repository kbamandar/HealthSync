import { useEffect } from "react";
import { StatusBar } from "expo-status-bar";
import * as Sentry from "@sentry/react-native";

import RootNavigator from "./src/navigation/RootNavigator";
import { initializeCertificatePinning } from "./src/security/certPinning";

// No live Sentry project exists in this sandbox — EXPO_PUBLIC_SENTRY_DSN is
// blank by default, which leaves crash reporting a real, wired-up no-op
// rather than something faked. Init must run at module scope (before the
// app renders), not inside a component.
const SENTRY_DSN = process.env.EXPO_PUBLIC_SENTRY_DSN;

if (SENTRY_DSN) {
  Sentry.init({ dsn: SENTRY_DSN, tracesSampleRate: 1.0 });
}

function App() {
  useEffect(() => {
    initializeCertificatePinning();
  }, []);

  return (
    <>
      <RootNavigator />
      <StatusBar style="auto" />
    </>
  );
}

export default Sentry.wrap(App);
