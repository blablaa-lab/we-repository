/**
 * Contrôle du site contre les recommandations du guide de démarrage SEO de Google.
 *
 *   node scripts/google-check.mjs --site https://staging.client.fr [--prod https://www.client.fr]
 *                                 [--in .we-finalise/front/report.json] [--links]
 *
 * Chaque constat porte une `resolution` qui dit quoi en faire :
 *   auto     → corrigeable par script ou par toi (metas, alt, canonical, robots.txt…)
 *   humain   → passe par le builder ou par un jugement visuel (structure, qualité d'image)
 *   question → il manque une information que seul le client a : pose la question, n'invente pas
 *
 * Ce script ne corrige rien : il constate. Les corrections passent par les scripts de la skill.
 */
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname } from 'node:path';

const argv = Object.fromEntries(process.argv.slice(2).reduce((acc, a, i, arr) => {
  if (a.startsWith('--')) acc.push([a.slice(2), arr[i + 1] && !arr[i + 1].startsWith('--') ? arr[i + 1] : true]);
  return acc;
}, []));
const IN = argv.in || '.we-finalise/front/report.json';
const OUT = argv.out || '.we-finalise/google-check.json';
const SITE = typeof argv.site === 'string' ? argv.site.replace(/\/$/, '') : null;
const PROD = typeof argv.prod === 'string' ? argv.prod.replace(/\/$/, '') : null;
if (!SITE) { console.error('✗ --site https://… est requis'); process.exit(1); }

let report;
try { report = JSON.parse(readFileSync(IN, 'utf8')); }
catch { console.error(`✗ ${IN} illisible. Lance d'abord scripts/front-audit.mjs.`); process.exit(1); }
const pages = (report.pages || []).filter(p => p.url);
if (!pages.length) { console.error('✗ aucune page dans le rapport front.'); process.exit(1); }

const findings = [];
const add = (severity, rule, resolution, where, detail, action) =>
  findings.push({ severity, rule, resolution, where, detail, action });

const host = u => { try { return new URL(u).host.replace(/^www\./, ''); } catch { return null; } };
const PROD_HOST = PROD ? host(PROD) : null;

// ---------------------------------------------------------------- 1. Découverte et indexation
const fetchText = async (url) => {
  try { const r = await fetch(url, { redirect: 'follow' }); return { status: r.status, body: await r.text() }; }
  catch (e) { return { status: 'error', body: '', error: String(e.message) }; }
};

const robots = await fetchText(`${SITE}/robots.txt`);
if (robots.status !== 200) {
  add('à corriger', 'robots.txt accessible', 'auto', '/robots.txt',
      `HTTP ${robots.status}`, 'Rank Math sert le robots.txt : vérifie le module et qu\'aucun fichier physique ne le masque');
} else {
  const lines = robots.body.split('\n').map(l => l.trim());
  if (lines.some(l => /^disallow:\s*\/\s*$/i.test(l))) {
    add('bloquant', 'Ne pas bloquer l\'exploration du site', 'auto', '/robots.txt',
        'Disallow: / — tout le site est interdit à l\'exploration',
        'Supprimer cette directive héritée de la phase de développement');
  }
  if (!/^sitemap:/im.test(robots.body)) {
    add('à corriger', 'Envoyer un sitemap', 'auto', '/robots.txt', 'aucune ligne Sitemap:',
        'Ajouter Sitemap: <url du sitemap Rank Math>');
  } else if (PROD_HOST) {
    for (const m of robots.body.matchAll(/^sitemap:\s*(\S+)/gim)) {
      if (host(m[1]) && host(m[1]) !== PROD_HOST) {
        add('à corriger', 'Sitemap sur le domaine de production', 'auto', '/robots.txt',
            `Sitemap: ${m[1]}`, `Remplacer par le domaine de production (${PROD_HOST})`);
      }
    }
  }
  // Google insiste : si le CSS et le JS sont bloqués, il ne comprend pas les pages.
  const blocked = lines.filter(l => /^disallow:\s*\/(wp-content|wp-includes|assets|themes|plugins)/i.test(l));
  if (blocked.length) {
    add('à corriger', 'Laisser Google accéder au CSS et au JavaScript', 'auto', '/robots.txt',
        blocked.join(' · '), 'Retirer ces Disallow : sans CSS ni JS, Google ne voit pas les pages comme un visiteur');
  }
}

