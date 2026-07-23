import { redirect } from "next/navigation";

/** Preserve the legacy /auth entry point by forwarding it to sign-in. */
export default function AuthPage(): never {
  redirect("/auth/login");
}
