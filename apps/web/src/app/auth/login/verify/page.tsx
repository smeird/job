import type { ReactNode } from "react";
import { VerifyChallenge } from "../../../../components/VerifyChallenge.js";

/** Render login verification at the legacy public URL. */
export default function LoginVerifyPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }): ReactNode {
  return <VerifyChallenge action="login" searchParams={searchParams} />;
}