// Le robots.txt peut déclarer le sitemap sur le domaine de prod : on teste le même chemin sur le
// site réellement audité.
const declared = (robots.body.match(/^sitemap:\s*(\S+)/im) || [])[1];
let sitemapPath = '/sitemap_index.xml';
if (declared) { try { sitemapPath = new URL(declared).pathname; } catch {} }
const sitemapUrl = `${SITE}${sitemapPath}`;
const sitemap = await fetchText(sitemapUrl);
if (sitemap.status !== 200) {
  add('à corriger', 'Envoyer un sitemap', 'auto', sitemapUrl, `HTTP ${sitemap.status}`,
      'Activer le module Sitemap de Rank Math et vérifier l\'URL');
} else if (!/<(urlset|sitemapindex)/i.test(sitemap.body)) {
  add('à corriger', 'Sitemap valide', 'auto', sitemapUrl, 'le fichier ne contient pas de <urlset> ni <sitemapindex>',
      'Vérifier le module Sitemap de Rank Math');
}

for (const p of pages) {
  if (/noindex/i.test(p.head?.robots || '')) {
    add('bloquant', 'Ne pas laisser de noindex sur une page à indexer', 'auto', p.slug || p.url,
        `meta robots = ${p.head.robots}`, 'Retirer le noindex si la page doit être indexée (Rank Math, réglages du contenu)');
  }
}

// ---------------------------------------------------------------- 2. Organisation et URL
const seenTitle = new Map(), seenDesc = new Map();
for (const p of pages) {
  let u; try { u = new URL(p.url); } catch { continue; }
  const path = u.pathname;

  if (/[?&](p|page_id|cat|attachment_id)=/.test(u.search)) {
    add('à corriger', 'URL descriptives', 'humain', p.slug || p.url, `URL à paramètre : ${u.search}`,
        'Activer des permaliens explicites (Réglages > Permaliens) et vérifier les redirections');
  }
  if (/\/[0-9a-f]{8,}\/?$/i.test(path) || /\/\d+\/?$/.test(path)) {
    add('à vérifier', 'URL descriptives', 'humain', p.slug || p.url, `segment non parlant : ${path}`,
        'Google recommande des mots utiles dans l\'URL plutôt qu\'un identifiant');
  }
  if (/_/.test(path)) {
    add('info', 'URL descriptives', 'humain', p.slug || p.url, 'underscores dans l\'URL',
        'Les tirets séparent mieux les mots ; ne pas changer une URL déjà en ligne sans redirection 301');
  }
  if (/[A-Z]/.test(path)) {
    add('à vérifier', 'URL descriptives', 'humain', p.slug || p.url, 'majuscules dans l\'URL',
        'Uniformiser en minuscules, avec redirection 301 si l\'URL est déjà connue');
  }

  // Canonique
  const canon = p.head?.canonical;
  if (!canon) {
    add('à corriger', 'Désigner la version canonique', 'auto', p.slug || p.url, 'aucun rel="canonical"',
        'Rank Math en pose un par défaut : vérifier que le module n\'est pas désactivé');
  } else {
    if (canon.startsWith('http://')) {
      add('à corriger', 'Canonique en HTTPS', 'auto', p.slug || p.url, canon, 'Canonique en http:// : forcer https');
    }
    if (PROD_HOST && host(canon) && host(canon) !== PROD_HOST && host(canon) === host(p.url)) {
      add('à corriger', 'Canonique sur le domaine de production', 'auto', p.slug || p.url, canon,
          `À la mise en ligne, la canonique devra pointer sur ${PROD_HOST} (search-replace global)`);
    }
    const norm = s => s.replace(/\/$/, '').split('?')[0];
    if (host(canon) === host(p.url) && norm(canon) !== norm(p.url)) {
      add('à vérifier', 'Canonique auto-référente', 'auto', p.slug || p.url,
          `canonique ${canon} ≠ URL de la page`, 'Volontaire (duplication assumée) ou erreur : trancher');
    }
  }

  // Duplication de title / description
  const ti = (p.head?.title || '').trim().toLowerCase();
  const de = (p.head?.description || '').trim().toLowerCase();
  if (ti) { seenTitle.set(ti, [...(seenTitle.get(ti) || []), p.slug || p.url]); }
  if (de) { seenDesc.set(de, [...(seenDesc.get(de) || []), p.slug || p.url]); }
}
for (const [val, slugs] of seenTitle) {
  if (slugs.length > 1) add('à corriger', 'Un title propre à chaque page', 'auto', slugs.join(', '),
      `title identique : « ${val.slice(0, 70)} »`, 'Rédiger un title distinct par page');
}
for (const [val, slugs] of seenDesc) {
  if (slugs.length > 1) add('à corriger', 'Une meta description propre à chaque page', 'auto', slugs.join(', '),
      `description identique : « ${val.slice(0, 70)} »`, 'Rédiger une description distincte par page');
}

