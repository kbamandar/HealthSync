import { useEffect, useRef } from "react";
import { Alert, Linking } from "react-native";

import { familyApi } from "../api/familyApi";
import { useAuth } from "../auth/AuthContext";

interface PendingInvite {
  action: "accept" | "decline";
  token: string;
}

function parseInviteUrl(url: string): PendingInvite | null {
  let parsed: URL;
  try {
    parsed = new URL(url);
  } catch {
    return null;
  }

  const token = parsed.searchParams.get("token");
  if (!token) return null;

  const path = `${parsed.hostname}${parsed.pathname}`;
  if (path.includes("invite/accept")) return { action: "accept", token };
  if (path.includes("invite/decline")) return { action: "decline", token };

  return null;
}

/**
 * Handles healthsync://family/invite/accept|decline?token=... links from
 * the family invite email. Mounted unconditionally (outside the auth Gate)
 * so a link opened before the invitee logs in isn't lost — it's queued
 * and replayed once they're authenticated, which is required either way
 * since accept/decline are auth.jwt-protected on the API.
 */
export default function InviteLinkHandler() {
  const { status } = useAuth();
  const pendingRef = useRef<PendingInvite | null>(null);

  async function process(invite: PendingInvite) {
    try {
      if (invite.action === "accept") {
        const member = await familyApi.acceptInvite(invite.token);
        Alert.alert("Invite accepted", `You've joined ${member.display_name}'s HealthSync family.`);
      } else {
        await familyApi.declineInvite(invite.token);
        Alert.alert("Invite declined");
      }
    } catch {
      Alert.alert("Couldn't process this invite", "It may have expired, already been used, or belong to a different account.");
    }
  }

  useEffect(() => {
    function onUrl(url: string) {
      const invite = parseInviteUrl(url);
      if (!invite) return;

      if (status === "authenticated" || status === "needs-profile") {
        process(invite);
      } else {
        pendingRef.current = invite;
      }
    }

    Linking.getInitialURL().then((url) => {
      if (url) onUrl(url);
    });

    const subscription = Linking.addEventListener("url", ({ url }) => onUrl(url));
    return () => subscription.remove();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [status]);

  useEffect(() => {
    if ((status === "authenticated" || status === "needs-profile") && pendingRef.current) {
      const invite = pendingRef.current;
      pendingRef.current = null;
      process(invite);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [status]);

  return null;
}
