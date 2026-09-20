import { ActivityIndicator, View } from "react-native";
import { NavigationContainer } from "@react-navigation/native";

import { AuthProvider, useAuth } from "../auth/AuthContext";
import { FamilyProvider } from "../family/FamilyContext";
import InviteLinkHandler from "../family/InviteLinkHandler";
import AppLockGate from "../screens/security/AppLockGate";
import { AppLockProvider, useAppLock } from "../security/AppLockContext";
import AuthFlow from "./AuthFlow";
import MainTabs from "./MainTabs";
import ProfileSetupScreen from "../screens/auth/ProfileSetupScreen";

function AuthenticatedApp() {
  const { isLockEnabled, isLocked } = useAppLock();

  if (isLockEnabled && isLocked) {
    return <AppLockGate />;
  }

  return <MainTabs />;
}

function Gate() {
  const { status } = useAuth();

  if (status === "loading") {
    return (
      <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  if (status === "unauthenticated") {
    return <AuthFlow />;
  }

  if (status === "needs-profile") {
    return <ProfileSetupScreen />;
  }

  return (
    <AppLockProvider>
      <AuthenticatedApp />
    </AppLockProvider>
  );
}

export default function RootNavigator() {
  return (
    <NavigationContainer>
      <AuthProvider>
        <FamilyProvider>
          <InviteLinkHandler />
          <Gate />
        </FamilyProvider>
      </AuthProvider>
    </NavigationContainer>
  );
}
