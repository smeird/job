import { AlignmentType, BorderStyle, Document, Footer, LevelFormat, Packer, PageNumber, Paragraph, TextRun } from 'docx';
import PDFDocument from 'pdfkit';

const INK = '172033';
const MUTED = '4B5563';
const RULE = '64748B';
const PAGE_WIDTH_POINTS = 595.28;
const PAGE_MARGIN_POINTS = 54;
const PAGE_BOTTOM_POINTS = 58;

export type ProfessionalDocumentProfile = {
  fullName?: string | null;
  email?: string | null;
  phone?: string | null;
  addressLine1?: string | null;
  addressLine2?: string | null;
  city?: string | null;
  region?: string | null;
  postalCode?: string | null;
  country?: string | null;
  linkedinUrl?: string | null;
  portfolioUrl?: string | null;
};

export type ProfessionalDocumentContext = {
  kind: 'cv' | 'cover_letter';
  profile?: ProfessionalDocumentProfile | null;
  companyName?: string | null;
  jobTitle?: string | null;
  createdAt?: string | Date | null;
};

export type ProfessionalDocumentBlock = { kind: 'section' | 'subheading' | 'paragraph' | 'bullet' | 'salutation' | 'closing' | 'signature' | 'date' | 'addressee' | 'subject'; text: string };
export type ProfessionalDocumentLayout = { kind: 'cv' | 'cover_letter'; label: string; title: string; contactLines: string[]; blocks: ProfessionalDocumentBlock[]; footerLabel: string };

