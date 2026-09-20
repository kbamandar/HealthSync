import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";

import {
  familyApi,
  type AddFamilyMemberInput,
  type UpdateFamilyMemberInput,
} from "../api/familyApi";
import type { FamilyMember } from "../api/types";
import { useAuth } from "../auth/AuthContext";

interface FamilyContextValue {
  members: FamilyMember[];
  isLoading: boolean;
  selectedMemberId: string | null;
  selectedMember: FamilyMember | null;
  selectMember: (id: string) => void;
  refresh: () => Promise<void>;
  addMember: (input: AddFamilyMemberInput) => Promise<FamilyMember>;
  updateMember: (id: string, input: UpdateFamilyMemberInput) => Promise<FamilyMember>;
  removeMember: (id: string) => Promise<void>;
}

const FamilyContext = createContext<FamilyContextValue | null>(null);

export function FamilyProvider({ children }: { children: ReactNode }) {
  const { status } = useAuth();
  const [members, setMembers] = useState<FamilyMember[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedMemberId, setSelectedMemberId] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    setIsLoading(true);
    try {
      const result = await familyApi.list();
      setMembers(result);
      setSelectedMemberId((current) => {
        if (current && result.some((m) => m.id === current)) return current;
        return result.find((m) => m.relationship === "self")?.id ?? result[0]?.id ?? null;
      });
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (status === "authenticated") {
      refresh();
    }
  }, [status, refresh]);

  const addMember = useCallback(
    async (input: AddFamilyMemberInput) => {
      const member = await familyApi.add(input);
      await refresh();
      return member;
    },
    [refresh],
  );

  const updateMember = useCallback(
    async (id: string, input: UpdateFamilyMemberInput) => {
      const member = await familyApi.update(id, input);
      await refresh();
      return member;
    },
    [refresh],
  );

  const removeMember = useCallback(
    async (id: string) => {
      await familyApi.remove(id);
      await refresh();
    },
    [refresh],
  );

  const selectedMember = members.find((m) => m.id === selectedMemberId) ?? null;

  const value = useMemo(
    () => ({
      members,
      isLoading,
      selectedMemberId,
      selectedMember,
      selectMember: setSelectedMemberId,
      refresh,
      addMember,
      updateMember,
      removeMember,
    }),
    [members, isLoading, selectedMemberId, selectedMember, refresh, addMember, updateMember, removeMember],
  );

  return <FamilyContext.Provider value={value}>{children}</FamilyContext.Provider>;
}

export function useFamily(): FamilyContextValue {
  const context = useContext(FamilyContext);
  if (!context) {
    throw new Error("useFamily must be used within a FamilyProvider");
  }
  return context;
}
