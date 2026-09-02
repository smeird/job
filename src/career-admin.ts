import { Pool, PoolConnection, RowDataPacket } from './db';

export type CareerRoleMergeMetadata = {
  location: string | null;
  startDateText: string | null;
  endDateText: string | null;
  isCurrent: boolean;
  summary: string | null;
  displayOrder: number;
};

export type CareerQuestionState = { status: 'open' | 'answered' | 'dismissed'; answeredFactId: number | null };

type CareerFactRow = RowDataPacket & { id: number; category: string; fact_hash: string; user_confirmed: number; archived_at: Date | null };
type CareerQuestionRow = RowDataPacket & { id: number; question_hash: string; rationale: string | null; status: 'open' | 'answered' | 'dismissed'; answered_fact_id: number | null };
type CareerRoleRow = RowDataPacket & { id: number; employer_name: string; job_title: string; location: string | null; start_date_text: string | null; end_date_text: string | null; is_current: number; summary: string | null; display_order: number };

/** Identifies expected ownership and validation failures from career administration operations. */
export class CareerRoleAdminError extends Error {
  /** Creates a typed error that an HTTP route can safely map to a client response. */
  constructor(public readonly code: 'not_found' | 'same_role' | 'invalid_direction') { super(code); }
}

/** Fills missing destination metadata from the source role without overwriting deliberate destination values. */
export function mergeCareerRoleMetadata(target: CareerRoleMergeMetadata, source: CareerRoleMergeMetadata): CareerRoleMergeMetadata {
  return {
    location: target.location || source.location,
    startDateText: target.startDateText || source.startDateText,
    endDateText: target.endDateText || source.endDateText,
    isCurrent: target.isCurrent || source.isCurrent,
    summary: target.summary || source.summary,
    displayOrder: Math.min(target.displayOrder, source.displayOrder),
  };
}

/** Chooses the question state that preserves an answer first and a deliberate dismissal second. */
export function preferredCareerQuestionState(target: CareerQuestionState, source: CareerQuestionState): CareerQuestionState {
  const rank = { open: 0, dismissed: 1, answered: 2 } as const;
  if (rank[source.status] > rank[target.status]) return source;
  if (target.status === 'answered' && !target.answeredFactId && source.answeredFactId) return source;
  return target;
}

/** Returns a complete stable role order after moving one role one place up or down. */
export function reorderedCareerRoleIds(ids: number[], roleId: number, direction: 'up' | 'down'): number[] {
  const next = [...ids];
  const index = next.indexOf(roleId);
  const destination = direction === 'up' ? index - 1 : index + 1;
  if (index < 0 || destination < 0 || destination >= next.length) return next;
  [next[index], next[destination]] = [next[destination], next[index]];
  return next;
}

/** Rewrites display order values inside an existing transaction so ordering remains deterministic. */
async function persistCareerRoleOrder(connection: PoolConnection, userId: number): Promise<void> {
  const [roles] = await connection.query<RowDataPacket[]>('SELECT id FROM career_roles WHERE user_id=? ORDER BY display_order,id FOR UPDATE', [userId]);
  for (const [index, role] of roles.entries()) await connection.query('UPDATE career_roles SET display_order=? WHERE id=? AND user_id=?', [index, role.id, userId]);
}

