import { describe, expect, it } from 'vitest';
import { detectBlock, loginParams, needsLogin, probe, redact } from './probe.ts';

const LOGGED_OUT = '<html><body><div id="page-header"><a href="./ucp.php?mode=login" title="Login">Login</a></div></body></html>';
const LOGGED_IN = '<html><body><div id="page-header"><a href="./ucp.php?mode=logout" title="Logout">Logout [Mario]</a></div></body></html>';
const LOGIN_FORM = `<html><body><div id="page-header"><a title="Login">Login</a></div>
<form action="./ucp.php?mode=login" method="post" id="login">
  <input type="text" name="username" value=""><input type="password" name="password">
  <input type="hidden" name="redirect" value="./index.php?sid=abc&amp;x=1">
  <input type="hidden" name="redirect" value="index.php">
  <input type='hidden' name='creation_time' value='1700000000'>
  <input type="hidden" name="form_token" value="tok">
  <input type="submit" name="login" value="Login">
  <input type="checkbox" name="autologin">
</form></body></html>`;

/** Fake phpBB: the session cookie is set on a redirect, and pages are only visible with it. */
function fakeDdu(opts: { password?: string; block?: Response } = {}) {
  const calls: { url: string; method: string; cookie: string; body?: string }[] = [];
  const fetchFn = (async (input: string | URL | Request, init?: RequestInit) => {
    const url = String(input);
    const cookie = new Headers(init?.headers).get('cookie') ?? '';
    calls.push({ url, method: init?.method ?? 'GET', cookie, body: init?.body ? String(init.body) : undefined });
    if (opts.block) return opts.block.clone();
    if (url.includes('mode=login') && init?.method === 'POST') {
      const ok = new URLSearchParams(String(init.body)).get('password') === (opts.password ?? 'secret');
      return ok
        ? new Response(null, { status: 302, headers: { location: '/index.php', 'set-cookie': 'phpbb_sid=s1; path=/; HttpOnly' } })
        : new Response(LOGIN_FORM);
    }
    if (url.includes('mode=login')) return new Response(LOGIN_FORM);
    if (url.endsWith('/index.php')) return new Response(cookie.includes('phpbb_sid=s1') ? LOGGED_IN : LOGGED_OUT);
    return new Response(cookie.includes('phpbb_sid=s1') ? `${LOGGED_IN}<!-- ${url} -->` : LOGGED_OUT);
  }) as typeof fetch;
  return { fetchFn, calls };
}

describe('needsLogin', () => {
  it('detects the Login link in the page header', () => {
    expect(needsLogin(LOGGED_OUT)).toBe(true);
    expect(needsLogin(LOGGED_IN)).toBe(false);
  });

  it('ignores Login links outside the page header', () => {
    expect(needsLogin('<a title="Login">x</a><div id="page-header"></div>')).toBe(false);
  });
});

describe('loginParams', () => {
  it('sends credentials plus every hidden and submit input, keeping duplicates', () => {
    const p = loginParams(LOGIN_FORM, 'mario', 'secret');
    expect([...p]).toEqual([
      ['username', 'mario'],
      ['password', 'secret'],
      ['redirect', './index.php?sid=abc&x=1'],
      ['redirect', 'index.php'],
      ['creation_time', '1700000000'],
      ['form_token', 'tok'],
      ['login', 'Login'],
    ]);
  });
});

describe('detectBlock', () => {
  it.each([
    [403, '', 'HTTP 403'],
    [200, '<title>Just a moment...</title>', 'Cloudflare challenge'],
    [403, '<title>Just a moment...</title><script>window._cf_chl_opt={}</script>', 'Cloudflare challenge (HTTP 403)'],
    [200, '<div class="cf-turnstile"></div>', 'Cloudflare challenge'],
    [200, '<div class="g-recaptcha"></div>', 'captcha'],
    [200, LOGGED_IN, null],
    [200, `${LOGIN_FORM}<script src="/cdn-cgi/challenge-platform/scripts/jsd/main.js"></script>`, null],
  ])('status %i', (status, html, expected) => {
    expect(detectBlock(status, html)).toBe(expected);
  });
});

describe('probe', () => {
  it('logs in, keeps the cookie set on a redirect and fetches the pages', async () => {
    const { fetchFn, calls } = fakeDdu();
    const { report, pages } = await probe({ username: 'mario', password: 'secret', urls: ['https://ddu.test/board'], loginUrl: 'https://ddu.test/ucp.php?mode=login', fetch: fetchFn });

    expect(report).toMatchObject({ ok: true, loggedIn: true });
    expect(report.steps.map((s) => s.step)).toEqual(['login-form', 'login-post', 'page']);
    expect(report.steps[0].title).toBeNull();
    expect(calls.at(-1)!.cookie).toBe('phpbb_sid=s1');
    expect(pages.get('https://ddu.test/board')).toContain('https://ddu.test/board');
  });

  it('fetches pages as a guest without logging in', async () => {
    const { fetchFn, calls } = fakeDdu();
    const { report } = await probe({ username: '', password: '', urls: ['https://ddu.test/board'], anonymous: true, fetch: fetchFn });

    expect(report).toMatchObject({ ok: true, loggedIn: false });
    expect(report.steps).toMatchObject([{ step: 'page', needsLogin: true, blocked: null }]);
    expect(calls.map((c) => c.method)).toEqual(['GET']);
  });

  it('reports a failed login', async () => {
    const { fetchFn } = fakeDdu();
    const { report } = await probe({ username: 'mario', password: 'wrong', urls: ['https://ddu.test/board'], loginUrl: 'https://ddu.test/ucp.php?mode=login', fetch: fetchFn });

    expect(report).toMatchObject({ ok: false, loggedIn: false, error: 'Login failed: page still shows the Login link' });
  });

  it('reports a block before sending credentials', async () => {
    const { fetchFn, calls } = fakeDdu({ block: new Response('denied', { status: 403 }) });
    const { report } = await probe({ username: 'mario', password: 'secret', urls: [], loginUrl: 'https://ddu.test/ucp.php?mode=login', fetch: fetchFn });

    expect(report).toMatchObject({ ok: false, error: 'Blocked on login form: HTTP 403' });
    expect(calls).toHaveLength(1);
  });

  it('reports network errors', async () => {
    const fetchFn = (async () => {
      throw new TypeError('fetch failed');
    }) as typeof fetch;
    const { report } = await probe({ username: 'm', password: 's', urls: [], fetch: fetchFn });

    expect(report).toMatchObject({ ok: false, error: 'TypeError: fetch failed' });
  });
});

describe('redact', () => {
  it('removes username, session ids, form tokens and e-mails', () => {
    const html = `${LOGIN_FORM}<a href="./viewtopic.php?t=1&amp;sid=0123abcd">x</a> Logout [Mario] mario@example.com`;
    const out = redact(html, 'mario');

    expect(out).not.toMatch(/mario/i);
    expect(out).not.toContain('0123abcd');
    expect(out).toContain('&amp;sid=REDACTED');
    expect(out).toContain('name="form_token" value="REDACTED"');
    expect(out).toContain("name='creation_time' value='REDACTED'");
    expect(out).toContain('Logout [fixture-user]');
  });
});