/** Converts typographic punctuation and Markdown decoration into portable document text without changing factual wording. */
function cleanDocumentText(value: string): string {
  return value.replace(/\u00a0/g, ' ').replace(/[\u2010-\u2015]/g, '-').replace(/[“”]/g, '"').replace(/[‘’]/g, "'").replace(/`([^`]+)`/g, '$1').replace(/\*\*([^*]+)\*\*/g, '$1').replace(/__([^_]+)__/g, '$1').trim();
}

/** Escapes a literal string before using it in a regular expression. */
function escapeRegExp(value: string): string { return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

/** Returns the canonical label used to recognise conventional CV section headings. */
function canonicalHeading(value: string): string { return cleanDocumentText(value).replace(/^#{1,6}\s*/, '').replace(/:$/, '').replace(/[^a-z0-9]+/gi, ' ').trim().toLowerCase(); }

/** Identifies conventional CV section headings without guessing that arbitrary short factual lines are headings. */
function isCvSectionHeading(value: string): boolean {
  const headings = new Set(['profile', 'professional profile', 'personal profile', 'summary', 'professional summary', 'career summary', 'objective', 'experience', 'professional experience', 'work experience', 'earlier experience', 'employment', 'employment history', 'career history', 'leadership', 'leadership and delivery', 'skills', 'key skills', 'core skills', 'technical skills', 'core competencies', 'education', 'education and qualifications', 'qualifications', 'certifications', 'certificates', 'achievements', 'selected achievements', 'projects', 'selected projects', 'training', 'professional development', 'publications', 'languages', 'volunteer experience', 'volunteering', 'interests', 'references', 'additional information']);
  return /^#{1,6}\s+/.test(value.trim()) || headings.has(canonicalHeading(value));
}

/** Identifies likely role, employer, or education lines when their date/delimiter structure is explicit. */
function isCvSubheading(value: string): boolean {
  const text = cleanDocumentText(value);
  return text.length <= 140 && !/[.!?]$/.test(text) && (text.includes('|') || /\b(?:19|20)\d{2}\b/.test(text));
}

/** Identifies a plausible name only when no stored profile name is available. */
function isLikelyName(value: string): boolean {
  const text = cleanDocumentText(value);
  const words = text.split(/\s+/);
  return text.length <= 60 && words.length >= 2 && words.length <= 5 && words.every((word) => /^[\p{L}][\p{L}'-]*$/u.test(word)) && !isCvSectionHeading(text);
}

/** Returns true when a top-of-document line contains only contact facts already represented in the structured header. */
function isRepeatedHeaderLine(value: string, profile: ProfessionalDocumentProfile | null | undefined): boolean {
  if (!profile) return false;
  const name = cleanDocumentText(profile.fullName || '').toLowerCase();
  const line = cleanDocumentText(value).toLowerCase();
  if (name && line === name) return true;
  const facts = [profile.email, profile.phone, profile.addressLine1, profile.addressLine2, profile.city, profile.region, profile.postalCode, profile.country, profile.linkedinUrl, profile.portfolioUrl].map((fact) => cleanDocumentText(fact || '').toLowerCase()).filter((fact) => fact.length >= 3);
  let remainder = line;
  let matched = false;
  for (const fact of facts.sort((left, right) => right.length - left.length)) {
    if (!remainder.includes(fact)) continue;
    remainder = remainder.replace(new RegExp(escapeRegExp(fact), 'g'), '');
    matched = true;
  }
  remainder = remainder.replace(/\b(?:email|e-mail|phone|telephone|tel|mobile|address|linkedin|portfolio|website)\b/gi, '').replace(/[|,;:/()\s-]+/g, '');
  return matched && !remainder;
}

/** Removes duplicate structured header facts only from the first few source lines. */
function withoutRepeatedHeader(text: string, profile: ProfessionalDocumentProfile | null | undefined): string[] {
  const lines = text.replace(/\r\n?/g, '\n').split('\n');
  let headerOpen = true;
  return lines.filter((line) => {
    if (!line.trim()) { headerOpen = false; return true; }
    if (!headerOpen) return true;
    if (isRepeatedHeaderLine(line, profile)) return false;
    headerOpen = false;
    return true;
  });
}

/** Produces compact, factual contact lines appropriate to the selected document type. */
function contactLines(profile: ProfessionalDocumentProfile | null | undefined, kind: 'cv' | 'cover_letter'): string[] {
  if (!profile) return [];
  const locality = [profile.city, profile.region, profile.postalCode, profile.country].map((part) => cleanDocumentText(part || '')).filter(Boolean).join(', ');
  const direct = [profile.email, profile.phone].map((part) => cleanDocumentText(part || '')).filter(Boolean).join(' | ');
  const links = [profile.linkedinUrl, profile.portfolioUrl].map((part) => cleanDocumentText(part || '')).filter(Boolean).join(' | ');
  if (kind === 'cv') return [direct, locality, links].filter(Boolean);
  const address = [profile.addressLine1, profile.addressLine2, locality].map((part) => cleanDocumentText(part || '')).filter(Boolean);
  return [...address, direct, links].filter(Boolean);
}

/** Parses structured CV text into restrained presentation blocks while retaining its factual text. */
function cvBlocks(lines: string[]): ProfessionalDocumentBlock[] {
  const blocks: ProfessionalDocumentBlock[] = [];
  for (const sourceLine of lines) {
    const trimmed = sourceLine.trim();
    if (!trimmed || /^[-_*]{3,}$/.test(trimmed)) continue;
    const bullet = trimmed.match(/^(?:[-*•])\s+(.+)$/);
    if (bullet) { blocks.push({ kind: 'bullet', text: cleanDocumentText(bullet[1]) }); continue; }
    if (isCvSectionHeading(trimmed)) { blocks.push({ kind: 'section', text: cleanDocumentText(trimmed.replace(/^#{1,6}\s*/, '').replace(/:$/, '')) }); continue; }
    const text = cleanDocumentText(trimmed);
    blocks.push({ kind: isCvSubheading(text) ? 'subheading' : 'paragraph', text });
  }
  return blocks;
}

/** Identifies conventional formal-letter closing phrases for signature spacing. */
function isLetterClosing(value: string): boolean { return /^(?:kind regards|best regards|regards|yours sincerely|yours faithfully|sincerely)[,.]?$/i.test(cleanDocumentText(value)); }

/** Parses a cover letter into paragraphs, salutations, closings, and signatures without inventing content. */
function letterBlocks(lines: string[]): ProfessionalDocumentBlock[] {
  const paragraphs = lines.join('\n').split(/\n\s*\n/).map((paragraph) => paragraph.split('\n').map(cleanDocumentText).filter(Boolean)).filter((paragraph) => paragraph.length);
  const blocks: ProfessionalDocumentBlock[] = [];
  for (const paragraphLines of paragraphs) {
    if (isLetterClosing(paragraphLines[0])) {
      blocks.push({ kind: 'closing', text: paragraphLines[0] });
      paragraphLines.slice(1).forEach((line) => blocks.push({ kind: 'signature', text: line }));
      continue;
    }
    const text = paragraphLines.join(' ');
    if (/^dear\b/i.test(text)) blocks.push({ kind: 'salutation', text });
    else if (blocks.at(-1)?.kind === 'closing') blocks.push({ kind: 'signature', text });
    else blocks.push({ kind: 'paragraph', text });
  }
  return blocks;
}

/** Formats a stored generation date for a formal letter without substituting an invented date. */
function formalDate(value: string | Date | null | undefined): string | null {
  if (!value) return null;
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(date);
}

/** Builds the single semantic layout consumed by both the Word and PDF renderers. */
export function buildProfessionalDocumentLayout(text: string, context: ProfessionalDocumentContext): ProfessionalDocumentLayout {
  const profile = context.profile;
  let lines = withoutRepeatedHeader(text, profile);
  let title = cleanDocumentText(profile?.fullName || '');
  if (!title && context.kind === 'cv') {
    const firstContentIndex = lines.findIndex((line) => line.trim());
    if (firstContentIndex >= 0 && isLikelyName(lines[firstContentIndex])) { title = cleanDocumentText(lines[firstContentIndex]); lines = lines.filter((_line, index) => index !== firstContentIndex); }
  }
  if (!title) title = context.kind === 'cv' ? 'Curriculum Vitae' : 'Cover Letter';
  const blocks = context.kind === 'cv' ? cvBlocks(lines) : letterBlocks(lines);
  if (context.kind === 'cover_letter') {
    const date = formalDate(context.createdAt);
    const metadata: ProfessionalDocumentBlock[] = [];
    if (date && !blocks.slice(0, 3).some((block) => block.text.includes(date))) metadata.push({ kind: 'date', text: date });
    if (context.companyName) metadata.push({ kind: 'addressee', text: cleanDocumentText(context.companyName) });
    if (context.jobTitle && !blocks.slice(0, 3).some((block) => /^(?:subject|re):/i.test(block.text))) metadata.push({ kind: 'subject', text: `Application for ${cleanDocumentText(context.jobTitle)}` });
    blocks.unshift(...metadata);
  }
  const label = context.kind === 'cv' ? 'CURRICULUM VITAE' : 'COVER LETTER';
  return { kind: context.kind, label, title, contactLines: contactLines(profile, context.kind), blocks, footerLabel: `${title} | ${context.kind === 'cv' ? 'CV' : 'Cover letter'}` };
}

/** Creates the consistent small document footer used in both file formats. */
function wordFooter(layout: ProfessionalDocumentLayout): Footer {
  return new Footer({ children: [new Paragraph({ alignment: AlignmentType.RIGHT, children: [new TextRun({ children: [`${layout.footerLabel} | Page `, PageNumber.CURRENT, ' of ', PageNumber.TOTAL_PAGES], font: 'Arial', size: 15, color: MUTED })] })] });
}

/** Converts one semantic block into its professionally styled DOCX paragraph. */
function wordBlock(block: ProfessionalDocumentBlock, kind: 'cv' | 'cover_letter', nextBlock?: ProfessionalDocumentBlock): Paragraph {
  const common = { widowControl: true, keepLines: true };
  if (block.kind === 'section') return new Paragraph({ ...common, keepNext: true, spacing: { before: 220, after: 90 }, border: { bottom: { style: BorderStyle.SINGLE, color: RULE, size: 5, space: 2 } }, children: [new TextRun({ text: block.text.toUpperCase(), bold: true, font: 'Arial', size: 20, color: INK, characterSpacing: 18 })] });
  if (block.kind === 'subheading') return new Paragraph({ ...common, keepNext: true, spacing: { before: 100, after: 35, line: 260 }, children: [new TextRun({ text: block.text, bold: true, font: 'Arial', size: 21, color: INK })] });
  if (block.kind === 'bullet') return new Paragraph({ ...common, keepNext: nextBlock?.kind === 'bullet', numbering: { reference: 'job-tune-bullets', level: 0 }, spacing: { after: 35, line: 270 }, children: [new TextRun({ text: block.text, font: 'Arial', size: 20, color: INK })] });
  if (block.kind === 'date') return new Paragraph({ ...common, keepNext: true, spacing: { before: 220, after: 100 }, children: [new TextRun({ text: block.text, font: 'Arial', size: 20, color: INK })] });
  if (block.kind === 'addressee') return new Paragraph({ ...common, keepNext: true, spacing: { after: 80 }, children: [new TextRun({ text: block.text, bold: true, font: 'Arial', size: 20, color: INK })] });
  if (block.kind === 'subject') return new Paragraph({ ...common, keepNext: true, spacing: { before: 160, after: 240 }, children: [new TextRun({ text: `Subject: ${block.text}`, bold: true, font: 'Arial', size: 21, color: INK })] });
  if (block.kind === 'salutation') return new Paragraph({ ...common, keepNext: true, spacing: { after: 180, line: 300 }, children: [new TextRun({ text: block.text, font: 'Arial', size: 21, color: INK })] });
  if (block.kind === 'closing') return new Paragraph({ ...common, keepNext: true, spacing: { before: 180, after: 120 }, children: [new TextRun({ text: block.text, font: 'Arial', size: 21, color: INK })] });
  if (block.kind === 'signature') return new Paragraph({ ...common, spacing: { after: 60 }, children: [new TextRun({ text: block.text, bold: true, font: 'Arial', size: 21, color: INK })] });
  return new Paragraph({ ...common, spacing: { after: kind === 'cv' ? 90 : 180, line: kind === 'cv' ? 276 : 300 }, children: [new TextRun({ text: block.text, font: 'Arial', size: kind === 'cv' ? 20 : 21, color: INK })] });
}

/** Renders a professional, editable Word document from the shared semantic layout. */
export async function professionalDocxBuffer(text: string, context: ProfessionalDocumentContext): Promise<Buffer> {
  const layout = buildProfessionalDocumentLayout(text, context);
  const children = [
    new Paragraph({ keepNext: true, spacing: { after: 55 }, children: [new TextRun({ text: layout.label, bold: true, font: 'Arial', size: 16, color: MUTED, characterSpacing: 30 })] }),
    new Paragraph({ keepNext: true, spacing: { after: 70 }, children: [new TextRun({ text: layout.title, bold: true, font: 'Arial', size: 42, color: INK })] }),
    ...layout.contactLines.map((line) => new Paragraph({ keepNext: true, spacing: { after: 25, line: 230 }, children: [new TextRun({ text: line, font: 'Arial', size: 18, color: MUTED })] })),
    new Paragraph({ keepNext: true, spacing: { before: layout.contactLines.length ? 70 : 20, after: 150 }, border: { bottom: { style: BorderStyle.SINGLE, color: RULE, size: 8, space: 1 } }, children: [] }),
    ...layout.blocks.map((block, index) => wordBlock(block, layout.kind, layout.blocks[index + 1])),
  ];
  const document = new Document({
    title: `${layout.title} - ${layout.kind === 'cv' ? 'CV' : 'Cover letter'}`,
    creator: 'Job Tune',
    description: 'Professional application document generated by Job Tune.',
    features: { updateFields: true },
    styles: { default: { document: { run: { font: 'Arial', size: 20, color: INK } } } },
    numbering: { config: [{ reference: 'job-tune-bullets', levels: [{ level: 0, format: LevelFormat.BULLET, text: '-', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 360, hanging: 180 } } } }] }] },
    sections: [{ properties: { page: { size: { width: 11906, height: 16838 }, margin: { top: 900, right: 1080, bottom: 900, left: 1080, header: 360, footer: 360, gutter: 0 } } }, footers: { default: wordFooter(layout) }, children }],
  });
  return Packer.toBuffer(document);
}

/** Adds a fresh PDF page when the next logical block would otherwise be orphaned. */
function ensurePdfSpace(pdf: PDFKit.PDFDocument, minimumHeight: number): void { if (pdf.y + minimumHeight > pdf.page.height - PAGE_BOTTOM_POINTS) pdf.addPage(); }

/** Estimates a compact section's first logical group so its heading and context remain on one PDF page. */
function pdfSectionGroupHeight(blocks: ProfessionalDocumentBlock[], sectionIndex: number): number {
  let height = 34;
  let sawSubheading = false;
  for (const block of blocks.slice(sectionIndex + 1)) {
    if (block.kind === 'section' || (block.kind === 'subheading' && sawSubheading)) break;
    if (block.kind === 'subheading') { sawSubheading = true; height += 25; }
    else if (block.kind === 'bullet') height += 24;
    else if (block.kind === 'paragraph') { height += 42; break; }
    else break;
    if (height >= 170) return 170;
  }
  return height;
}

/** Draws one semantic content block with the same hierarchy used by the DOCX renderer. */
function renderPdfBlock(pdf: PDFKit.PDFDocument, block: ProfessionalDocumentBlock, kind: 'cv' | 'cover_letter', sectionHeight = 70): void {
  const contentWidth = PAGE_WIDTH_POINTS - PAGE_MARGIN_POINTS * 2;
  pdf.x = PAGE_MARGIN_POINTS;
  pdf.fillColor(`#${INK}`);
  if (block.kind === 'section') {
    ensurePdfSpace(pdf, sectionHeight);
    pdf.moveDown(0.65).font('Helvetica-Bold').fontSize(10).text(block.text.toUpperCase(), { characterSpacing: 0.7, lineGap: 1 });
    const ruleY = pdf.y + 3;
    pdf.save().strokeColor(`#${RULE}`).lineWidth(0.6).moveTo(PAGE_MARGIN_POINTS, ruleY).lineTo(PAGE_WIDTH_POINTS - PAGE_MARGIN_POINTS, ruleY).stroke().restore();
    pdf.y = ruleY + 8;
    return;
  }
  if (block.kind === 'subheading') { ensurePdfSpace(pdf, 30); pdf.moveDown(0.2).font('Helvetica-Bold').fontSize(10.5).text(block.text, { lineGap: 2 }); pdf.moveDown(0.15); return; }
  if (block.kind === 'bullet') {
    ensurePdfSpace(pdf, 25);
    const textX = PAGE_MARGIN_POINTS + 15;
    const textWidth = contentWidth - 15;
    const bulletY = pdf.y;
    pdf.font('Helvetica').fontSize(10).fillColor(`#${INK}`).text('-', PAGE_MARGIN_POINTS + 1, bulletY, { width: 8, lineBreak: false });
    pdf.font('Helvetica').fontSize(10).fillColor(`#${INK}`).text(block.text, textX, bulletY, { width: textWidth, lineGap: 2.5 });
    pdf.moveDown(0.15);
    return;
  }
  if (block.kind === 'date') { ensurePdfSpace(pdf, 32); pdf.moveDown(0.8).font('Helvetica').fontSize(10.5).text(block.text, { lineGap: 3 }); pdf.moveDown(0.35); return; }
  if (block.kind === 'addressee') { ensurePdfSpace(pdf, 26); pdf.font('Helvetica-Bold').fontSize(10.5).text(block.text, { lineGap: 3 }); pdf.moveDown(0.35); return; }
  if (block.kind === 'subject') { ensurePdfSpace(pdf, 42); pdf.moveDown(0.5).font('Helvetica-Bold').fontSize(10.5).text(`Subject: ${block.text}`, { lineGap: 3 }); pdf.moveDown(0.8); return; }
  if (block.kind === 'salutation') { ensurePdfSpace(pdf, 42); pdf.font('Helvetica').fontSize(10.5).text(block.text, { lineGap: 4 }); pdf.moveDown(0.75); return; }
  if (block.kind === 'closing') { ensurePdfSpace(pdf, 48); pdf.moveDown(0.7).font('Helvetica').fontSize(10.5).text(block.text, { lineGap: 4 }); pdf.moveDown(0.7); return; }
  if (block.kind === 'signature') { ensurePdfSpace(pdf, 28); pdf.font('Helvetica-Bold').fontSize(10.5).text(block.text, { lineGap: 3 }); pdf.moveDown(0.25); return; }
  ensurePdfSpace(pdf, 34);
  pdf.font('Helvetica').fontSize(kind === 'cv' ? 10 : 10.5).text(block.text, { width: contentWidth, lineGap: kind === 'cv' ? 3 : 4 });
  pdf.moveDown(kind === 'cv' ? 0.35 : 0.75);
}

