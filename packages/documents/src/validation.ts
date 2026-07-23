import { createHash } from "node:crypto";
import { extname } from "node:path";
import yauzl, { type Entry, type ZipFile } from "yauzl";
import type { SupportedDocumentKind, ValidatedUpload } from "./types.js";

export const MAX_UPLOAD_BYTES = 1_048_576;
const MAX_ZIP_ENTRIES = 10_000;
const MAX_ZIP_UNCOMPRESSED_BYTES = 32 * 1_048_576;
const MAX_XML_ENTRY_BYTES = 2 * 1_048_576;

export class DocumentValidationError extends Error {
  public constructor(message: string) {
    super(message);
    this.name = "DocumentValidationError";
  }
}

/** Open a ZIP archive from memory with entry-size checks enabled. */
function openZip(buffer: Buffer): Promise<ZipFile> {
  return new Promise((resolve, reject) => {
    yauzl.fromBuffer(buffer, { lazyEntries: true, validateEntrySizes: true }, (error, zipFile) => {
      if (error !== null || zipFile === undefined) {
        reject(error ?? new DocumentValidationError("The DOCX archive could not be opened."));
        return;
      }
      resolve(zipFile);
    });
  });
}

/** Read a bounded ZIP entry into memory for structural security checks. */
function readZipEntry(zipFile: ZipFile, entry: Entry): Promise<Buffer> {
  return new Promise((resolve, reject) => {
    zipFile.openReadStream(entry, (error, stream) => {
      if (error !== null || stream === undefined) {
        reject(error ?? new DocumentValidationError("A DOCX archive entry could not be read."));
        return;
      }
      const chunks: Buffer[] = [];
      let length = 0;
      stream.on("data", (chunk: Buffer) => {
        length += chunk.length;
        if (length > MAX_XML_ENTRY_BYTES) {
          stream.destroy(new DocumentValidationError("A DOCX XML entry is unexpectedly large."));
          return;
        }
        chunks.push(chunk);
      });
      stream.once("error", reject);
      stream.once("end", () => resolve(Buffer.concat(chunks)));
    });
  });
}

/** Inspect DOCX ZIP structure, macros, path traversal, bombs, and external relationships. */
async function validateDocxArchive(buffer: Buffer): Promise<void> {
  const zipFile = await openZip(buffer);
  await new Promise<void>((resolve, reject) => {
    let entryCount = 0;
    let totalUncompressed = 0;
    let hasContentTypes = false;
    let hasDocumentXml = false;
    let settled = false;

    const fail = (error: unknown): void => {
      if (!settled) {
        settled = true;
        zipFile.close();
        reject(error instanceof Error ? error : new DocumentValidationError("The DOCX archive is malformed."));
      }
    };

    zipFile.once("error", fail);
    zipFile.once("end", () => {
      if (settled) {
        return;
      }
      if (!hasContentTypes || !hasDocumentXml) {
        fail(new DocumentValidationError("The upload is not a complete DOCX document."));
        return;
      }
      settled = true;
      zipFile.close();
      resolve();
    });
    zipFile.on("entry", (entry) => {
      void (async () => {
        entryCount += 1;
        totalUncompressed += entry.uncompressedSize;
        const name = entry.fileName.replace(/\\/g, "/");
        const lowerName = name.toLowerCase();
        if (entryCount > MAX_ZIP_ENTRIES || totalUncompressed > MAX_ZIP_UNCOMPRESSED_BYTES) {
          throw new DocumentValidationError("The DOCX archive expands beyond the safe limit.");
        }
        if (name.startsWith("/") || name.split("/").includes("..") || name.includes("\0")) {
          throw new DocumentValidationError("The DOCX archive contains an unsafe path.");
        }
        if (lowerName.endsWith("word/vbaproject.bin") || lowerName.includes("macros/")) {
          throw new DocumentValidationError("Macro-enabled documents are not supported.");
        }
        hasContentTypes ||= name === "[Content_Types].xml";
        hasDocumentXml ||= lowerName === "word/document.xml";
        if ((name === "[Content_Types].xml" || lowerName.endsWith(".rels")) && entry.uncompressedSize > 0) {
          const xml = (await readZipEntry(zipFile, entry)).toString("utf8");
          if (/macroEnabled|vbaProject/i.test(xml)) {
            throw new DocumentValidationError("Macro-enabled documents are not supported.");
          }
          if (lowerName.endsWith(".rels") && /TargetMode\s*=\s*["']External["']/i.test(xml)) {
            throw new DocumentValidationError("DOCX files with external relationships are not supported.");
          }
        }
        zipFile.readEntry();
      })().catch(fail);
    });
    zipFile.readEntry();
  });
}

/** Map an allowed file extension to its canonical kind and MIME type. */
function identifyExtension(filename: string): { kind: SupportedDocumentKind; mimeType: string } {
  const extension = extname(filename).toLowerCase();
  const mapping: Record<string, { kind: SupportedDocumentKind; mimeType: string }> = {
    ".docx": { kind: "docx", mimeType: "application/vnd.openxmlformats-officedocument.wordprocessingml.document" },
    ".md": { kind: "markdown", mimeType: "text/markdown" },
    ".pdf": { kind: "pdf", mimeType: "application/pdf" },
    ".txt": { kind: "text", mimeType: "text/plain" },
  };
  const identified = mapping[extension];
  if (identified === undefined) {
    throw new DocumentValidationError("Supported file types are DOCX, PDF, Markdown, and plain text.");
  }
  return identified;
}

/** Remove path components and control characters while retaining the user's recognizable filename. */
export function sanitizeFilename(filename: string): string {
  const leaf = filename.split(/[\\/]/).pop()?.replace(/[\u0000-\u001f\u007f]/g, "").trim() ?? "";
  if (leaf === "" || leaf.length > 255) {
    throw new DocumentValidationError("The uploaded filename is invalid.");
  }
  return leaf;
}

/** Validate size, signature, archive structure, active content, and canonical MIME type. */
export async function validateUpload(filenameValue: string, content: Buffer): Promise<ValidatedUpload> {
  const filename = sanitizeFilename(filenameValue);
  if (content.length === 0 || content.length > MAX_UPLOAD_BYTES) {
    throw new DocumentValidationError("Documents must be between 1 byte and 1 MiB.");
  }
  const identified = identifyExtension(filename);
  if (identified.kind === "docx") {
    const signature = content.subarray(0, 4).toString("hex");
    if (!["504b0304", "504b0506", "504b0708"].includes(signature)) {
      throw new DocumentValidationError("The DOCX file signature is invalid.");
    }
    await validateDocxArchive(content);
  } else if (identified.kind === "pdf") {
    if (!content.subarray(0, 8).toString("latin1").startsWith("%PDF-")) {
      throw new DocumentValidationError("The PDF file signature is invalid.");
    }
    const source = content.toString("latin1");
    if (/\/(?:JavaScript|JS|Launch|EmbeddedFile)\b/.test(source)) {
      throw new DocumentValidationError("PDF files containing active or embedded content are not supported.");
    }
  } else if (content.includes(0)) {
    throw new DocumentValidationError("Text documents may not contain binary null bytes.");
  }

  return {
    content,
    filename,
    kind: identified.kind,
    mimeType: identified.mimeType,
    sha256: createHash("sha256").update(content).digest("hex"),
    sizeBytes: BigInt(content.length),
  };
}
