import { execFileSync } from 'node:child_process';

/**
 * The suite describes a site that has never been connected, so the state a previous run (or a demo seed) left behind is
 * cleared first. Runs against whatever WordPress wp-env started; skipped when WP_URL points somewhere else.
 */
export default function globalSetup() {
  if (process.env.WP_URL && !process.env.WP_URL.includes('localhost:8889')) return;
  for (const option of ['monoranks_connection', 'monoranks_insights', 'monoranks_change_log']) {
    try {
      execFileSync('npx', ['wp-env', 'run', 'cli', 'wp', 'option', 'delete', option], { stdio: 'ignore' });
    } catch {
      // The option was not there, which is the state the tests want anyway.
    }
  }
  try {
    execFileSync('npx', ['wp-env', 'run', 'cli', 'wp', 'user', 'meta', 'update', '1', 'locale', 'en_US'], { stdio: 'ignore' });
  } catch {
    // A site without that user still runs the suite in English.
  }
}
