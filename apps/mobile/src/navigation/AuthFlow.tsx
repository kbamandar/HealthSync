import { useState } from "react";

import OtpScreen from "../screens/auth/OtpScreen";
import WelcomeScreen from "../screens/auth/WelcomeScreen";

export default function AuthFlow() {
  const [pending, setPending] = useState<{ email: string; mobile: string } | null>(null);

  if (pending) {
    return <OtpScreen email={pending.email} mobile={pending.mobile} onBack={() => setPending(null)} />;
  }

  return <WelcomeScreen onCodeSent={(email, mobile) => setPending({ email, mobile })} />;
}
