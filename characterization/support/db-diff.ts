type Dump = Record<string, Record<string, unknown>[]>;

export type DbDiff = Record<string, { removed: Record<string, unknown>[]; added: Record<string, unknown>[] }>;

/** Row-level diff between two DB dumps; tables without changes are omitted. A changed row shows as removed + added. */
export function diffDb(before: Dump, after: Dump): DbDiff {
  const diff: DbDiff = {};
  for (const table of new Set([...Object.keys(before), ...Object.keys(after)])) {
    const b = (before[table] ?? []).map((r) => JSON.stringify(r));
    const a = (after[table] ?? []).map((r) => JSON.stringify(r));
    const removed = b.filter((r) => !a.includes(r)).map((r) => JSON.parse(r));
    const added = a.filter((r) => !b.includes(r)).map((r) => JSON.parse(r));
    if (removed.length || added.length) diff[table] = { removed, added };
  }
  return diff;
}