/** Adds restrained page-number footers after PDFKit has completed pagination. */
function addPdfFooters(pdf: PDFKit.PDFDocument, layout: ProfessionalDocumentLayout): void {
  const pages = pdf.bufferedPageRange();
  for (let index = 0; index < pages.count; index += 1) {
    pdf.switchToPage(pages.start + index);
    const bottomMargin = pdf.page.margins.bottom;
    pdf.page.margins.bottom = 0;
    pdf.save().font('Helvetica').fontSize(7.5).fillColor(`#${MUTED}`).text(`${layout.footerLabel} | Page ${index + 1} of ${pages.count}`, PAGE_MARGIN_POINTS, pdf.page.height - 34, { width: PAGE_WIDTH_POINTS - PAGE_MARGIN_POINTS * 2, align: 'right', lineBreak: false }).restore();
    pdf.page.margins.bottom = bottomMargin;
  }
}

/** Renders a printer-friendly tagged PDF from the same semantic layout as the Word document. */
export async function professionalPdfBuffer(text: string, context: ProfessionalDocumentContext): Promise<Buffer> {
  const layout = buildProfessionalDocumentLayout(text, context);
  const pdf = new PDFDocument({ size: 'A4', margins: { top: 45, right: PAGE_MARGIN_POINTS, bottom: PAGE_BOTTOM_POINTS, left: PAGE_MARGIN_POINTS }, bufferPages: true, tagged: true, lang: 'en-GB', displayTitle: true, pdfVersion: '1.7', info: { Title: `${layout.title} - ${layout.kind === 'cv' ? 'CV' : 'Cover letter'}`, Author: layout.title, Creator: 'Job Tune', Subject: 'Professional application document' } });
  const chunks: Buffer[] = [];
  const completed = new Promise<Buffer>((resolve, reject) => { pdf.on('data', (chunk: Buffer) => chunks.push(chunk)); pdf.on('end', () => resolve(Buffer.concat(chunks))); pdf.on('error', reject); });
  pdf.font('Helvetica-Bold').fontSize(8).fillColor(`#${MUTED}`).text(layout.label, { characterSpacing: 1.5 });
  pdf.moveDown(0.35).font('Helvetica-Bold').fontSize(21).fillColor(`#${INK}`).text(layout.title, { lineGap: 1 });
  if (layout.contactLines.length) {
    pdf.moveDown(0.3);
    for (const line of layout.contactLines) pdf.font('Helvetica').fontSize(9).fillColor(`#${MUTED}`).text(line, { lineGap: 1.5 });
  }
  const ruleY = pdf.y + 8;
  pdf.save().strokeColor(`#${RULE}`).lineWidth(0.8).moveTo(PAGE_MARGIN_POINTS, ruleY).lineTo(PAGE_WIDTH_POINTS - PAGE_MARGIN_POINTS, ruleY).stroke().restore();
  pdf.y = ruleY + 10;
  layout.blocks.forEach((block, index) => renderPdfBlock(pdf, block, layout.kind, block.kind === 'section' ? pdfSectionGroupHeight(layout.blocks, index) : 70));
  addPdfFooters(pdf, layout);
  pdf.end();
  return completed;
}
