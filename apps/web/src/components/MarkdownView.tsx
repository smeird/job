import { parseRestrictedMarkdown, type BlockNode, type InlineNode } from "@job/documents";
import type { ReactNode } from "react";

/** Render safe inline nodes as React elements without raw HTML. */
function Inline({ nodes }: { nodes: readonly InlineNode[] }): ReactNode {
  return nodes.map((node, index) => {
    if (node.type === "text") {
      return <span key={index}>{node.value}</span>;
    }
    if (node.type === "code") {
      return <code key={index}>{node.value}</code>;
    }
    if (node.type === "link") {
      return <span key={index}>{node.url === "" || node.url === node.label ? node.label : `${node.label} (${node.url})`}</span>;
    }
    return node.type === "bold"
      ? <strong key={index}><Inline nodes={node.children} /></strong>
      : <em key={index}><Inline nodes={node.children} /></em>;
  });
}

/** Render safe block nodes recursively as semantic React elements. */
function Blocks({ blocks }: { blocks: readonly BlockNode[] }): ReactNode {
  return blocks.map((block, index) => {
    if (block.type === "paragraph") {
      return <p key={index}><Inline nodes={block.children} /></p>;
    }
    if (block.type === "heading") {
      return block.level === 1
        ? <h1 key={index}><Inline nodes={block.children} /></h1>
        : block.level === 2
          ? <h2 key={index}><Inline nodes={block.children} /></h2>
          : <h3 key={index}><Inline nodes={block.children} /></h3>;
    }
    if (block.type === "codeBlock") {
      return <pre key={index}><code>{block.value}</code></pre>;
    }
    if (block.type === "thematicBreak") {
      return <hr key={index} />;
    }
    if (block.type === "quote") {
      return <blockquote key={index}><Blocks blocks={block.children} /></blockquote>;
    }
    const items = block.items.map((item, itemIndex) => <li key={itemIndex}><Blocks blocks={item} /></li>);
    return block.ordered ? <ol key={index}>{items}</ol> : <ul key={index}>{items}</ul>;
  });
}

/** Parse and render stored Markdown through the same restricted tree used by document exporters. */
export function MarkdownView({ markdown }: { markdown: string }): ReactNode {
  return <div className="prose-safe"><Blocks blocks={parseRestrictedMarkdown(markdown).blocks} /></div>;
}
