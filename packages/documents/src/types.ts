export type InlineNode =
  | { type: "text"; value: string }
  | { children: InlineNode[]; type: "bold" }
  | { children: InlineNode[]; type: "italic" }
  | { type: "code"; value: string }
  | { label: string; type: "link"; url: string };

export type BlockNode =
  | { children: InlineNode[]; type: "paragraph" }
  | { children: InlineNode[]; level: 1 | 2 | 3; type: "heading" }
  | { items: BlockNode[][]; ordered: boolean; type: "list" }
  | { children: BlockNode[]; type: "quote" }
  | { language: string | null; type: "codeBlock"; value: string }
  | { type: "thematicBreak" };

export interface RestrictedDocument {
  blocks: BlockNode[];
  version: 1;
}

export type SupportedDocumentKind = "docx" | "pdf" | "markdown" | "text";

export interface ValidatedUpload {
  content: Buffer;
  filename: string;
  kind: SupportedDocumentKind;
  mimeType: string;
  sha256: string;
  sizeBytes: bigint;
}
