import * as SecureStore from 'expo-secure-store';
import * as SQLite from 'expo-sqlite';
import { Platform } from 'react-native';

import { EMPTY, movementKey, type LocalData } from './engine';

/**
 * Where the offline copy lives: SQLite on the phone (one table per kind, one JSON row per record, so a sale
 * rewrites a few rows, not the whole copy), localStorage on the web build. The token goes to the secure store.
 */

const TABLES = {
  customers: (x: LocalData['customers'][number]) => String(x.id),
  movements: (x: LocalData['movements'][number]) => movementKey(x),
  banks: (x: LocalData['banks'][number]) => String(x.id),
  queue: (x: LocalData['queue'][number]) => x.client_uuid,
} as const;
type Table = keyof typeof TABLES;
const META = ['me', 'cursor', 'lastSyncAt', 'cache'] as const;

const web = Platform.OS === 'web';
const WEB_KEY = 'bakery.local';
const TOKEN_KEY = 'bakery.token';

let db: SQLite.SQLiteDatabase | null = null;

async function open() {
  if (db) return db;
  db = await SQLite.openDatabaseAsync('bakery.db');
  await db.execAsync(`
    PRAGMA journal_mode = WAL;
    ${(Object.keys(TABLES) as Table[]).map((t) => `CREATE TABLE IF NOT EXISTS ${t} (key TEXT PRIMARY KEY NOT NULL, seq INTEGER NOT NULL, json TEXT NOT NULL);`).join('\n')}
    CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY NOT NULL, json TEXT NOT NULL);
  `);
  return db;
}

export async function load(): Promise<LocalData> {
  if (web) {
    try {
      const raw = globalThis.localStorage?.getItem(WEB_KEY);
      return raw ? { ...EMPTY, ...JSON.parse(raw) } : EMPTY;
    } catch {
      return EMPTY;
    }
  }
  const d = await open();
  const out: LocalData = { ...EMPTY };
  for (const t of Object.keys(TABLES) as Table[]) {
    const rows = await d.getAllAsync<{ json: string }>(`SELECT json FROM ${t} ORDER BY seq`);
    (out as unknown as Record<Table, unknown[]>)[t] = rows.map((r) => JSON.parse(r.json));
  }
  for (const r of await d.getAllAsync<{ key: string; json: string }>('SELECT key, json FROM meta')) {
    (out as unknown as Record<string, unknown>)[r.key] = JSON.parse(r.json);
  }
  out.customers.sort((a, b) => a.name.localeCompare(b.name, 'ar'));
  return out;
}

let seq = Date.now() * 1000;
let webTimer: ReturnType<typeof setTimeout> | null = null;

/** Writes what changed between two values: rows compared by identity (the engine never mutates a row). */
export async function save(prev: LocalData, next: LocalData): Promise<void> {
  if (web) {
    // shortcut: the web build keeps one JSON snapshot (localStorage, ~5 MB); it is the demo and screenshot target.
    if (webTimer) clearTimeout(webTimer);
    webTimer = setTimeout(() => {
      try {
        globalThis.localStorage?.setItem(WEB_KEY, JSON.stringify(next));
      } catch {}
    }, 300);
    return;
  }
  const d = await open();
  await d.withExclusiveTransactionAsync(async (tx) => {
    for (const t of Object.keys(TABLES) as Table[]) {
      const before = prev[t] as unknown[];
      const after = next[t] as unknown[];
      if (before === after) continue;
      const key = TABLES[t] as (x: unknown) => string;
      const old = new Map(before.map((x) => [key(x), x]));
      const keys = new Set<string>();
      for (const row of after) {
        const k = key(row);
        keys.add(k);
        // A new or changed row goes to the end of the load order (customers are sorted by name on load).
        if (old.get(k) !== row) await tx.runAsync(`INSERT OR REPLACE INTO ${t} (key, seq, json) VALUES (?, ?, ?)`, k, seq++, JSON.stringify(row));
      }
      for (const k of old.keys()) if (!keys.has(k)) await tx.runAsync(`DELETE FROM ${t} WHERE key = ?`, k);
    }
    for (const m of META) {
      if (prev[m] !== next[m]) await tx.runAsync('INSERT OR REPLACE INTO meta (key, json) VALUES (?, ?)', m, JSON.stringify(next[m]));
    }
  });
}

export async function getToken(): Promise<string | null> {
  if (web) return globalThis.localStorage?.getItem(TOKEN_KEY) ?? null;
  return SecureStore.getItemAsync(TOKEN_KEY);
}

export async function setToken(token: string | null): Promise<void> {
  if (web) {
    if (token) globalThis.localStorage?.setItem(TOKEN_KEY, token);
    else globalThis.localStorage?.removeItem(TOKEN_KEY);
    return;
  }
  if (token) await SecureStore.setItemAsync(TOKEN_KEY, token);
  else await SecureStore.deleteItemAsync(TOKEN_KEY);
}
