import type { StoredDocument } from "@job/db";
import { extractDocumentText, type SupportedDocumentKind } from "@job/documents";

/** Infer a stored document kind from its canonical MIME type and filename. */
export function storedDocumentKind(document: Pick<StoredDocument, "filename" | "mimeType">): SupportedDocumentKind {
  if (document.mimeType === "application/pdf" || document.filename.toLowerCase().endsWith(".pdf")) {
    return "pdf";
  }
  if (document.mimeType.includes("wordprocessingml") || document.filename.toLowerCase().endsWith(".docx")) {
    return "docx";
  }
  return document.mimeType === "text/markdown" || document.filename.toLowerCase().endsWith(".md") ? "markdown" : "text";
}

/** Extract source text from an already ownership-checked database document. */
export async function extractStoredDocument(document: StoredDocument): Promise<string> {
  return extractDocumentText(storedDocumentKind(document), document.content);
}
