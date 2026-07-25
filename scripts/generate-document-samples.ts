import fs from 'node:fs/promises';
import path from 'node:path';
import { professionalDocxBuffer, professionalPdfBuffer, type ProfessionalDocumentContext } from '../src/documents';

const outputDirectory = path.join(process.cwd(), 'tmp', 'docs');
const profile = { fullName: 'Alex Morgan', email: 'alex.morgan@example.test', phone: '020 7946 0100', addressLine1: '10 Example Street', city: 'London', postalCode: 'SW1A 1AA', country: 'United Kingdom', linkedinUrl: 'https://www.linkedin.com/in/alex-morgan-test', portfolioUrl: 'https://portfolio.example.test' };
const cv = `Alex Morgan
alex.morgan@example.test | 020 7946 0100

PROFESSIONAL SUMMARY
Platform engineer with documented experience delivering TypeScript services, MySQL data platforms, and operational improvements for synthetic organisations.

CORE SKILLS
- TypeScript application development and service design
- MySQL schema design, migrations, indexing, and query analysis
- Apache reverse proxy configuration and Linux service operations
- Automated testing, code review, incident response, and technical documentation

PROFESSIONAL EXPERIENCE
Senior Platform Engineer | Example Systems Ltd | 2022-Present
- Led a documented migration of twelve internal services to a shared TypeScript platform while preserving existing customer workflows.
- Reduced synthetic deployment time from forty minutes to twelve minutes by introducing repeatable build and verification steps.
- Designed MySQL migration controls and rollback checks used by four product teams.
- Mentored six engineers through code reviews, architecture sessions, and incident exercises.

Software Engineer | Sample Digital plc | 2019-2022
- Built and maintained TypeScript APIs supporting account, document, and reporting workflows.
- Improved a documented reporting query from eighteen seconds to under two seconds through indexing and query redesign.
- Added integration tests for authentication, access control, and file-generation paths.
- Worked with delivery and support teams to translate verified user needs into incremental releases.

Junior Software Engineer | Demonstration Services | 2017-2019
- Developed internal tools using JavaScript, SQL, and standards-based web technologies.
- Documented deployment procedures and supported scheduled releases on Linux hosts.
- Investigated production incidents and recorded preventative follow-up actions.

EARLIER EXPERIENCE
Technical Support Analyst | Fictional Operations | 2015-2017
- Resolved documented application and data issues for internal users across three business units.
- Created concise knowledge articles that reduced repeated support requests.
- Escalated verified software defects with reproducible steps and supporting evidence.

LEADERSHIP AND DELIVERY
- Planned incremental releases with clear acceptance criteria, operational checks, and rollback steps.
- Facilitated technical reviews involving engineering, delivery, support, and information-security colleagues.
- Maintained decision records so future teams could understand the context behind important changes.
- Used synthetic fixtures to test document and data workflows without exposing personal information.

SELECTED PROJECTS
Document Workflow Modernisation | 2023
- Created a versioned document pipeline with clear audit records and recoverable user actions.
- Introduced repeatable PDF and Word output checks using synthetic test fixtures.

Database Reliability Programme | 2021
- Reviewed slow queries, defined service-level indicators, and delivered a prioritised remediation plan.
- Added migration verification that detected schema drift before application deployment.

Service Observability Review | 2020
- Defined a documented baseline for service health, error rates, latency, and deployment outcomes.
- Produced an evidence-based improvement backlog and supported teams through the first delivery phase.

EDUCATION AND QUALIFICATIONS
BSc Computer Science | Example University | 2017
- Modules included software engineering, databases, distributed systems, and information security.

Professional Certificate in Cloud Operations | Example Institute | 2020

PROFESSIONAL DEVELOPMENT
- Secure application design workshop, 2025
- Advanced MySQL performance course, 2024
- Technical leadership programme, 2023

ADDITIONAL INFORMATION
- Eligible to work in the United Kingdom.
- References available on request.`;
const letter = `Dear Hiring Team,

I am writing to apply for the Platform Engineer position at Example Industries. My CV documents practical experience delivering TypeScript services, MySQL data platforms, and reliable deployment workflows.

In my current synthetic role, I led a migration of twelve internal services to a shared TypeScript platform while preserving existing customer workflows. I also introduced repeatable build and verification steps that reduced the documented deployment time from forty minutes to twelve minutes.

The role's focus on dependable engineering and collaborative delivery aligns with my recorded experience in migration controls, automated testing, code review, and incident response. I would bring a methodical approach to improving systems while keeping changes understandable and supportable.

Thank you for considering my application. I would welcome the opportunity to discuss how my documented experience could support your team.

Kind regards,
Alex Morgan`;

/** Writes one synthetic document in both production output formats for visual QA. */
async function writeSample(baseName: string, text: string, context: ProfessionalDocumentContext): Promise<void> {
  const [docx, pdf] = await Promise.all([professionalDocxBuffer(text, context), professionalPdfBuffer(text, context)]);
  await Promise.all([fs.writeFile(path.join(outputDirectory, `${baseName}.docx`), docx), fs.writeFile(path.join(outputDirectory, `${baseName}.pdf`), pdf)]);
}

/** Generates non-personal samples that exercise multi-page CV and formal-letter layouts. */
async function generateSamples(): Promise<void> {
  await fs.mkdir(outputDirectory, { recursive: true });
  await Promise.all([
    writeSample('job-tune-synthetic-cv', cv, { kind: 'cv', profile, companyName: 'Example Industries', jobTitle: 'Platform Engineer', createdAt: new Date('2026-07-25T10:00:00Z') }),
    writeSample('job-tune-synthetic-cover-letter', letter, { kind: 'cover_letter', profile, companyName: 'Example Industries', jobTitle: 'Platform Engineer', createdAt: new Date('2026-07-25T10:00:00Z') }),
  ]);
  console.log(`Synthetic document samples written to ${outputDirectory}`);
}

generateSamples().catch((error: Error) => { console.error(error.message); process.exitCode = 1; });
