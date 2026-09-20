import { createNativeStackNavigator } from "@react-navigation/native-stack";

import HomeDashboardScreen from "../screens/HomeDashboardScreen";
import RemindersListScreen from "../screens/reminders/RemindersListScreen";
import CreateReminderScreen from "../screens/reminders/CreateReminderScreen";

export type HomeStackParamList = {
  HomeDashboard: undefined;
  Reminders: undefined;
  CreateReminder: undefined;
};

const Stack = createNativeStackNavigator<HomeStackParamList>();

export default function HomeStack() {
  return (
    <Stack.Navigator>
      <Stack.Screen name="HomeDashboard" component={HomeDashboardScreen} options={{ title: "Home" }} />
      <Stack.Screen name="Reminders" component={RemindersListScreen} options={{ title: "Reminders" }} />
      <Stack.Screen name="CreateReminder" component={CreateReminderScreen} options={{ title: "New reminder" }} />
    </Stack.Navigator>
  );
}
