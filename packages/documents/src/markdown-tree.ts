import type { PhrasingContent, RootContent } from "mdast";
import { unified } from "unified";
import remarkParse from "remark-parse";
import type { BlockNode, InlineNode, RestrictedDocument } from "./types.js";

/** Keep only display-safe link schemes; renderers never fetch the target. */
function safeLinkUrl(value: string): string {
  try {
    const url = new URL(value);
    return ["http:", "https:", "mailto:"].includes(url.protocol) ? value : "";
  } catch {
    return "";
  }
}

/** Convert Markdown phrasing nodes to the restricted inline tree and drop raw HTML or image resources. */
function convertInline(nodes: readonly PhrasingContent[]): InlineNode[] {
  return nodes.flatMap((node): InlineNode[] => {
    switch (node.type) {
      case "text":
        return [{ type: "text", value: node.value }];
      case "strong":
        return [{ children: convertInline(node.children), type: "bold" }];
      case "emphasis":
        return [{ children: convertInline(node.children), type: "italic" }];
      case "inlineCode":
        return [{ type: "code", value: node.value }];
      case "break":
        return [{ type: "text", value: "\n" }];
      case "link": {
        const url = safeLinkUrl(node.url);
        const label = inlinePlainText(convertInline(node.children));
        return [{ label, type: "link", url }];
      }
      case "image":
        return node.alt == null ? [] : [{ type: "text", value: node.alt }];
      case "html":
      default:
        return [];
    }
  });
}

/** Convert one Markdown block node into the restricted document structure. */
function convertBlock(node: RootContent): BlockNode[] {
  switch (node.type) {
    case "paragraph":
      return [{ children: convertInline(node.children), type: "paragraph" }];
    case "heading":
      return [{ children: convertInline(node.children), level: Math.min(3, node.depth) as 1 | 2 | 3, type: "heading" }];
    case "list":
      return [{
        items: node.children.map((item) => item.children.flatMap(convertBlock)),
        ordered: node.ordered === true,
        type: "list",
      }];
    case "blockquote":
      return [{ children: node.children.flatMap(convertBlock), type: "quote" }];
    case "code":
      return [{ language: node.lang ?? null, type: "codeBlock", value: node.value }];
    case "thematicBreak":
      return [{ type: "thematicBreak" }];
    case "html":
    case "definition":
    default:
      return [];
  }
}

/** Parse generated Markdown into a renderer-safe, versioned document tree. */
export function parseRestrictedMarkdown(markdown: string): RestrictedDocument {
  const root = unified().use(remarkParse).parse(markdown);
  return { blocks: root.children.flatMap(convertBlock), version: 1 };
}

/** Flatten safe inline content for PDF output, previews, and link labels. */
export function inlinePlainText(nodes: readonly InlineNode[]): string {
  return nodes.map((node) => {
    if (node.type === "text" || node.type === "code") {
      return node.value;
    }
    if (node.type === "link") {
      return node.url === "" || node.url === node.label ? node.label : `${node.label} (${node.url})`;
    }
    return inlinePlainText(node.children);
  }).join("");
}

/** Flatten a restricted document tree without interpreting raw Markdown or HTML. */
export function documentPlainText(document: RestrictedDocument): string {
  const renderBlocks = (blocks: readonly BlockNode[], depth = 0): string => blocks.map((block) => {
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
      return renderBlocks(block.children, depth);
    }
    return block.items.map((item, index) => `${block.ordered ? `${index + 1}.` : "•"} ${renderBlocks(item, depth + 1)}`).join("\n");
  }).join("\n\n");
  return renderBlocks(document.blocks).trim();
}
