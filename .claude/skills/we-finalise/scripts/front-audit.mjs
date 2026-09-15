#!/usr/bin/env node
/**
 * Audit front d'un site : responsive (débordement + screenshots), <img alt> vs médiathèque,
 * liens réseaux sociaux, favicon, title/meta description, texte de la page.
 *
 * Usage :
 *   node scripts/front-audit.mjs --urls .we-finalise/urls.json [--media .we-finalise/media.json] [--out .we-finalise/front]
 *        [--viewports 375,768,1024,1440] [--auth user:pass] [--only slug1,slug2]
 *
 * Prérequis (une fois, dans le projet) : npm i -D playwright && npx playwright install chromium
 * Sortie : <out>/report.json + <out>/shots/<slug>-<width>.png
 */
import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const argv = Object.fromEntries(process.argv.slice(2).reduce((acc, a, i, arr) => {
  if (a.startsWith('--')) acc.push([a.slice(2), arr[i + 1] && !arr[i + 1].startsWith('--') ? arr[i + 1] : true]);
  return acc;
}, []));

if (!argv.urls) { console.error('--urls requis'); process.exit(1); }
const OUT = argv.out || '.we-finalise/front';
const VIEWPORTS = String(argv.viewports || '375,768,1024,1440').split(',').map(Number);
const SOCIAL = /(facebook|instagram|linkedin|tiktok|youtube|twitter|x\.com|pinterest|threads\.net)/i;

const urls = JSON.parse(readFileSync(argv.urls, 'utf8')).filter(u => u.url && u.type !== '_meta');
const only = argv.only ? String(argv.only).split(',') : null;
const targets = only ? urls.filter(u => only.includes(u.slug)) : urls;

// Index médiathèque par "stem" de fichier (sans extension, sans suffixe -WxH) → alt.
const media = argv.media ? JSON.parse(readFileSync(argv.media, 'utf8')) : [];
const stem = (src) => decodeURIComponent(src.split('/').pop().split('?')[0]).replace(/\.[a-z0-9]+$/i, '').replace(/-\d+x\d+$/, '').replace(/-scaled$/, '');
const libAlt = new Map(media.map(m => [stem(m.file), { id: m.id, alt: m.alt }]));

mkdirSync(join(OUT, 'shots'), { recursive: true });
const browser = await chromium.launch();
const ctxOpts = {};
if (argv.auth) { const [username, password] = String(argv.auth).split(':'); ctxOpts.httpCredentials = { username, password }; }

const report = { generated_at: new Date().toISOString(), viewports: VIEWPORTS, pages: [] };

