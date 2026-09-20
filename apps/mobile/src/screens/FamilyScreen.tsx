// The Family tab now needs its own internal navigation (list -> add ->
// detail), so the real implementation lives in navigation/FamilyStack and
// screens/family/*. This file stays as the tab's entry point so MainTabs'
// import doesn't need to change.
export { default } from "../navigation/FamilyStack";
