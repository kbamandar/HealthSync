import { createNativeStackNavigator } from "@react-navigation/native-stack";

import FamilyMemberSwitcher from "../components/FamilyMemberSwitcher";
import VitalsHomeScreen from "../screens/vitals/VitalsHomeScreen";
import LogVitalScreen from "../screens/vitals/LogVitalScreen";

export type VitalsStackParamList = {
  VitalsHome: undefined;
  LogVital: { tabKey: string };
};

const Stack = createNativeStackNavigator<VitalsStackParamList>();

export default function VitalsStack() {
  return (
    <Stack.Navigator>
      <Stack.Screen
        name="VitalsHome"
        component={VitalsHomeScreen}
        options={{ title: "Vitals", headerRight: () => <FamilyMemberSwitcher /> }}
      />
      <Stack.Screen name="LogVital" component={LogVitalScreen} options={{ title: "Log a reading" }} />
    </Stack.Navigator>
  );
}
