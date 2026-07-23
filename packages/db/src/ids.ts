/** Convert a driver-returned identifier into the bigint used inside the TypeScript domain. */
export function asBigInt(value: unknown, label = "identifier"): bigint {
  if (typeof value === "bigint") {
    return value;
  }

  if (typeof value === "number" && Number.isSafeInteger(value)) {
    return BigInt(value);
  }

  if (typeof value === "string" && /^\d+$/.test(value)) {
    return BigInt(value);
  }

  throw new TypeError(`${label} is not a valid unsigned bigint.`);
}

/** Serialize an internal bigint for JSON, HTML fields, and URL construction without precision loss. */
export function idToString(value: bigint): string {
  return value.toString(10);
}

/** Parse an untrusted route or form identifier and reject zero, signed, or non-decimal values. */
export function parsePublicId(value: string, label = "identifier"): bigint {
  if (!/^[1-9]\d*$/.test(value)) {
    throw new TypeError(`${label} must be a positive decimal identifier.`);
  }

  return BigInt(value);
}