// ---------------------------------------------------------------- 3. Title, description, titres
const GENERIQUES = /^(accueil|home|page d'accueil|bienvenue|sans titre|nouvelle page|untitled|mon site|mon blog)\b/i;
for (const p of pages) {
  const h = p.head || {};
  if (!h.title || !h.title.trim()) {
    add('bloquant', 'Un élément <title> sur chaque page', 'auto', p.slug || p.url, 'title absent',
        'Rédiger un title (meta title Rank Math)');
  } else {
    if (GENERIQUES.test(h.title.trim())) {
      add('à corriger', 'Titres clairs, concis et descriptifs', 'auto', p.slug || p.url,
          `title générique : « ${h.title} »`, 'Décrire le contenu de la page et nommer le site');
    }
    if (h.title_length > 60) {
      add('info', 'Titres concis', 'auto', p.slug || p.url, `${h.title_length} caractères`,
          'Google ne fixe pas de limite, mais au-delà de ~60 le titre est tronqué dans les résultats');
    }
  }
  if (!h.description || !h.description.trim()) {
    add('à corriger', 'Une meta description par page', 'auto', p.slug || p.url, 'description absente',
        'Rédiger 1 à 2 phrases reprenant les points les plus pertinents de la page');
  }
  if (h.keywords) {
    add('à corriger', 'Google n\'utilise pas la balise meta keywords', 'auto', p.slug || p.url,
        `meta keywords = « ${String(h.keywords).slice(0, 60)} »`, 'Supprimer : inutile, et signal de site mal entretenu');
  }
  // Titres : l'ordre n'affecte pas le classement, mais les lecteurs d'écran s'en servent.
  const hs = p.headings || [];
  const h1s = hs.filter(x => x.level === 1);
  if (!h1s.length) {
    add('à corriger', 'Structure de titres lisible', 'humain', p.slug || p.url, 'aucun H1',
        'Ajouter un H1 dans le builder — pour l\'accessibilité et la clarté, pas pour le classement');
  } else if (h1s.length > 1) {
    add('à vérifier', 'Structure de titres lisible', 'humain', p.slug || p.url, `${h1s.length} H1`,
        'Google tolère plusieurs H1 ; un seul reste plus clair pour les lecteurs d\'écran');
  }
  for (let i = 1; i < hs.length; i++) {
    if (hs[i].level - hs[i - 1].level > 1) {
      add('info', 'Ordre des titres (accessibilité)', 'humain', p.slug || p.url,
          `saut H${hs[i - 1].level} → H${hs[i].level} avant « ${hs[i].text.slice(0, 40)} »`,
          'Sans effet sur le classement selon Google, mais gêne la navigation au lecteur d\'écran');
      break;
    }
  }
  if ((p.text || '').replace(/\s+/g, ' ').trim().length < 200) {
    add('à vérifier', 'Contenu utile et suffisant', 'question', p.slug || p.url,
        `${(p.text || '').trim().length} caractères de texte`,
        'Google ne fixe aucune longueur minimale, mais une page presque vide n\'a rien à indexer : demander au client la matière manquante');
  }
}

// ---------------------------------------------------------------- 4. Liens
const ANCRES_VAGUES = /^(cliquez ici|clic(k)? ici|ici|en savoir plus|lire la suite|lire plus|voir plus|plus d'infos?|plus|détails|découvrir|read more|click here|link|lien|voir|télécharger|ce lien)\.?$/i;
const internal = new Set(), linkedTo = new Set(), stagingLinks = [];
for (const p of pages) {
  for (const l of p.links || []) {
    const text = (l.text || '').trim();
    if (!text && !/^(#|javascript:)/.test(l.href)) {
      add('info', 'Textes d\'ancrage descriptifs', 'humain', p.slug || p.url, `lien sans texte : ${l.href}`,
          'Un lien sans texte (icône seule) a besoin d\'un aria-label');
    } else if (ANCRES_VAGUES.test(text)) {
      add('à corriger', 'Textes d\'ancrage descriptifs', 'auto', p.slug || p.url,
          `ancre vague : « ${text} » → ${l.href}`, 'Décrire la destination : « Voir nos tarifs d\'ostéopathie » plutôt que « En savoir plus »');
    }
    if (/^#$|javascript:void/.test(l.href)) {
      add('à corriger', 'Pas de lien mort', 'humain', p.slug || p.url, `lien vide : ${l.href} (« ${text} »)`,
          'Renseigner la destination dans le builder, ou retirer le lien');
    }
    if (host(l.href) === host(p.url)) { internal.add(l.href.split('#')[0].replace(/\/$/, '')); linkedTo.add(l.href.split('#')[0].replace(/\/$/, '')); }
    if (PROD_HOST && host(l.href) && host(l.href) !== PROD_HOST && host(l.href) === host(SITE)) {
      stagingLinks.push({ page: p.slug || p.url, href: l.href });
    }
  }
}
if (stagingLinks.length) {
  const uniq = [...new Set(stagingLinks.map(s => s.page))];
  add('à corriger', 'Pas d\'URL de staging en dur', 'auto',
      `${uniq.length} page(s) : ${uniq.slice(0, 5).join(', ')}${uniq.length > 5 ? '…' : ''}`,
      `${stagingLinks.length} lien(s) absolu(s) vers ${host(SITE)}, ex. ${stagingLinks[0].href}`,
      'Un search-replace global à la mise en ligne les traite tous : à inscrire dans les vérifications de mise en ligne');
}

// Pages publiées qu'aucune autre page ne lie : Google les découvre mal.
for (const p of pages) {
  const key = p.url.split('#')[0].replace(/\/$/, '');
  if (!linkedTo.has(key) && p.slug !== 'accueil' && !/^https?:\/\/[^/]+\/?$/.test(p.url)) {
    add('à vérifier', 'Lier les pages entre elles', 'humain', p.slug || p.url,
        'aucun lien interne trouvé vers cette page',
        'Google découvre les pages par les liens : ajouter un lien depuis le menu ou une page pertinente');
  }
}

if (argv.links) {
  const checked = [...internal].slice(0, 300);
  for (const u of checked) {
    try {
      const r = await fetch(u, { method: 'HEAD', redirect: 'follow' });
      if (r.status >= 400) add('à corriger', 'Pas de lien cassé', 'humain', u, `HTTP ${r.status}`,
          'Corriger ou retirer le lien dans le builder');
    } catch { add('à vérifier', 'Pas de lien cassé', 'humain', u, 'requête impossible', 'Vérifier manuellement'); }
  }
}

// ---------------------------------------------------------------- 5. Images
const NOMS_NON_PARLANTS = /^(img|dsc|dscn|photo|image|capture|screenshot|sans-titre|untitled|final|copie|copy|download|unnamed|pexels|unsplash|istock|shutterstock|adobestock|fichier)[-_ ]?\d*$/i;
for (const p of pages) {
  for (const i of p.images || []) {
    const file = decodeURIComponent((i.src || '').split('/').pop().split('?')[0]).replace(/\.[a-z0-9]+$/i, '').replace(/-\d+x\d+$/, '');
    if (NOMS_NON_PARLANTS.test(file)) {
      add('info', 'Nommer les fichiers image de façon descriptive', 'auto', p.slug || p.url,
          `nom de fichier : ${file}`, 'Renommer à l\'import ; ne pas renommer un média déjà en ligne sans redirection');
    }
    // Google demande des images nettes : une image affichée plus grande que sa résolution est floue.
    if (i.natural?.w && i.rendered?.w && i.rendered.w > 50 && i.natural.w < i.rendered.w * 0.8) {
      add('à corriger', 'Images de haute qualité, claires et nettes', 'humain', p.slug || p.url,
          `${file} : source ${i.natural.w}px affichée sur ${i.rendered.w}px`,
          'Image étirée donc floue : fournir un fichier plus grand');
    }
    if (i.natural?.w && i.rendered?.w && i.rendered.w > 50 && i.natural.w > i.rendered.w * 3) {
      add('info', 'Images au bon format', 'humain', p.slug || p.url,
          `${file} : source ${i.natural.w}px affichée sur ${i.rendered.w}px`,
          'Poids inutile : redimensionner ou laisser le thème servir une taille intermédiaire');
    }
  }
}

// ---------------------------------------------------------------- 6. Mobile, HTTPS, langue
for (const p of pages) {
  if (!p.head?.viewport) {
    add('bloquant', 'Site adapté au mobile', 'humain', p.slug || p.url, 'aucune meta viewport',
        'Sans viewport, la page n\'est pas utilisable sur mobile — vérifier le thème/builder');
  }
  if ((p.head?.mixed_content || []).length) {
    add('à corriger', 'HTTPS sans contenu mixte', 'auto', p.slug || p.url,
        `${p.head.mixed_content.length} ressource(s) en http:// : ${p.head.mixed_content.slice(0, 3).join(', ')}`,
        'Le navigateur bloque ces ressources : search-replace http:// → https://');
  }
  if (!p.head?.lang) {
    add('à corriger', 'Langue de la page déclarée', 'auto', p.slug || p.url, 'attribut lang absent sur <html>',
        'Vérifier la langue du site dans WordPress (Réglages > Général)');
  }
  if (!p.url.startsWith('https://')) {
    add('bloquant', 'Servir le site en HTTPS', 'auto', p.slug || p.url, p.url, 'Passer le site en HTTPS');
  }
}

// ---------------------------------------------------------------- 7. Données structurées
const withSchema = pages.filter(p => (p.schema_types || []).length);
if (!withSchema.length) {
  add('à corriger', 'Données structurées valides', 'auto', 'tout le site', 'aucun JSON-LD détecté',
      'Activer le module Schema de Rank Math (étape 7 de la skill)');
} else {
  for (const p of pages) {
    if ((p.schema_types || []).includes('(JSON-LD illisible)')) {
      add('à corriger', 'Données structurées valides', 'auto', p.slug || p.url, 'un bloc JSON-LD ne se parse pas',
          'Un JSON-LD invalide est ignoré en bloc : trouver le plugin qui l\'émet');
    }
    const orgs = (p.schema_types || []).filter(t => /LocalBusiness|Organization|Physician|Dentist|Store|Restaurant|ProfessionalService/.test(t));
    if (orgs.length > 2) {
      add('à vérifier', 'Une seule entité d\'entreprise', 'auto', p.slug || p.url,
          `types d'entreprise multiples : ${orgs.join(', ')}`,
          'Deux graphes concurrents (thème + Rank Math ?) valent moins que zéro : n\'en garder qu\'un');
    }
  }
}

// ---------------------------------------------------------------- Sortie
const ORDER = { bloquant: 0, 'à corriger': 1, 'à vérifier': 2, info: 3 };
findings.sort((a, b) => ORDER[a.severity] - ORDER[b.severity] || a.rule.localeCompare(b.rule));
const out = {
  generated_at: new Date().toISOString(), site: SITE, prod: PROD, source: IN,
  pages: pages.length,
  counts: findings.reduce((a, f) => ({ ...a, [f.severity]: (a[f.severity] || 0) + 1 }), {}),
  questions: findings.filter(f => f.resolution === 'question'),
  findings,
};
mkdirSync(dirname(OUT), { recursive: true });
writeFileSync(OUT, JSON.stringify(out, null, 2));

console.log(`\n${pages.length} pages · ${findings.length} constats`);
for (const sev of ['bloquant', 'à corriger', 'à vérifier', 'info']) {
  const list = findings.filter(f => f.severity === sev);
  if (!list.length) continue;
  console.log(`\n=== ${sev.toUpperCase()} (${list.length}) ===`);
  const byRule = list.reduce((a, f) => { (a[f.rule] ||= []).push(f); return a; }, {});
  for (const [rule, fs] of Object.entries(byRule)) {
    console.log(`\n${rule} [${[...new Set(fs.map(f => f.resolution))].join('/')}]`);
    for (const f of fs.slice(0, 8)) console.log(`  · ${f.where} — ${f.detail}`);
    if (fs.length > 8) console.log(`  … et ${fs.length - 8} autre(s)`);
    console.log(`  → ${fs[0].action}`);
  }
}
if (out.questions.length) {
  console.log(`\n=== INFORMATIONS À DEMANDER AU CLIENT (${out.questions.length}) ===`);
  for (const q of out.questions) console.log(`  · ${q.where} : ${q.detail}`);
}
console.log(`\n→ ${OUT}`);
