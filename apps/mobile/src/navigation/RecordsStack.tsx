import { Text, TouchableOpacity, View } from "react-native";
import { createNativeStackNavigator } from "@react-navigation/native-stack";
import type { NativeStackNavigationProp } from "@react-navigation/native-stack";
import { useNavigation } from "@react-navigation/native";

import FamilyMemberSwitcher from "../components/FamilyMemberSwitcher";
import RecordsListScreen from "../screens/records/RecordsListScreen";
import UploadRecordScreen from "../screens/records/UploadRecordScreen";
import RecordDetailScreen from "../screens/records/RecordDetailScreen";
import EditRecordScreen from "../screens/records/EditRecordScreen";
import RecycleBinScreen from "../screens/records/RecycleBinScreen";
import OcrResultsScreen from "../screens/records/OcrResultsScreen";

export type RecordsStackParamList = {
  RecordsList: undefined;
  UploadRecord: undefined;
  RecordDetail: { id: string };
  EditRecord: { id: string };
  RecycleBin: undefined;
  OcrResults: { id: string };
};

const Stack = createNativeStackNavigator<RecordsStackParamList>();

function RecordsListHeaderRight() {
  const navigation = useNavigation<NativeStackNavigationProp<RecordsStackParamList>>();

  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: 14 }}>
      <TouchableOpacity onPress={() => navigation.navigate("RecycleBin")}>
        <Text style={{ color: "#0f766e", fontSize: 13, fontWeight: "600" }}>Recycle bin</Text>
      </TouchableOpacity>
      <FamilyMemberSwitcher />
    </View>
  );
}

export default function RecordsStack() {
  return (
    <Stack.Navigator>
      <Stack.Screen
        name="RecordsList"
        component={RecordsListScreen}
        options={{ title: "Health Records", headerRight: () => <RecordsListHeaderRight /> }}
      />
      <Stack.Screen name="UploadRecord" component={UploadRecordScreen} options={{ title: "Add a health record" }} />
      <Stack.Screen name="RecordDetail" component={RecordDetailScreen} options={{ title: "Record" }} />
      <Stack.Screen name="EditRecord" component={EditRecordScreen} options={{ title: "Edit record" }} />
      <Stack.Screen name="RecycleBin" component={RecycleBinScreen} options={{ title: "Recycle bin" }} />
      <Stack.Screen name="OcrResults" component={OcrResultsScreen} options={{ title: "Extracted data" }} />
    </Stack.Navigator>
  );
}
