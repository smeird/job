import type { ReactNode } from "react";

/** Render restrained status and error feedback shared by settings and forms. */
export function Alert({ children, kind = "status" }: { children: ReactNode; kind?: "status" | "error" }): ReactNode {
  return (
    <div className={kind === "error"
      ? "rounded-xl border border-rose-400/25 bg-rose-950/30 px-4 py-3 text-sm text-rose-200"
      : "rounded-xl border border-emerald-400/25 bg-emerald-950/25 px-4 py-3 text-sm text-emerald-200"}
    >
      {children}
    </div>
  );
}
