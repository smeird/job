# Project Development Guidelines

This repository must adhere to the following requirements:

1. The deployment environment provides a local MySQL database. Connection details are exposed via environment variables configured in the Apache configuration.
2. The MySQL database schema must include a dedicated section (e.g., table or namespace) for storing site-wide settings.
3. The application is a multi-user system. From the outset, implement user accounts and ensure content is segregated appropriately between users.
4. User authentication is required.
5. Tailwind CSS must be used to establish the site's look and feel.
6. Use Tabulator for any interactive or data-driven tables.
7. Use Highcharts for rendering graphs and chart visualizations.
8. The site should present a modern aesthetic, featuring a welcoming landing page with a hero component that appears after the login screen.
9. All new or modified code must include inline documentation comments that explain the purpose of every function or method.
10. All PHP code must remain fully compatible with PHP 7.4, avoiding language features introduced in later versions and setting Composer's platform requirement accordingly.

## Change Log
Record all subsequent changes to either the feature set or the look-and-feel requirements here.

- Added a technical requirement to maintain PHP 7.4 compatibility and configure Composer accordingly.