/** Atomically merges a source role into a user-selected destination without changing saved CV snapshots. */
export async function mergeCareerRoles(pool: Pool, userId: number, sourceRoleId: number, targetRoleId: number): Promise<{ targetRoleId: number; movedFacts: number; duplicateFacts: number; movedQuestions: number; duplicateQuestions: number }> {
  if (sourceRoleId === targetRoleId) throw new CareerRoleAdminError('same_role');
  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();
    const [roles] = await connection.query<CareerRoleRow[]>('SELECT * FROM career_roles WHERE user_id=? AND id IN (?,?) FOR UPDATE', [userId, sourceRoleId, targetRoleId]);
    const source = roles.find((role) => Number(role.id) === sourceRoleId);
    const target = roles.find((role) => Number(role.id) === targetRoleId);
    if (!source || !target) throw new CareerRoleAdminError('not_found');

    const metadata = mergeCareerRoleMetadata(
      { location: target.location, startDateText: target.start_date_text, endDateText: target.end_date_text, isCurrent: Boolean(target.is_current), summary: target.summary, displayOrder: target.display_order },
      { location: source.location, startDateText: source.start_date_text, endDateText: source.end_date_text, isCurrent: Boolean(source.is_current), summary: source.summary, displayOrder: source.display_order },
    );
    await connection.query('UPDATE career_roles SET location=?,start_date_text=?,end_date_text=?,is_current=?,summary=?,display_order=? WHERE id=? AND user_id=?', [metadata.location, metadata.startDateText, metadata.endDateText, metadata.isCurrent ? 1 : 0, metadata.summary, metadata.displayOrder, targetRoleId, userId]);

    const [[sourceFacts], [targetFacts]] = await Promise.all([
      connection.query<CareerFactRow[]>('SELECT id,category,fact_hash,user_confirmed,archived_at FROM career_facts WHERE user_id=? AND role_id=? FOR UPDATE', [userId, sourceRoleId]),
      connection.query<CareerFactRow[]>('SELECT id,category,fact_hash,user_confirmed,archived_at FROM career_facts WHERE user_id=? AND role_id=? FOR UPDATE', [userId, targetRoleId]),
    ]);
    const targetFactsByHash = new Map(targetFacts.map((fact) => [fact.fact_hash, fact]));
    let duplicateFacts = 0;
    for (const sourceFact of sourceFacts) {
      const targetFact = targetFactsByHash.get(sourceFact.fact_hash);
      if (!targetFact) {
        await connection.query('UPDATE career_facts SET role_id=? WHERE id=? AND user_id=?', [targetRoleId, sourceFact.id, userId]);
        continue;
      }
      duplicateFacts += 1;
      await connection.query('INSERT INTO career_fact_sources (user_id,fact_id,source_cv_id,source_document_name,source_excerpt,created_at) SELECT user_id,?,source_cv_id,source_document_name,source_excerpt,created_at FROM career_fact_sources WHERE user_id=? AND fact_id=? ON DUPLICATE KEY UPDATE source_document_name=VALUES(source_document_name),source_excerpt=VALUES(source_excerpt)', [targetFact.id, userId, sourceFact.id]);
      await connection.query('UPDATE career_questions SET answered_fact_id=? WHERE user_id=? AND answered_fact_id=?', [targetFact.id, userId, sourceFact.id]);
      const category = targetFact.category === 'other' && sourceFact.category !== 'other' ? sourceFact.category : targetFact.category;
      const archivedAt = !targetFact.archived_at || !sourceFact.archived_at ? null : targetFact.archived_at;
      await connection.query('UPDATE career_facts SET category=?,user_confirmed=?,archived_at=? WHERE id=? AND user_id=?', [category, targetFact.user_confirmed || sourceFact.user_confirmed ? 1 : 0, archivedAt, targetFact.id, userId]);
      await connection.query('DELETE FROM career_facts WHERE id=? AND user_id=?', [sourceFact.id, userId]);
    }

    const [[sourceQuestions], [targetQuestions]] = await Promise.all([
      connection.query<CareerQuestionRow[]>('SELECT id,question_hash,rationale,status,answered_fact_id FROM career_questions WHERE user_id=? AND role_id=? FOR UPDATE', [userId, sourceRoleId]),
      connection.query<CareerQuestionRow[]>('SELECT id,question_hash,rationale,status,answered_fact_id FROM career_questions WHERE user_id=? AND role_id=? FOR UPDATE', [userId, targetRoleId]),
    ]);
    const targetQuestionsByHash = new Map(targetQuestions.map((question) => [question.question_hash, question]));
    let duplicateQuestions = 0;
    for (const sourceQuestion of sourceQuestions) {
      const targetQuestion = targetQuestionsByHash.get(sourceQuestion.question_hash);
      if (!targetQuestion) {
        await connection.query('UPDATE career_questions SET role_id=? WHERE id=? AND user_id=?', [targetRoleId, sourceQuestion.id, userId]);
        continue;
      }
      duplicateQuestions += 1;
      const state = preferredCareerQuestionState(
        { status: targetQuestion.status, answeredFactId: targetQuestion.answered_fact_id },
        { status: sourceQuestion.status, answeredFactId: sourceQuestion.answered_fact_id },
      );
      await connection.query('UPDATE career_questions SET rationale=COALESCE(rationale,?),status=?,answered_fact_id=? WHERE id=? AND user_id=?', [sourceQuestion.rationale, state.status, state.answeredFactId, targetQuestion.id, userId]);
      await connection.query('DELETE FROM career_questions WHERE id=? AND user_id=?', [sourceQuestion.id, userId]);
    }

    await connection.query('INSERT INTO career_role_sources (user_id,role_id,source_cv_id,source_document_name,source_excerpt,created_at) SELECT user_id,?,source_cv_id,source_document_name,source_excerpt,created_at FROM career_role_sources WHERE user_id=? AND role_id=? ON DUPLICATE KEY UPDATE source_document_name=VALUES(source_document_name),source_excerpt=VALUES(source_excerpt)', [targetRoleId, userId, sourceRoleId]);
    await connection.query('DELETE FROM career_roles WHERE id=? AND user_id=?', [sourceRoleId, userId]);
    await persistCareerRoleOrder(connection, userId);
    await connection.commit();
    return { targetRoleId, movedFacts: sourceFacts.length - duplicateFacts, duplicateFacts, movedQuestions: sourceQuestions.length - duplicateQuestions, duplicateQuestions };
  } catch (error) {
    await connection.rollback();
    throw error;
  } finally {
    connection.release();
  }
}

/** Moves one owned role by one position while preserving a complete stable career order. */
export async function moveCareerRole(pool: Pool, userId: number, roleId: number, direction: 'up' | 'down'): Promise<void> {
  if (!['up', 'down'].includes(direction)) throw new CareerRoleAdminError('invalid_direction');
  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();
    const [roles] = await connection.query<RowDataPacket[]>('SELECT id FROM career_roles WHERE user_id=? ORDER BY display_order,id FOR UPDATE', [userId]);
    const ids = roles.map((role) => Number(role.id));
    if (!ids.includes(roleId)) throw new CareerRoleAdminError('not_found');
    const orderedIds = reorderedCareerRoleIds(ids, roleId, direction);
    for (const [index, id] of orderedIds.entries()) await connection.query('UPDATE career_roles SET display_order=? WHERE id=? AND user_id=?', [index, id, userId]);
    await connection.commit();
  } catch (error) {
    await connection.rollback();
    throw error;
  } finally {
    connection.release();
  }
}
