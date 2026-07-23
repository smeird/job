import mammoth from "mammoth";
import { getDocument } from "pdfjs-dist/legacy/build/pdf.mjs";
import type { SupportedDocumentKind } from "./types.js";

/** Normalize extracted text to stable line endings and bounded blank-line runs. */
function normalizeExtractedText(value: string): string {
  return value.replace(/\r\n?/g, "\n").replace(/[ \t]+\n/g, "\n").replace(/\n{3,}/g, "\n\n").trim();
}

/** Extract text from a validated DOCX with Mammoth and no HTML renderer. */
async function extractDocx(content: Buffer): Promise<string> {
  const result = await mammoth.extractRawText({ buffer: content });
  return normalizeExtractedText(result.value);
}

/** Extract page text from a validated PDF with PDF.js without invoking an HTML renderer. */
async function extractPdf(content: Buffer): Promise<string> {
  const loadingTask = getDocument({ data: new Uint8Array(content), disableFontFace: true, useSystemFonts: false });
  const document = await loadingTask.promise;
  const pages: string[] = [];
  try {
    for (let index = 1; index <= document.numPages; index += 1) {
      const page = await document.getPage(index);
      const text = await page.getTextContent();
      const pageText = text.items
        .map((item) => "str" in item ? item.str : "")
        .filter(Boolean)
        .join(" ");
      pages.push(pageText);
      page.cleanup();
    }
  } finally {
    await loadingTask.destroy();
  }
  return normalizeExtractedText(pages.join("\n\n"));
}

/** Extract canonical plain text from one of the four validated upload kinds. */
export async function extractDocumentText(kind: SupportedDocumentKind, content: Buffer): Promise<string> {
  if (kind === "docx") {
    return extractDocx(content);
  }
  if (kind === "pdf") {
    return extractPdf(content);
  }
  return normalizeExtractedText(content.toString("utf8"));
}
