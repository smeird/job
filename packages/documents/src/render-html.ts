import type { BlockNode, InlineNode, RestrictedDocument } from "./types.js";

/** Escape text before including it in the restricted HTML representation. */
function escapeHtml(value: string): string {
  return value.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

/** Render safe inline nodes without images, scripts, styles, or fetched resources. */
function renderInline(nodes: readonly InlineNode[]): string {
  return nodes.map((node) => {
    if (node.type === "text") {
      return escapeHtml(node.value).replace(/\n/g, "<br>");
    }
    if (node.type === "code") {
      return `<code>${escapeHtml(node.value)}</code>`;
    }
    if (node.type === "link") {
      const suffix = node.url === "" || node.url === node.label ? "" : ` (${node.url})`;
      return `<span>${escapeHtml(`${node.label}${suffix}`)}</span>`;
    }
    const tag = node.type === "bold" ? "strong" : "em";
    return `<${tag}>${renderInline(node.children)}</${tag}>`;
  }).join("");
}

/** Render safe block nodes recursively from the restricted document tree. */
function renderBlocks(blocks: readonly BlockNode[]): string {
  return blocks.map((block) => {
    if (block.type === "paragraph") {
      return `<p>${renderInline(block.children)}</p>`;
    }
    if (block.type === "heading") {
      return `<h${block.level}>${renderInline(block.children)}</h${block.level}>`;
    }
    if (block.type === "thematicBreak") {
      return "<hr>";
    }
    if (block.type === "codeBlock") {
      return `<pre><code>${escapeHtml(block.value)}</code></pre>`;
    }
    if (block.type === "quote") {
      return `<blockquote>${renderBlocks(block.children)}</blockquote>`;
    }
    const tag = block.ordered ? "ol" : "ul";
    return `<${tag}>${block.items.map((item) => `<li>${renderBlocks(item)}</li>`).join("")}</${tag}>`;
  }).join("\n");
}

/** Render a restricted tree to inert semantic HTML for legacy previews. */
export function renderRestrictedHtml(document: RestrictedDocument): string {
  return renderBlocks(document.blocks);
}
