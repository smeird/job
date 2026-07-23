import PDFDocument from "pdfkit";
import { inlinePlainText } from "./markdown-tree.js";
import type { BlockNode, RestrictedDocument } from "./types.js";

/** Write restricted blocks to PDFKit using only built-in fonts and local text. */
function writeBlocks(pdf: PDFKit.PDFDocument, blocks: readonly BlockNode[], listDepth = 0): void {
  for (const block of blocks) {
    if (block.type === "heading") {
      const size = block.level === 1 ? 18 : block.level === 2 ? 14 : 11.5;
      pdf.moveDown(block.level === 1 ? 0.7 : 0.45).font("Helvetica-Bold").fontSize(size).fillColor("#0f172a").text(inlinePlainText(block.children), { lineGap: 2 });
      continue;
    }
    if (block.type === "paragraph") {
      pdf.font("Helvetica").fontSize(10.5).fillColor("#1e293b").text(inlinePlainText(block.children), { lineGap: 2.5, paragraphGap: 5 });
      continue;
    }
    if (block.type === "thematicBreak") {
      const y = pdf.y + 5;
      pdf.moveTo(pdf.page.margins.left, y).lineTo(pdf.page.width - pdf.page.margins.right, y).strokeColor("#cbd5e1").lineWidth(0.5).stroke().moveDown(0.8);
      continue;
    }
    if (block.type === "codeBlock") {
      pdf.font("Courier").fontSize(9).fillColor("#334155").text(block.value, { lineGap: 2, paragraphGap: 6 });
      continue;
    }
    if (block.type === "quote") {
      const x = pdf.x;
      pdf.x += 14;
      pdf.font("Helvetica-Oblique");
      writeBlocks(pdf, block.children, listDepth);
      pdf.x = x;
      continue;
    }
    block.items.forEach((item, index) => {
      const prefix = block.ordered ? `${index + 1}.` : "•";
      const text = item.map((node) => node.type === "paragraph" || node.type === "heading" ? inlinePlainText(node.children) : node.type === "codeBlock" ? node.value : "").filter(Boolean).join(" ");
      pdf.font("Helvetica").fontSize(10.5).fillColor("#1e293b").text(`${"  ".repeat(listDepth)}${prefix}  ${text}`, { indent: 12, lineGap: 2, paragraphGap: 3 });
      const nested = item.filter((node) => node.type === "list");
      if (nested.length > 0) {
        writeBlocks(pdf, nested, listDepth + 1);
      }
    });
  }
}

/** Render a restricted document tree to a PDF buffer without browser or HTML execution. */
export function renderPdf(document: RestrictedDocument, metadata: { author?: string; title?: string } = {}): Promise<Buffer> {
  return new Promise((resolve, reject) => {
    const pdf = new PDFDocument({
      bufferPages: true,
      info: { Author: metadata.author ?? "", Creator: "Job Tune", Title: metadata.title ?? "Tailored application document" },
      margins: { bottom: 44, left: 50, right: 50, top: 44 },
      size: "A4",
    });
    const chunks: Buffer[] = [];
    pdf.on("data", (chunk: Buffer) => chunks.push(chunk));
    pdf.once("error", reject);
    pdf.once("end", () => resolve(Buffer.concat(chunks)));
    writeBlocks(pdf, document.blocks);
    pdf.end();
  });
}
