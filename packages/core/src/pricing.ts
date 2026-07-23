import { z } from "zod";

const tariffSchema = z.record(z.string(), z.object({
  completion: z.number().nonnegative(),
  prompt: z.number().nonnegative(),
}));

export interface UsageCost {
  available: boolean;
  precisePence: number | null;
  roundedPence: bigint;
}

/** Parse the environment tariff map expressed in pence per one thousand tokens. */
export function parseTariffs(json: string): Record<string, { completion: number; prompt: number }> {
  try {
    return tariffSchema.parse(JSON.parse(json) as unknown);
  } catch {
    return {};
  }
}

/** Calculate precise and rounded cost without reporting an unconfigured model as free. */
export function calculateUsageCost(
  model: string,
  promptTokens: number,
  completionTokens: number,
  tariffs: Record<string, { completion: number; prompt: number }>,
): UsageCost {
  const tariff = tariffs[model];
  if (tariff === undefined) {
    return { available: false, precisePence: null, roundedPence: 0n };
  }

  const precisePence = (promptTokens * tariff.prompt + completionTokens * tariff.completion) / 1_000;
  return { available: true, precisePence, roundedPence: BigInt(Math.round(precisePence)) };
}
