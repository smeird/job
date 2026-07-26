import mysql, { Pool } from 'mysql2/promise';
import dotenv from 'dotenv';

dotenv.config();

/** Creates the single MySQL connection pool used by the web app and migration command. */
export function createDatabasePool(): Pool {
  return mysql.createPool({ host: process.env.DB_HOST, port: Number(process.env.DB_PORT || 3306), user: process.env.DB_USER, password: process.env.DB_PASSWORD, database: process.env.DB_NAME, waitForConnections: true, connectionLimit: 10, charset: 'utf8mb4', dateStrings: ['DATE'] });
}
