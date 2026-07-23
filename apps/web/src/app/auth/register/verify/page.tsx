import type { ReactNode } from "react";
import { VerifyChallenge } from "../../../../components/VerifyChallenge.js";

/** Render registration verification at the legacy public URL. */
export default function RegisterVerifyPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }): ReactNode {
  return <VerifyChallenge action="register" searchParams={searchParams} />;
}
