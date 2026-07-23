import {
  BorderStyle,
  Document,
  HeadingLevel,
  Packer,
  Paragraph,
  TextRun,
  type IRunOptions,
} from "docx";
import { inlinePlainText } from "./markdown-tree.js";
import type { BlockNode, InlineNode, RestrictedDocument } from "./types.js";

/** Render restricted inline nodes to Word runs while propagating safe text styles. */
function renderInlineRuns(nodes: readonly InlineNode[], inherited: Pick<IRunOptions, "bold" | "italics"> = {}): TextRun[] {
  return nodes.flatMap((node): TextRun[] => {
    if (node.type === "text") {
      return node.value.split("\n").map((value, index) => new TextRun({ ...inherited, ...(index === 0 ? {} : { break: 1 }), text: value }));
    }
    if (node.type === "code") {
      return [new TextRun({ ...inherited, font: "Courier New", text: node.value })];
    }
    if (node.type === "link") {
      const text = node.url === "" || node.url === node.label ? node.label : `${node.label} (${node.url})`;
      return [new TextRun({ ...inherited, color: "334155", text, underline: {} })];
    }
    return renderInlineRuns(node.children, { ...inherited, ...(node.type === "bold" ? { bold: true } : { italics: true }) });
  });
}

/** Flatten a restricted block into text when a container such as a list or quote needs one paragraph. */
function blockPlainText(block: BlockNode): string {
  if (block.type === "paragraph" || block.type === "heading") {
    return inlinePlainText(block.children);
  }
  if (block.type === "codeBlock") {
    return block.value;
  }
  if (block.type === "thematicBreak") {
    return "";
  }
  if (block.type === "quote") {
    return block.children.map(blockPlainText).filter(Boolean).join(" ");
  }
  return block.items.map((item, index) => `${block.ordered ? `${index + 1}.` : "•"} ${item.map(blockPlainText).filter(Boolean).join(" ")}`).join("\n");
}

/** Convert restricted blocks to DOCX paragraphs without HTML, remote assets, or raw OOXML. */
function renderBlocks(blocks: readonly BlockNode[], listDepth = 0): Paragraph[] {
  return blocks.flatMap((block): Paragraph[] => {
    if (block.type === "paragraph") {
      return [new Paragraph({ children: renderInlineRuns(block.children), spacing: { after: 120, line: 276 } })];
    }
    if (block.type === "heading") {
      const heading = block.level === 1 ? HeadingLevel.HEADING_1 : block.level === 2 ? HeadingLevel.HEADING_2 : HeadingLevel.HEADING_3;
      return [new Paragraph({ children: renderInlineRuns(block.children), heading, keepNext: true, spacing: { after: 100, before: block.level === 1 ? 240 : 160 } })];
    }
    if (block.type === "thematicBreak") {
      return [new Paragraph({ border: { bottom: { color: "CBD5E1", size: 4, space: 8, style: BorderStyle.SINGLE } } })];
    }
    if (block.type === "codeBlock") {
      return [new Paragraph({ children: [new TextRun({ font: "Courier New", size: 18, text: block.value })], spacing: { after: 120 } })];
    }
    if (block.type === "quote") {
      return [new Paragraph({
        border: { left: { color: "94A3B8", size: 8, space: 8, style: BorderStyle.SINGLE } },
        children: [new TextRun({ italics: true, text: block.children.map(blockPlainText).filter(Boolean).join(" ") })],
      })];
    }
    return block.items.flatMap((item, index) => {
      const text = item.map(blockPlainText).filter(Boolean).join(" ");
      return [new Paragraph({
        ...(block.ordered ? {} : { bullet: { level: Math.min(8, listDepth) } }),
        children: [new TextRun({ text: block.ordered ? `${index + 1}. ${text}` : text })],
        ...(block.ordered ? { indent: { left: 360 * (listDepth + 1), hanging: 240 } } : {}),
        spacing: { after: 60 },
      })];
    });
  });
}

/** Render a restricted document tree to an ATS-readable DOCX buffer. */
export async function renderDocx(document: RestrictedDocument): Promise<Buffer> {
  const output = new Document({
    sections: [{
      children: renderBlocks(document.blocks),
      properties: {
        page: { margin: { bottom: 720, left: 792, right: 792, top: 720 } },
      },
    }],
    styles: {
      default: {
        document: { run: { font: "Aptos", size: 21 } },
        heading1: { run: { bold: true, color: "0F172A", font: "Aptos Display", size: 30 } },
        heading2: { run: { bold: true, color: "1E293B", font: "Aptos Display", size: 25 } },
        heading3: { run: { bold: true, color: "334155", font: "Aptos", size: 22 } },
      },
    },
  });
  return Packer.toBuffer(output);
}
