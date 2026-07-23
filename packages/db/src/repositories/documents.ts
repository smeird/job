import type { Kysely } from "kysely";
import { asBigInt } from "../ids.js";
import type { DatabaseSchema } from "../types.js";

export interface StoredDocument {
  content: Buffer;
  createdAt: Date;
  documentType: string;
  filename: string;
  id: bigint;
  mimeType: string;
  sha256: string;
  sizeBytes: bigint;
  updatedAt: Date;
  userId: bigint;
}

export type DocumentSummary = Omit<StoredDocument, "content">;

/** Map a raw database document to the bigint-safe domain representation. */
function mapDocument(row: {
  content: Buffer;
  created_at: Date;
  document_type: string;
  filename: string;
  id: unknown;
  mime_type: string;
  sha256: string;
  size_bytes: unknown;
  updated_at: Date;
  user_id: unknown;
}): StoredDocument {
  return {
    content: row.content,
    createdAt: row.created_at,
    documentType: row.document_type,
    filename: row.filename,
    id: asBigInt(row.id, "document id"),
    mimeType: row.mime_type,
    sha256: row.sha256,
    sizeBytes: asBigInt(row.size_bytes, "document size"),
    updatedAt: row.updated_at,
    userId: asBigInt(row.user_id, "document user id"),
  };
}

export class DocumentsRepository {
  public constructor(private readonly database: Kysely<DatabaseSchema>) {}

  /** List document metadata belonging to one authenticated user. */
  public async listForUser(userId: bigint, documentType?: string): Promise<DocumentSummary[]> {
    let query = this.database
      .selectFrom("documents")
      .select(["id", "user_id", "document_type", "filename", "mime_type", "size_bytes", "sha256", "created_at", "updated_at"])
      .where("user_id", "=", userId);

    if (documentType !== undefined) {
      query = query.where("document_type", "=", documentType);
    }

    const rows = await query.orderBy("created_at", "desc").execute();
    return rows.map((row) => {
      const document = mapDocument({ ...row, content: Buffer.alloc(0) });
      return {
        createdAt: document.createdAt,
        documentType: document.documentType,
        filename: document.filename,
        id: document.id,
        mimeType: document.mimeType,
        sha256: document.sha256,
        sizeBytes: document.sizeBytes,
        updatedAt: document.updatedAt,
        userId: document.userId,
      };
    });
  }

  /** Load a document only when both its id and owning user match. */
  public async findOwned(id: bigint, userId: bigint): Promise<StoredDocument | null> {
    const row = await this.database
      .selectFrom("documents")
      .selectAll()
      .where("id", "=", id)
      .where("user_id", "=", userId)
      .executeTakeFirst();

    return row === undefined ? null : mapDocument(row);
  }

  /** Store a validated document BLOB and return the newly allocated identifier. */
  public async create(input: {
    content: Buffer;
    documentType: string;
    filename: string;
    mimeType: string;
    sha256: string;
    sizeBytes: bigint;
    userId: bigint;
  }): Promise<bigint> {
    const result = await this.database
      .insertInto("documents")
      .values({
        content: input.content,
        document_type: input.documentType,
        filename: input.filename,
        mime_type: input.mimeType,
        sha256: input.sha256,
        size_bytes: input.sizeBytes,
        user_id: input.userId,
      })
      .executeTakeFirstOrThrow();

    return asBigInt(result.insertId, "new document id");
  }

  /** Delete a document through an ownership-qualified predicate. */
  public async deleteOwned(id: bigint, userId: bigint): Promise<boolean> {
    const result = await this.database.deleteFrom("documents").where("id", "=", id).where("user_id", "=", userId).executeTakeFirst();
    return Number(result.numDeletedRows) === 1;
  }

  /** Compute a stable BLOB checksum in MySQL for migration and cutover verification. */
  public async contentChecksum(id: bigint, userId: bigint): Promise<string | null> {
    const row = await this.database
      .selectFrom("documents")
      .select("sha256")
      .where("id", "=", id)
      .where("user_id", "=", userId)
      .executeTakeFirst();
    return row?.sha256 ?? null;
  }
}