for (const t of targets) {
  const page = { id: t.id, type: t.type, slug: t.slug, url: t.url, overflow: {}, shots: {}, images: [], links: [], social_links: [], favicon: null, head: null, headings: [], schema_types: [], text: '', errors: [] };
  const context = await browser.newContext(ctxOpts);
  const p = await context.newPage();
  p.on('pageerror', e => page.errors.push(String(e.message)));
  try {
    for (const w of VIEWPORTS) {
      await p.setViewportSize({ width: w, height: 900 });
      await p.goto(t.url, { waitUntil: 'networkidle', timeout: 45000 });
      // Force le chargement des images lazy avant capture.
      await p.evaluate(async () => { window.scrollTo(0, document.body.scrollHeight); await new Promise(r => setTimeout(r, 400)); window.scrollTo(0, 0); });
      const ov = await p.evaluate(() => ({ scroll: document.documentElement.scrollWidth, inner: window.innerWidth }));
      page.overflow[w] = ov.scroll > ov.inner + 1 ? { overflow: true, by: ov.scroll - ov.inner } : { overflow: false };
      const shot = join(OUT, 'shots', `${t.slug || t.id}-${w}.png`);
      await p.screenshot({ path: shot, fullPage: true });
      page.shots[w] = shot;

      if (w === VIEWPORTS[VIEWPORTS.length - 1]) {
        // Collectes faites une fois, sur le plus grand viewport (header/footer complets).
        const data = await p.evaluate(() => {
          const zone = (el) => el.closest('header, [class*="header"], #header') ? 'header' : el.closest('footer, [class*="footer"], #footer') ? 'footer' : 'content';
          const imgs = [...document.querySelectorAll('img')].map(i => ({ src: i.currentSrc || i.src, alt: i.getAttribute('alt'), zone: zone(i), decorative: i.getAttribute('role') === 'presentation' || i.getAttribute('aria-hidden') === 'true',
            natural: { w: i.naturalWidth, h: i.naturalHeight }, rendered: { w: i.clientWidth, h: i.clientHeight }, loading: i.getAttribute('loading') }));
          const links = [...document.querySelectorAll('a[href]')].map(a => ({ href: a.href, text: (a.innerText || a.getAttribute('aria-label') || '').trim(), zone: zone(a), target: a.target }));
          const icon = document.querySelector('link[rel~="icon"], link[rel="shortcut icon"]');
          const md = document.querySelector('meta[name="description"]');
          const h1 = [...document.querySelectorAll('h1')].map(h => h.innerText.trim());
          const meta = n => { const el = document.querySelector(`meta[name="${n}"]`); return el ? el.content : null; };
          const headings = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')]
            .map(h => ({ level: Number(h.tagName[1]), text: h.innerText.trim().slice(0, 120) }));
          const canonical = document.querySelector('link[rel="canonical"]');
          const hreflang = [...document.querySelectorAll('link[rel="alternate"][hreflang]')]
            .map(l => ({ hreflang: l.getAttribute('hreflang'), href: l.href }));
          // Types de schema émis dans la page : sert à croiser avec l'étape 7.
          const schema_types = [...document.querySelectorAll('script[type="application/ld+json"]')].flatMap(s => {
            try {
              const walk = n => !n || typeof n !== 'object' ? []
                : [].concat(Array.isArray(n) ? n.flatMap(walk)
                    : [n['@type']].flat().filter(Boolean).concat(Object.values(n).flatMap(walk)));
              return walk(JSON.parse(s.textContent));
            } catch { return ['(JSON-LD illisible)']; }
          });
          // Ressources chargées en http:// sur une page https:// : contenu mixte, bloqué par le navigateur.
          const mixed = [...document.querySelectorAll('img[src^="http:"], script[src^="http:"], link[href^="http:"][rel="stylesheet"], iframe[src^="http:"]')]
            .map(el => el.getAttribute('src') || el.getAttribute('href')).slice(0, 20);
          return { imgs, links, icon: icon ? icon.href : null, title: document.title,
                   description: md ? md.content : null, h1, headings, schema_types, hreflang, mixed,
                   canonical: canonical ? canonical.href : null,
                   robots: meta('robots'), keywords: meta('keywords'), viewport: meta('viewport'),
                   lang: document.documentElement.getAttribute('lang'),
                   text: document.body.innerText };
        });
        page.images = data.imgs.map(i => {
          const lib = libAlt.get(stem(i.src));
          const mismatch = lib ? (i.alt ?? '') !== (lib.alt ?? '') : null;
          return { ...i, media_id: lib?.id ?? null, library_alt: lib?.alt ?? null, alt_mismatch: mismatch, alt_missing: !i.decorative && !(i.alt ?? '').trim() };
        });
        page.social_links = data.links.filter(l => SOCIAL.test(l.href));
        page.links = data.links;
        page.headings = data.headings;
        page.schema_types = [...new Set(data.schema_types)];
        page.head = { title: data.title, description: data.description, h1: data.h1,
                      title_length: data.title.length, description_length: data.description ? data.description.length : 0,
                      canonical: data.canonical, robots: data.robots, keywords: data.keywords,
                      viewport: data.viewport, lang: data.lang, hreflang: data.hreflang, mixed_content: data.mixed };
        page.text = data.text.replace(/\n{3,}/g, '\n\n').trim();
        let status = null;
        try { const r = await context.request.get(data.icon || new URL('/favicon.ico', t.url).href); status = r.status(); } catch { status = 'error'; }
        page.favicon = { href: data.icon, status };
      }
    }
  } catch (e) {
    page.errors.push(String(e.message));
  } finally {
    await context.close();
  }
  report.pages.push(page);
  const ovs = Object.entries(page.overflow).filter(([, v]) => v.overflow).map(([w]) => w);
  console.log(`${t.slug || t.id}: ${ovs.length ? 'DÉBORDEMENT @' + ovs.join(',') : 'ok'} · ${page.images.filter(i => i.alt_missing).length} img sans alt · ${page.images.filter(i => i.alt_mismatch).length} alt ≠ médiathèque · ${page.social_links.length} liens sociaux`);
}
await browser.close();

// Synthèse
report.summary = {
  pages: report.pages.length,
  overflow_pages: report.pages.filter(p => Object.values(p.overflow).some(v => v.overflow)).map(p => p.slug),
  images_missing_alt: report.pages.reduce((n, p) => n + p.images.filter(i => i.alt_missing).length, 0),
  images_alt_mismatch: report.pages.reduce((n, p) => n + p.images.filter(i => i.alt_mismatch).length, 0),
  social_targets: [...new Set(report.pages.flatMap(p => p.social_links.map(l => l.href)))],
  favicon_ok: report.pages.some(p => p.favicon && p.favicon.status === 200),
  pages_without_meta_description: report.pages.filter(p => p.head && !p.head.description).map(p => p.slug),
  pages_noindex: report.pages.filter(p => /noindex/i.test(p.head?.robots || '')).map(p => p.slug),
  pages_mixed_content: report.pages.filter(p => (p.head?.mixed_content || []).length).map(p => p.slug),
  errors: report.pages.filter(p => p.errors.length).map(p => ({ slug: p.slug, errors: p.errors })),
};
writeFileSync(join(OUT, 'report.json'), JSON.stringify(report, null, 2));
console.log(`\n→ ${join(OUT, 'report.json')} · screenshots dans ${join(OUT, 'shots')}`);
