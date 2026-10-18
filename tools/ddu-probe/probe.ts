// DDU connectivity probe: logs in the way legacy/DDUBrowser.php does and fetches the requested pages.
// Uses only fetch and Web APIs, so the same code runs on Node, AWS Lambda and Cloudflare Workers.

export const DDU_LOGIN_URL = 'https://ddunlimited.net/ucp.php?mode=login';

export type ProbeOptions = {
  username: string;
  password: string;
  /** Pages to fetch after login, in order. */
  urls: string[];
  loginUrl?: string;
  fetch?: typeof fetch;
  now?: () => number;
};

export type StepReport = {
  step: string;
  url: string;
  status: number;
  ms: number;
  bytes: number;
  needsLogin: boolean;
  blocked: string | null;
};

export type ProbeReport = {
  ok: boolean;
  loggedIn: boolean;
  steps: StepReport[];
  error?: string;
};

export type ProbeResult = { report: ProbeReport; pages: Map<string, string> };

/** Same check as DDUBrowser::needsLogin: a "Login" link inside div#page-header. */
export function needsLogin(html: string): boolean {
  const header = html.match(/<div[^>]*id=["']page-header["'][\s\S]*$/i)?.[0] ?? '';
  return /<a\b[^>]*title=["']Login["']/i.test(header);
}

/** Recognizes pages that are not DDU content: HTTP errors and common anti-bot interstitials. */
export function detectBlock(status: number, html: string): string | null {
  if (status === 403 || status === 429 || status === 503) return `HTTP ${status}`;
  if (/cf-chl-|challenge-platform|Just a moment\.\.\./i.test(html)) return 'Cloudflare challenge';
  if (/g-recaptcha|h-captcha|cf-turnstile/i.test(html)) return 'captcha';
  return null;
}

function attr(tag: string, name: string): string | null {
  const m = tag.match(new RegExp(`\\b${name}\\s*=\\s*(?:"([^"]*)"|'([^']*)'|([^\\s>]+))`, 'i'));
  return m ? (m[1] ?? m[2] ?? m[3] ?? '') : null;
}

function decodeEntities(s: string): string {
  return s.replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>');
}

/**
 * Same parameters as DDUBrowser::getLoginParams: username and password, then every hidden or submit input of
 * form#login. Inputs sharing a name are all sent, as legacy does by turning them into arrays.
 */
export function loginParams(html: string, username: string, password: string): URLSearchParams {
  const params = new URLSearchParams({ username, password });
  const form = html.match(/<form\b[^>]*\bid=["']login["'][^>]*>([\s\S]*?)<\/form>/i)?.[1] ?? '';
  for (const input of form.match(/<input\b[^>]*>/gi) ?? []) {
    const type = (attr(input, 'type') ?? '').toLowerCase();
    const name = attr(input, 'name');
    if ((type === 'hidden' || type === 'submit') && name) {
      params.append(name, decodeEntities(attr(input, 'value') ?? ''));
    }
  }
  return params;
}

/** Minimal single-host cookie jar: enough for a phpBB session. */
class CookieJar {
  private cookies = new Map<string, string>();

  store(res: Response): void {
    for (const c of res.headers.getSetCookie()) {
      const [pair] = c.split(';');
      const i = pair.indexOf('=');
      if (i > 0) this.cookies.set(pair.slice(0, i).trim(), pair.slice(i + 1).trim());
    }
  }

  header(): string {
    return [...this.cookies].map(([k, v]) => `${k}=${v}`).join('; ');
  }
}

export async function probe(opts: ProbeOptions): Promise<ProbeResult> {
  const doFetch = opts.fetch ?? fetch;
  const now = opts.now ?? Date.now;
  const loginUrl = opts.loginUrl ?? DDU_LOGIN_URL;
  const jar = new CookieJar();
  const steps: StepReport[] = [];
  const pages = new Map<string, string>();

  // Redirects are followed by hand so that cookies set on intermediate responses are kept.
  async function request(step: string, url: string, body?: URLSearchParams): Promise<string> {
    const start = now();
    let current = url;
    let res: Response;
    let init: RequestInit = { method: body ? 'POST' : 'GET', body, redirect: 'manual' };
    for (let hops = 0; ; hops++) {
      res = await doFetch(current, { ...init, headers: { cookie: jar.header(), 'user-agent': 'Mozilla/5.0 (TVScraper DDU probe)' } });
      jar.store(res);
      const location = res.headers.get('location');
      if (res.status < 300 || res.status >= 400 || !location || hops >= 5) break;
      current = new URL(location, current).toString();
      init = { method: 'GET', redirect: 'manual' };
    }
    const html = await res.text();
    steps.push({
      step,
      url,
      status: res.status,
      ms: now() - start,
      bytes: html.length,
      needsLogin: needsLogin(html),
      blocked: detectBlock(res.status, html),
    });
    return html;
  }

  const report = (extra: Partial<ProbeReport>): ProbeResult => ({
    report: { ok: false, loggedIn: false, steps, ...extra },
    pages,
  });

  try {
    const form = await request('login-form', loginUrl);
    if (steps.at(-1)!.blocked) return report({ error: `Blocked on login form: ${steps.at(-1)!.blocked}` });

    const afterLogin = await request('login-post', loginUrl, loginParams(form, opts.username, opts.password));
    if (steps.at(-1)!.blocked) return report({ error: `Blocked on login: ${steps.at(-1)!.blocked}` });
    if (needsLogin(afterLogin)) return report({ error: 'Login failed: page still shows the Login link' });

    for (const url of opts.urls) {
      const html = await request('page', url);
      pages.set(url, html);
      const last = steps.at(-1)!;
      if (last.blocked) return report({ loggedIn: true, error: `Blocked on ${url}: ${last.blocked}` });
      if (last.needsLogin) return report({ loggedIn: true, error: `Session lost on ${url}` });
    }
    return report({ ok: true, loggedIn: true });
  } catch (e) {
    return report({ error: e instanceof Error ? `${e.name}: ${e.message}` : String(e) });
  }
}

/** Removes account-identifying data from a captured page, keeping the markup the legacy parser relies on. */
export function redact(html: string, username: string): string {
  const escaped = username.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return html
    .replace(new RegExp(escaped, 'gi'), 'fixture-user')
    .replace(/([?&;]|&amp;)sid=[0-9a-f]+/gi, '$1sid=REDACTED')
    .replace(/(name=["'](?:form_token|creation_time|sid)["'][^>]*value=["'])[^"']*/gi, '$1REDACTED')
    .replace(/[\w.+-]+@[\w-]+\.[\w.-]+/g, 'redacted@example.invalid');
}
