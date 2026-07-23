import { z } from "zod";

export interface DatabaseConfig {
  database: string;
  host?: string;
  password: string;
  port?: number;
  socketPath?: string;
  user: string;
}

const databaseEnvironmentSchema = z.object({
  DB_DATABASE: z.string().min(1).default("job"),
  DB_DSN: z.string().optional().default(""),
  DB_HOST: z.string().min(1).default("127.0.0.1"),
  DB_PASSWORD: z.string().default(""),
  DB_PORT: z.coerce.number().int().positive().max(65_535).default(3306),
  DB_SOCKET: z.string().optional().default(""),
  DB_USERNAME: z.string().min(1).default("job"),
});

/** Parse a PHP-style MySQL DSN into the connection fields understood by mysql2. */
function parseMysqlDsn(dsn: string): Partial<DatabaseConfig> {
  if (!dsn.startsWith("mysql:")) {
    return {};
  }

  const entries = dsn
    .slice("mysql:".length)
    .split(";")
    .map((part) => part.split("=", 2))
    .filter((entry): entry is [string, string] => entry.length === 2 && entry[1] !== undefined);
  const values = Object.fromEntries(entries);
  const port = values.port === undefined ? undefined : Number(values.port);

  return {
    ...(values.dbname === undefined ? {} : { database: values.dbname }),
    ...(values.host === undefined ? {} : { host: values.host }),
    ...(port !== undefined && Number.isInteger(port) ? { port } : {}),
    ...(values.unix_socket === undefined ? {} : { socketPath: values.unix_socket }),
  };
}

/** Validate database environment values without mutating the process environment. */
export function loadDatabaseConfig(environment: NodeJS.ProcessEnv = process.env): DatabaseConfig {
  const parsed = databaseEnvironmentSchema.parse(environment);
  const dsn = parseMysqlDsn(parsed.DB_DSN);
  const socketPath = dsn.socketPath ?? (parsed.DB_SOCKET === "" ? undefined : parsed.DB_SOCKET);
  const host = socketPath === undefined ? (dsn.host ?? parsed.DB_HOST) : undefined;
  const port = socketPath === undefined ? (dsn.port ?? parsed.DB_PORT) : undefined;

  return {
    database: dsn.database ?? parsed.DB_DATABASE,
    ...(host === undefined ? {} : { host }),
    password: parsed.DB_PASSWORD,
    ...(port === undefined ? {} : { port }),
    ...(socketPath === undefined ? {} : { socketPath }),
    user: parsed.DB_USERNAME,
  };
}
