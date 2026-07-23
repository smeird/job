import { describe, expect, it } from "vitest";
import JSZip from "jszip";
import { extractDocumentText } from "./extraction.js";
import { documentPlainText, parseRestrictedMarkdown } from "./markdown-tree.js";
import { renderDocx } from "./render-docx.js";
import { renderPdf } from "./render-pdf.js";
import { validateUpload } from "./validation.js";

const fixtureMarkdown = "# Alex Example\n\nSenior engineer building reliable systems.\n\n## Experience\n\n- Led a migration\n- Reduced incidents by 30%";

describe("safe document pipeline", () => {
  it("drops raw HTML and external image resources from the typed tree", () => {
    const tree = parseRestrictedMarkdown("# CV\n\n<img src=\"https://evil.example/x\">\n\n![portrait](https://evil.example/image.png)\n\n[Profile](javascript:alert(1))");
    const text = documentPlainText(tree);
    expect(text).not.toContain("<img");
    expect(text).toContain("portrait");
    expect(text).not.toContain("javascript:");
  });

  it("renders valid DOCX and PDF signatures and can extract the DOCX text", async () => {
    const tree = parseRestrictedMarkdown(fixtureMarkdown);
    const [docx, pdf] = await Promise.all([renderDocx(tree), renderPdf(tree)]);
    expect(docx.subarray(0, 2).toString()).toBe("PK");
    expect(pdf.subarray(0, 5).toString()).toBe("%PDF-");
    await expect(validateUpload("cv.docx", docx)).resolves.toMatchObject({ kind: "docx" });
    await expect(validateUpload("cv.pdf", pdf)).resolves.toMatchObject({ kind: "pdf" });
    await expect(extractDocumentText("docx", docx)).resolves.toContain("Reduced incidents by 30%");
  });

  it("rejects invalid signatures, binary text, active PDF content, and oversized uploads", async () => {
    await expect(validateUpload("cv.docx", Buffer.from("not a zip"))).rejects.toThrow(/signature/i);
    await expect(validateUpload("cv.txt", Buffer.from([65, 0, 66]))).rejects.toThrow(/binary/i);
    await expect(validateUpload("cv.pdf", Buffer.from("%PDF-1.7\n/JavaScript true"))).rejects.toThrow(/active/i);
    await expect(validateUpload("cv.txt", Buffer.alloc(1_048_577, 65))).rejects.toThrow(/1 MiB/i);
  });

  it("rejects macro payloads and external DOCX relationships", async () => {
    const source = await renderDocx(parseRestrictedMarkdown(fixtureMarkdown));
    const macroArchive = await JSZip.loadAsync(source);
    macroArchive.file("word/vbaProject.bin", "unsafe");
    await expect(validateUpload("macro.docx", await macroArchive.generateAsync({ type: "nodebuffer" }))).rejects.toThrow(/macro/i);

    const externalArchive = await JSZip.loadAsync(source);
    externalArchive.file("word/_rels/document.xml.rels", `<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.com/tracker" TargetMode="External"/></Relationships>`);
    await expect(validateUpload("external.docx", await externalArchive.generateAsync({ type: "nodebuffer" }))).rejects.toThrow(/external/i);
  });
});
