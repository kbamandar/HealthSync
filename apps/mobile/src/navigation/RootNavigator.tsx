import { ActivityIndicator, View } from "react-native";
import { NavigationContainer } from "@react-navigation/native";

import { AuthProvider, useAuth } from "../auth/AuthContext";
import AuthFlow from "./AuthFlow";
import MainTabs from "./MainTabs";
import ProfileSetupScreen from "../screens/auth/ProfileSetupScreen";

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

  return <MainTabs />;
}

export default function RootNavigator() {
  return (
    <NavigationContainer>
      <AuthProvider>
        <Gate />
      </AuthProvider>
    </NavigationContainer>
  );
}
