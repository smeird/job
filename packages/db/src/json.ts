/** Decode JSON columns consistently whether mysql2 returned a parsed object or a JSON string. */
export function decodeDatabaseJson(value: unknown): unknown {
  if (typeof value !== "string") {
    return value;
  }

  try {
    return JSON.parse(value) as unknown;
  } catch {
    return value;
  }
}

/** Encode structured values explicitly for MySQL JSON inserts and updates. */
export function encodeDatabaseJson(value: unknown): string {
  return JSON.stringify(value);
}
