import { createNativeStackNavigator } from "@react-navigation/native-stack";

import AddFamilyMemberScreen from "../screens/family/AddFamilyMemberScreen";
import FamilyListScreen from "../screens/family/FamilyListScreen";
import MemberDetailScreen from "../screens/family/MemberDetailScreen";

export type FamilyStackParamList = {
  FamilyList: undefined;
  AddMember: undefined;
  MemberDetail: { id: string };
};

const Stack = createNativeStackNavigator<FamilyStackParamList>();

export default function FamilyStack() {
  return (
    <Stack.Navigator>
      <Stack.Screen name="FamilyList" component={FamilyListScreen} options={{ title: "Family" }} />
      <Stack.Screen name="AddMember" component={AddFamilyMemberScreen} options={{ title: "Add family member" }} />
      <Stack.Screen name="MemberDetail" component={MemberDetailScreen} options={{ title: "Family member" }} />
    </Stack.Navigator>
  );
}
