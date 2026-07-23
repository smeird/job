import type { Kysely } from "kysely";
import type { DatabaseSchema } from "../types.js";

export interface ContactDetails {
  address: string;
  email: string | null;
  phone: string | null;
}

export class ContactDetailsRepository {
  public constructor(private readonly database: Kysely<DatabaseSchema>) {}

  /** Load contact details by user id to preserve strict tenant isolation. */
  public async findForUser(userId: bigint): Promise<ContactDetails | null> {
    const row = await this.database
      .selectFrom("user_contact_details")
      .select(["address", "email", "phone"])
      .where("user_id", "=", userId)
      .executeTakeFirst();

    return row ?? null;
  }

  /** Upsert only the authenticated user's contact details. */
  public async saveForUser(userId: bigint, details: ContactDetails): Promise<void> {
    const now = new Date();
    await this.database
      .insertInto("user_contact_details")
      .values({
        address: details.address,
        created_at: now,
        email: details.email,
        phone: details.phone,
        updated_at: now,
        user_id: userId,
      })
      .onDuplicateKeyUpdate({
        address: details.address,
        email: details.email,
        phone: details.phone,
        updated_at: now,
      })
      .executeTakeFirstOrThrow();
  }
}
