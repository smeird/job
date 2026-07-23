import { describe, expect, it } from "vitest";
import { extractJobPosting } from "./remote-fetch.js";
import { validateRemoteHttpUrl } from "./security.js";

describe("job posting import security", () => {
  it("rejects local schemes and private literal addresses", () => {
    expect(() => validateRemoteHttpUrl("file:///etc/passwd")).toThrow();
    expect(() => validateRemoteHttpUrl("http://127.0.0.1/admin")).toThrow();
    expect(() => validateRemoteHttpUrl("http://[::1]/admin")).toThrow();
  });

  it("extracts text while removing scripts and markup", () => {
    const posting = extractJobPosting("<title>Engineer &amp; Lead</title><script>steal()</script><h1>Engineer</h1><p>Build reliable systems.</p>", new URL("https://jobs.example.com/1"));
    expect(posting.title).toBe("Engineer & Lead");
    expect(posting.description).toContain("Build reliable systems.");
    expect(posting.description).not.toContain("steal");
    expect(posting.description).not.toContain("<p>");
  });
});
