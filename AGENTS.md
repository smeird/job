# Project Development Guidelines

This repository must adhere to the following requirements:

1. The supported deployment foundation is Apache as the public proxy, a TypeScript application runtime, and a local MySQL database. Connection details are exposed via environment variables configured in Apache.
2. The MySQL schema must include a dedicated section (for example, a table) for site-wide settings.
3. The application is multi-user. Implement user accounts from the outset and strictly segregate all user content.
4. User authentication is required for user-owned resources.
5. The site should present a modern, welcoming post-login workspace.
6. All new or modified code must include inline documentation comments that explain the purpose of every function or method.

## Change Log
Record all subsequent changes to either the feature set or the look-and-feel requirements here.

- Replaced obsolete PHP, Tailwind, Tabulator, and Highcharts mandates with the current Apache, TypeScript, and MySQL foundation.
- Added a user-scoped application tracker, reusable document library, generated cover letters, contact profiles, selectable AI models, and optional document email delivery.
- Established a light-first professional interface with persistent dark mode and complete mobile workflows.
- Added searchable document management with custom names, recoverable trash, restore controls, and guarded permanent deletion.
