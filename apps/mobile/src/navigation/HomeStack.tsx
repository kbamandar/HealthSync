import { createNativeStackNavigator } from "@react-navigation/native-stack";

import HomeDashboardScreen from "../screens/HomeDashboardScreen";
import RemindersListScreen from "../screens/reminders/RemindersListScreen";
import CreateReminderScreen from "../screens/reminders/CreateReminderScreen";
import TimelineScreen from "../screens/TimelineScreen";
import SearchScreen from "../screens/SearchScreen";
import ManageSharedLinksScreen from "../screens/sharing/ManageSharedLinksScreen";

export type HomeStackParamList = {
  HomeDashboard: undefined;
  Reminders: undefined;
  CreateReminder: undefined;
  Timeline: undefined;
  Search: undefined;
  ManageSharedLinks: undefined;
};

const Stack = createNativeStackNavigator<HomeStackParamList>();

export default function HomeStack() {
  return (
    <Stack.Navigator>
      <Stack.Screen name="HomeDashboard" component={HomeDashboardScreen} options={{ title: "Home" }} />
      <Stack.Screen name="Reminders" component={RemindersListScreen} options={{ title: "Reminders" }} />
      <Stack.Screen name="CreateReminder" component={CreateReminderScreen} options={{ title: "New reminder" }} />
      <Stack.Screen name="Timeline" component={TimelineScreen} options={{ title: "Timeline" }} />
      <Stack.Screen name="Search" component={SearchScreen} options={{ title: "Search" }} />
      <Stack.Screen
        name="ManageSharedLinks"
        component={ManageSharedLinksScreen}
        options={{ title: "Shared links" }}
      />
    </Stack.Navigator>
  );
}
