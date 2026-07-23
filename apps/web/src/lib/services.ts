import { loadAppConfig, AuthenticationService, ChallengeMailer } from "@job/core";
import {
  ApplicationsRepository,
  AuditRepository,
  AuthRepository,
  ContactDetailsRepository,
  DocumentsRepository,
  GenerationsRepository,
  SettingsRepository,
  UsageRepository,
  getDatabase,
  loadDatabaseConfig,
} from "@job/db";

/** Return validated application configuration for the current server process. */
export function appConfig(): ReturnType<typeof loadAppConfig> {
  return loadAppConfig();
}

/** Return the shared Kysely pool used by all web request repositories. */
export function database(): ReturnType<typeof getDatabase> {
  return getDatabase(loadDatabaseConfig());
}

/** Build authentication services over the shared repository and audit log. */
export function authenticationService(): AuthenticationService {
  const db = database();
  const config = appConfig();
  return new AuthenticationService(new AuthRepository(db), new AuditRepository(db), new ChallengeMailer(config.mail));
}

/** Build all request-scoped repositories over the shared database pool. */
export function repositories(): {
  applications: ApplicationsRepository;
  audit: AuditRepository;
  auth: AuthRepository;
  contactDetails: ContactDetailsRepository;
  documents: DocumentsRepository;
  generations: GenerationsRepository;
  settings: SettingsRepository;
  usage: UsageRepository;
} {
  const db = database();
  return {
    applications: new ApplicationsRepository(db),
    audit: new AuditRepository(db),
    auth: new AuthRepository(db),
    contactDetails: new ContactDetailsRepository(db),
    documents: new DocumentsRepository(db),
    generations: new GenerationsRepository(db),
    settings: new SettingsRepository(db),
    usage: new UsageRepository(db),
  };
}
