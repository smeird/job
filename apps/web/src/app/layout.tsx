import type { Metadata } from "next";
import type { ReactNode } from "react";
import "tabulator-tables/dist/css/tabulator_midnight.min.css";
import "./globals.css";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  applicationName: "Job Tune",
  description: "Evidence-led CV tailoring and application tracking.",
  title: { default: "Job Tune", template: "%s · Job Tune" },
};

/** Provide the shared document shell while individual pages control authenticated navigation. */
export default function RootLayout({ children }: Readonly<{ children: ReactNode }>): ReactNode {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
