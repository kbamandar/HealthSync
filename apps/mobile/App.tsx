import { useEffect } from "react";
import { StatusBar } from "expo-status-bar";
import * as Sentry from "@sentry/react-native";

import RootNavigator from "./src/navigation/RootNavigator";
import { initCertPinning } from "./src/security/certPinning";

Sentry.init({
  dsn: process.env.EXPO_PUBLIC_SENTRY_DSN || undefined,
  enabled: Boolean(process.env.EXPO_PUBLIC_SENTRY_DSN),
});

function App() {
  useEffect(() => {
    initCertPinning();
  }, []);

  return (
    <>
      <RootNavigator />
      <StatusBar style="auto" />
    </>
  );
}

export default Sentry.wrap(App);
