import { createBottomTabNavigator } from "@react-navigation/bottom-tabs";

import FamilyMemberSwitcher from "../components/FamilyMemberSwitcher";
import HomeScreen from "../screens/HomeScreen";
import RecordsStack from "./RecordsStack";
import VitalsScreen from "../screens/VitalsScreen";
import FamilyScreen from "../screens/FamilyScreen";
import ProfileScreen from "../screens/ProfileScreen";

const Tab = createBottomTabNavigator();

export default function MainTabs() {
  return (
    <Tab.Navigator screenOptions={{ headerShown: true }}>
      <Tab.Screen name="Home" component={HomeScreen} />
      <Tab.Screen name="Records" component={RecordsStack} options={{ headerShown: false }} />
      <Tab.Screen
        name="Vitals"
        component={VitalsScreen}
        options={{ headerRight: () => <FamilyMemberSwitcher /> }}
      />
      <Tab.Screen name="Family" component={FamilyScreen} options={{ headerShown: false }} />
      <Tab.Screen name="Profile" component={ProfileScreen} />
    </Tab.Navigator>
  );
}
