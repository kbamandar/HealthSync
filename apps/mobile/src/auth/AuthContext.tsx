import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";

import { authApi, type ConsentInput } from "../api/authApi";
import { usersApi, type UpdateProfileInput } from "../api/usersApi";
import type { UserProfile } from "../api/types";
import { tokenStorage } from "./tokenStorage";

type AuthStatus = "loading" | "unauthenticated" | "needs-profile" | "authenticated";

interface AuthContextValue {
  status: AuthStatus;
  user: UserProfile | null;
  sendOtp: (email: string, mobile: string) => Promise<void>;
  verifyOtp: (email: string, mobile: string, otp: string, consent?: ConsentInput) => Promise<void>;
  completeProfile: (input: UpdateProfileInput) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

function statusFor(user: UserProfile): AuthStatus {
  return user.profile_complete ? "authenticated" : "needs-profile";
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<AuthStatus>("loading");
  const [user, setUser] = useState<UserProfile | null>(null);

  useEffect(() => {
    (async () => {
      const accessToken = await tokenStorage.getAccessToken();

      if (!accessToken) {
        setStatus("unauthenticated");
        return;
      }

      try {
        const profile = await usersApi.me();
        setUser(profile);
        setStatus(statusFor(profile));
      } catch {
        await tokenStorage.clear();
        setStatus("unauthenticated");
      }
    })();
  }, []);

  const sendOtp = useCallback(async (email: string, mobile: string) => {
    await authApi.sendOtp(email, mobile);
  }, []);

  const verifyOtp = useCallback(async (email: string, mobile: string, otp: string, consent?: ConsentInput) => {
    const result = await authApi.verifyOtp(email, mobile, otp, consent);
    await tokenStorage.setTokens(result.access_token, result.refresh_token);
    setUser(result.user);
    setStatus(statusFor(result.user));
  }, []);

  const completeProfile = useCallback(async (input: UpdateProfileInput) => {
    const profile = await usersApi.updateMe(input);
    setUser(profile);
    setStatus(statusFor(profile));
  }, []);

  const logout = useCallback(async () => {
    const refreshToken = await tokenStorage.getRefreshToken();
    if (refreshToken) {
      await authApi.logout(refreshToken).catch(() => {
        // Best-effort — clear local state regardless of server response.
      });
    }
    await tokenStorage.clear();
    setUser(null);
    setStatus("unauthenticated");
  }, []);

  const value = useMemo(
    () => ({ status, user, sendOtp, verifyOtp, completeProfile, logout }),
    [status, user, sendOtp, verifyOtp, completeProfile, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}
