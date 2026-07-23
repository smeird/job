import { appendFile, mkdir } from "node:fs/promises";
import { dirname } from "node:path";
import nodemailer from "nodemailer";
import type { AppConfig } from "./config.js";

export interface AuthChallengeNotification {
  action: "login" | "register";
  code: string;
  email: string;
  expiresAt: Date;
}

export interface AuthChallengeNotifier {
  sendChallenge(notification: AuthChallengeNotification): Promise<void>;
}

export class ChallengeMailer implements AuthChallengeNotifier {
  public constructor(private readonly config: AppConfig["mail"]) {}

  /** Deliver a short-lived authentication code through SMTP or the configured structured log fallback. */
  public async sendChallenge(notification: AuthChallengeNotification): Promise<void> {
    const subject = notification.action === "register" ? "Complete your Job Tune registration" : "Your Job Tune sign-in code";
    const text = [`Your Job Tune code is ${notification.code}.`, `It expires at ${notification.expiresAt.toISOString()}.`, "If you did not request this code, you can ignore this message."].join("\n\n");
    if (this.config.host !== "") {
      const transport = nodemailer.createTransport({
        auth: this.config.username === "" ? undefined : { pass: this.config.password, user: this.config.username },
        host: this.config.host,
        port: this.config.port,
        secure: this.config.tls && this.config.port === 465,
        ...(this.config.tls && this.config.port !== 465 ? { requireTLS: true } : {}),
      });
      await transport.sendMail({ from: this.config.from, subject, text, to: notification.email });
      return;
    }
    await mkdir(dirname(this.config.logPath), { recursive: true });
    await appendFile(this.config.logPath, `${JSON.stringify({ ...notification, expiresAt: notification.expiresAt.toISOString(), subject, text })}\n`, { encoding: "utf8", mode: 0o600 });
  }
}
