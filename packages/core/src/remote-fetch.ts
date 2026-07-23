import { lookup } from "node:dns/promises";
import { isIP } from "node:net";
import { validateRemoteHttpUrl } from "./security.js";

const MAX_REDIRECTS = 5;
const MAX_RESPONSE_BYTES = 2 * 1_024 * 1_024;

/** Identify IP ranges that must never be reached by the server-side posting importer. */
function isPrivateAddress(address: string): boolean {
  const normalized = address.toLowerCase().replace(/^::ffff:/, "");
  if (isIP(normalized) === 4) {
    const octets = normalized.split(".").map(Number);
    const [first = 0, second = 0] = octets;
    return first === 0 || first === 10 || first === 127 || first >= 224
      || (first === 169 && second === 254)
      || (first === 172 && second >= 16 && second <= 31)
      || (first === 192 && second === 168)
      || (first === 100 && second >= 64 && second <= 127);
  }
  return normalized === "::" || normalized === "::1" || normalized.startsWith("fc")
    || normalized.startsWith("fd") || normalized.startsWith("fe8") || normalized.startsWith("fe9")
    || normalized.startsWith("fea") || normalized.startsWith("feb") || normalized.startsWith("2001:db8:");
}

/** Resolve every advertised address and reject names that can reach local or reserved networks. */
async function assertPublicHost(url: URL): Promise<void> {
  const hostname = url.hostname.replace(/^\[|\]$/g, "");
  if (isIP(hostname) !== 0) {
    if (isPrivateAddress(hostname)) {
      throw new Error("Local and private network URLs are not allowed.");
    }
    return;
  }
  const addresses = await lookup(hostname, { all: true, verbatim: true });
  if (addresses.length === 0 || addresses.some((entry) => isPrivateAddress(entry.address))) {
    throw new Error("The URL does not resolve to a public host.");
  }
}

/** Read a response stream with a hard byte limit independent of Content-Length. */
async function boundedResponseText(response: Response): Promise<string> {
  const declared = Number(response.headers.get("content-length") ?? 0);
  if (Number.isFinite(declared) && declared > MAX_RESPONSE_BYTES) {
    throw new Error("The job advert is too large to import.");
  }
  if (response.body === null) {
    return "";
  }
  const reader = response.body.getReader();
  const chunks: Uint8Array[] = [];
  let size = 0;
  while (true) {
    const result = await reader.read();
    if (result.done) {
      break;
    }
    size += result.value.byteLength;
    if (size > MAX_RESPONSE_BYTES) {
      await reader.cancel();
      throw new Error("The job advert is too large to import.");
    }
    chunks.push(result.value);
  }
  const combined = new Uint8Array(size);
  let offset = 0;
  for (const chunk of chunks) {
    combined.set(chunk, offset);
    offset += chunk.byteLength;
  }
  return new TextDecoder("utf-8", { fatal: false }).decode(combined);
}

/** Fetch a public text resource with DNS, redirect, timeout, content-type, and size protections. */
export async function fetchPublicText(value: string): Promise<{ body: string; url: URL }> {
  let url = validateRemoteHttpUrl(value.trim());
  for (let redirect = 0; redirect <= MAX_REDIRECTS; redirect += 1) {
    await assertPublicHost(url);
    const response = await fetch(url, {
      headers: {
        Accept: "text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.2",
        "User-Agent": "JobTune/2.0 (+https://job.smeird.com)",
      },
      redirect: "manual",
      signal: AbortSignal.timeout(20_000),
    });
    if ([301, 302, 303, 307, 308].includes(response.status)) {
      const location = response.headers.get("location");
      if (location === null || redirect === MAX_REDIRECTS) {
        throw new Error("The job advert redirected too many times.");
      }
      url = validateRemoteHttpUrl(new URL(location, url).toString());
      continue;
    }
    if (!response.ok) {
      throw new Error(`The job advert returned HTTP ${response.status}.`);
    }
    const contentType = response.headers.get("content-type")?.toLowerCase() ?? "";
    if (contentType !== "" && !contentType.includes("text/html") && !contentType.includes("application/xhtml+xml") && !contentType.includes("text/plain")) {
      throw new Error("The URL did not return a readable web page.");
    }
    return { body: await boundedResponseText(response), url };
  }
  throw new Error("The job advert could not be imported.");
}

/** Decode the small entity set commonly present in titles and extracted body copy. */
function decodeHtmlEntities(value: string): string {
  return value
    .replace(/&#(\d+);/g, (_match, code: string) => String.fromCodePoint(Number(code)))
    .replace(/&#x([0-9a-f]+);/gi, (_match, code: string) => String.fromCodePoint(Number.parseInt(code, 16)))
    .replace(/&nbsp;/gi, " ")
    .replace(/&amp;/gi, "&")
    .replace(/&quot;/gi, '"')
    .replace(/&#39;|&apos;/gi, "'")
    .replace(/&lt;/gi, "<")
    .replace(/&gt;/gi, ">");
}

/** Convert fetched HTML to bounded plain text and a best-effort page title. */
export function extractJobPosting(html: string, sourceUrl: URL): { description: string; source_url: string; title: string } {
  const titleMatch = html.match(/<title\b[^>]*>([\s\S]*?)<\/title>/i) ?? html.match(/<h1\b[^>]*>([\s\S]*?)<\/h1>/i);
  const title = decodeHtmlEntities((titleMatch?.[1] ?? "").replace(/<[^>]+>/g, " ")).replace(/\s+/g, " ").trim().slice(0, 255);
  const withoutHidden = html
    .replace(/<(script|style|noscript|svg|template)\b[^>]*>[\s\S]*?<\/\1>/gi, " ")
    .replace(/<\/(?:p|div|section|article|li|ul|ol|h[1-6]|tr|br)>/gi, "\n")
    .replace(/<[^>]+>/g, " ");
  const description = decodeHtmlEntities(withoutHidden)
    .replace(/[ \t]+/g, " ")
    .replace(/\n\s*\n\s*\n+/g, "\n\n")
    .trim()
    .slice(0, 60_000);
  if (description === "") {
    throw new Error("The fetched page did not contain readable job description text.");
  }
  return { description, source_url: sourceUrl.toString(), title };
}

/** Import and normalize one public job advert without retaining or rendering its raw HTML. */
export async function fetchJobPosting(value: string): Promise<{ description: string; source_url: string; title: string }> {
  const fetched = await fetchPublicText(value);
  return extractJobPosting(fetched.body, fetched.url);
}
