import { createBottomTabNavigator } from "@react-navigation/bottom-tabs";

import HomeStack from "./HomeStack";
import ProfileStack from "./ProfileStack";
import RecordsStack from "./RecordsStack";
import VitalsStack from "./VitalsStack";
import FamilyScreen from "../screens/FamilyScreen";

const Tab = createBottomTabNavigator();

export default function MainTabs() {
  return (
    <Tab.Navigator screenOptions={{ headerShown: true }}>
      <Tab.Screen name="Home" component={HomeStack} options={{ headerShown: false }} />
      <Tab.Screen name="Records" component={RecordsStack} options={{ headerShown: false }} />
      <Tab.Screen name="Vitals" component={VitalsStack} options={{ headerShown: false }} />
      <Tab.Screen name="Family" component={FamilyScreen} options={{ headerShown: false }} />
      <Tab.Screen name="Profile" component={ProfileStack} options={{ headerShown: false }} />
    </Tab.Navigator>
  );
}
