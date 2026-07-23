import { NextResponse } from "next/server";

/** Report web-process health without touching MySQL or external services. */
export function GET(): NextResponse {
  return new NextResponse("ok", { headers: { "Content-Type": "text/plain; charset=utf-8" }, status: 200 });
}
