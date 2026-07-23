import { createHash } from "node:crypto";
import { z } from "zod";

const environmentSchema = z.object({
  APP_COOKIE_DOMAIN: z.string().optional().default(""),
  APP_DEBUG: z.string().optional().default("false"),
  APP_ENV: z.enum(["development", "local", "test", "production"]).default("production"),
  APP_KEY: z.string().optional().default(""),
  APP_URL: z.url().default("https://job.smeird.com"),
  MAIL_LOG_PATH: z.string().default("/var/log/job/mail.log"),
  OPENAI_API_KEY: z.string().optional().default(""),
  OPENAI_BASE_URL: z.url().default("https://api.openai.com/v1"),
  OPENAI_MAX_TOKENS: z.coerce.number().int().min(500).max(128_000).default(8_000),
  OPENAI_MODEL_DRAFT: z.string().min(1).default("gpt-5.6-sol"),
  OPENAI_MODEL_PLAN: z.string().min(1).default("gpt-5.6-sol"),
  OPENAI_TARIFF_JSON: z.string().default("{}"),
  SMTP_FROM: z.string().default("no-reply@job.smeird.com"),
  SMTP_HOST: z.string().optional().default(""),
  SMTP_PASSWORD: z.string().optional().default(""),
  SMTP_PORT: z.coerce.number().int().positive().max(65_535).default(587),
  SMTP_TLS: z.string().optional().default("true"),
  SMTP_USERNAME: z.string().optional().default(""),
});

export interface AppConfig {
  app: {
    cookieDomain: string | undefined;
    csrfSecret: string;
    debug: boolean;
    environment: "development" | "test" | "production";
    url: URL;
  };
  mail: {
    from: string;
    host: string;
    logPath: string;
    password: string;
    port: number;
    tls: boolean;
    username: string;
  };
  openai: {
    apiKey: string;
    baseUrl: string;
    draftModel: string;
    maxOutputTokens: number;
    planModel: string;
    tariffJson: string;
  };
}

/** Parse a conventional environment boolean without accepting surprising truthy strings. */
function parseBoolean(value: string): boolean {
  return ["1", "true", "yes", "on"].includes(value.toLowerCase());
}

/** Load and validate application configuration, requiring cryptographic secrets in production. */
export function loadAppConfig(environment: NodeJS.ProcessEnv = process.env): AppConfig {
  const parsed = environmentSchema.parse(environment);
  const normalizedEnvironment = parsed.APP_ENV === "local" ? "development" : parsed.APP_ENV;
  const configuredSecret = parsed.APP_KEY || (normalizedEnvironment === "production" ? "" : "development-only-job-csrf-secret-change-me");
  const csrfSecret = normalizedEnvironment !== "production" && configuredSecret.length > 0 && configuredSecret.length < 32
    ? createHash("sha256").update(`job-development:${configuredSecret}`, "utf8").digest("hex")
    : configuredSecret;
  if (csrfSecret.length < 32) {
    throw new Error("APP_KEY must contain at least 32 characters for signed CSRF cookies.");
  }

  return {
    app: {
      cookieDomain: parsed.APP_COOKIE_DOMAIN === "" ? undefined : parsed.APP_COOKIE_DOMAIN,
      csrfSecret,
      debug: parseBoolean(parsed.APP_DEBUG),
      environment: normalizedEnvironment,
      url: new URL(parsed.APP_URL),
    },
    mail: {
      from: parsed.SMTP_FROM,
      host: parsed.SMTP_HOST,
      logPath: parsed.MAIL_LOG_PATH,
      password: parsed.SMTP_PASSWORD,
      port: parsed.SMTP_PORT,
      tls: parseBoolean(parsed.SMTP_TLS),
      username: parsed.SMTP_USERNAME,
    },
    openai: {
      apiKey: parsed.OPENAI_API_KEY,
      baseUrl: parsed.OPENAI_BASE_URL,
      draftModel: parsed.OPENAI_MODEL_DRAFT,
      maxOutputTokens: parsed.OPENAI_MAX_TOKENS,
      planModel: parsed.OPENAI_MODEL_PLAN,
      tariffJson: parsed.OPENAI_TARIFF_JSON,
    },
  };
}
