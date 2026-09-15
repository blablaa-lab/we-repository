/**
 * Extrait les informations d'entreprise depuis le texte des pages capturé à l'étape 0.
 * Sert à remplir la fiche Local SEO de Rank Math avec les vraies données du site, et à repérer
 * les incohérences NAP (deux téléphones différents, deux adresses…).
 *
 *   node scripts/site-info.mjs [--in .we-finalise/pages.json] [--domain client.fr]
 *                              [--out .we-finalise/site-info.json]
 *
 * Ne devine rien : chaque valeur est rendue avec les pages où elle apparaît et son nombre
 * d'occurrences, à charge pour toi de trancher. Une valeur trouvée une seule fois, sur une seule
 * page, est un candidat — pas un fait.
 */
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname } from 'node:path';

const argv = Object.fromEntries(process.argv.slice(2).reduce((acc, a, i, arr) => {
  if (a.startsWith('--')) acc.push([a.slice(2), arr[i + 1] && !arr[i + 1].startsWith('--') ? arr[i + 1] : true]);
  return acc;
}, []));
const IN = argv.in || '.we-finalise/pages.json';
const OUT = argv.out || '.we-finalise/site-info.json';
const DOMAIN = typeof argv.domain === 'string' ? argv.domain.toLowerCase() : null;

let report;
try {
  report = JSON.parse(readFileSync(IN, 'utf8'));
} catch {
  console.error(`✗ ${IN} illisible. Produis-le d'abord avec scripts/php/page-text.php (étape 0) — ou passe --in .we-finalise/front/report.json si tu as lancé front-audit.mjs.`);
  process.exit(1);
}
const pages = (report.pages || []).filter(p => p.text && p.url);
if (!pages.length) { console.error(`✗ aucune page avec du texte dans ${IN}.`); process.exit(1); }

// Les pages légales et contact sont les sources fiables : le NAP y est écrit en clair.
const weight = slug => /mention|legal|cgv|cgu|confidentialit|contact|about|propos|equipe/i.test(slug || '') ? 3 : 1;

const JOURS = 'lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche';
const PATTERNS = {
  // Le TLD doit finir par des lettres : sinon une version de bibliothèque extraite d'un arbre
  // Breakdance (« gsap@3.13.0 ») passe pour une adresse et pollue la liste.
  email:      /[\w.+-]+@[\w-]+\.(?:[\w-]+\.)*[a-z]{2,}\b/gi,
  phone:      /(?:\+33|0033)\s?[1-9](?:[\s.\-]?\d{2}){4}|\b0[1-9](?:[\s.\-]?\d{2}){4}\b/g,
  postal_city:/\b(\d{5})\s+([A-ZÀ-ÖØ-Þ][\p{L}'’\-]+(?:[ \-][A-ZÀ-ÖØ-Þ\p{L}'’]+){0,3})/gu,
  street:     /\b\d{1,4}(?:\s?(?:bis|ter))?,?\s+(?:rue|avenue|av\.|boulevard|bd\.?|impasse|chemin|route|place|allée|allee|quai|cours|passage|square|lotissement|résidence|residence|z\.?a\.?c?|z\.?i\.?)\s+[^\n,;·|]{2,60}/gi,
  siret:      /\b\d{3}[\s.]?\d{3}[\s.]?\d{3}[\s.]?\d{5}\b/g,
  siren:      /\bSIREN\s*:?\s*(\d{3}[\s.]?\d{3}[\s.]?\d{3})\b/gi,
  vat:        /\bFR\s?\d{2}\s?\d{9}\b/gi,
  legal_form: /\b(SARL|SASU|SAS|EURL|SCI|SCOP|EIRL|EI|SNC|SA|Association|Micro-entreprise)\b[\s:]*([A-ZÀ-Þ][\w'’&.\-]*(?:\s+[\w'’&.\-]+){0,4})/g,
  capital:    /capital(?:\s+social)?\s+(?:de\s+)?([\d\s.,]{3,15})\s*(?:€|euros?)/gi,
  rcs:        /\bRCS\s+(?:de\s+)?([A-ZÀ-Þ][\p{L}\-]+(?:\s+[A-ZÀ-Þ][\p{L}\-]+)?)/gu,
  hours:      new RegExp(`(?:${JOURS})(?:\\s*(?:au?|[-–àa/,])\\s*(?:${JOURS}))?\\s*:?\\s*(?:de\\s*)?\\d{1,2}\\s*[h:]\\s*\\d{0,2}(?:\\s*(?:[-–à]|et|jusqu'à)\\s*\\d{1,2}\\s*[h:]\\s*\\d{0,2}){0,3}`, 'gi'),
};

// Groupe de capture porteur de l'information, quand le match entier contient l'étiquette.
const GROUP = { capital: 1, siren: 1, rcs: 1 };
// Mots qui signalent un faux positif : on est tombé sur l'étiquette voisine, pas sur la donnée.
const STOP = /^(t[ée]l[ée]phone|email|mail|siret|siren|tva|rcs|ape|naf|capital|si[èe]ge|adresse|horaires?|fax|eur|euros?)\b/i;
// L'étiquette suivante colle souvent à la valeur (« RCS Lyon Téléphone ») : on coupe avant.
const truncateAtStop = v => {
  const words = v.split(/\s+/);
  const cut = words.findIndex((w, i) => i > 0 && STOP.test(w));
  return (cut > 0 ? words.slice(0, cut) : words).join(' ').replace(/[,;:·|]+$/, '');
};
const TRUNCATE = new Set(['rcs', 'legal_form', 'street', 'postal_city']);

const REJECT = {
  // Un code postal français ne commence pas par 00 ; « 00019 SIREN » est la fin d'un SIRET.
  postal_city: v => /^00/.test(v) || STOP.test(v.replace(/^\d{5}\s+/, '')),
  rcs: v => STOP.test(v),
  street: v => STOP.test(v),
  legal_form: v => STOP.test(v.replace(/^\S+\s+/, '')),
};

const found = {};
const add = (kind, value, page) => {
  const v = value.replace(/\s+/g, ' ').trim();
  if (!v) return;
  const key = kind === 'phone' ? v.replace(/[\s.\-]/g, '') : v.toLowerCase();
  found[kind] ??= new Map();
  const e = found[kind].get(key) || { value: v, count: 0, score: 0, pages: [] };
  e.count += 1;
  e.score += weight(page.slug);
  if (!e.pages.includes(page.slug)) e.pages.push(page.slug);
  found[kind].set(key, e);
};

for (const page of pages) {
  for (const [kind, re] of Object.entries(PATTERNS)) {
    for (const m of page.text.matchAll(re)) {
      const raw = m[GROUP[kind] ?? 0];
      if (!raw) continue;
      let val = raw.replace(/\s+/g, ' ').trim();
      if (TRUNCATE.has(kind)) val = truncateAtStop(val);
      if (!val || REJECT[kind]?.(val)) continue;
      add(kind, val, page);
    }
  }
}

// Un numéro de SIRET contient un SIREN : ne pas rendre deux fois la même information.
if (found.siret && found.siren) {
  const sirets = [...found.siret.keys()].map(s => s.replace(/\D/g, ''));
  for (const [k, e] of found.siren) {
    if (sirets.some(s => s.startsWith(e.value.replace(/\D/g, '')))) found.siren.delete(k);
  }
}

const rank = kind => [...(found[kind]?.values() || [])].sort((a, b) => b.score - a.score || b.count - a.count);
const out = { source: IN, pages: pages.length, domain: DOMAIN, extracted: {}, conflicts: [], notes: [] };
for (const kind of Object.keys(PATTERNS)) out.extracted[kind] = rank(kind);

// Emails hors domaine : souvent une adresse d'agence ou d'hébergeur restée dans le pied de page.
if (DOMAIN) {
  const foreign = out.extracted.email.filter(e => !e.value.toLowerCase().endsWith('@' + DOMAIN));
  if (foreign.length) {
    out.notes.push({ kind: 'email_hors_domaine', values: foreign.map(e => e.value),
      note: `ces adresses ne sont pas sur ${DOMAIN} : vérifie qu'il ne s'agit pas de l'agence ou de l'hébergeur` });
  }
}
// Plusieurs valeurs concurrentes pour une donnée unique = incohérence NAP à trancher.
for (const kind of ['phone', 'street', 'postal_city', 'siret', 'vat']) {
  const vals = out.extracted[kind];
  if (vals.length > 1) {
    out.conflicts.push({ kind, values: vals.map(v => ({ value: v.value, pages: v.pages, count: v.count })),
      note: 'plusieurs valeurs distinctes trouvées : une seule doit subsister, à l\'identique partout (NAP)' });
  }
}
for (const kind of ['phone', 'street', 'postal_city']) {
  if (!out.extracted[kind].length) out.notes.push({ kind, note: 'introuvable dans le texte du site — à demander au client, ne pas inventer' });
}

mkdirSync(dirname(OUT), { recursive: true });
writeFileSync(OUT, JSON.stringify(out, null, 2));

const show = (label, kind, n = 3) => {
  const v = out.extracted[kind];
  if (!v.length) { console.log(`${label.padEnd(14)} — introuvable`); return; }
  console.log(`${label.padEnd(14)} ${v.slice(0, n).map(x => `${x.value} (${x.count}× · ${x.pages.slice(0, 3).join(', ')})`).join('\n' + ' '.repeat(15))}`);
};
console.log(`\n${pages.length} pages analysées · source ${IN}\n`);
show('Raison sociale', 'legal_form');
show('Téléphone', 'phone');
show('Email', 'email');
show('Rue', 'street');
show('CP + ville', 'postal_city');
show('SIRET', 'siret');
show('TVA', 'vat');
show('RCS', 'rcs');
show('Capital', 'capital');
show('Horaires', 'hours', 5);
if (out.conflicts.length) {
  console.log('\n⚠ incohérences à trancher :');
  for (const c of out.conflicts) console.log(`  ${c.kind} : ${c.values.map(v => v.value).join('  |  ')}`);
}
if (out.notes.length) {
  console.log('\nNotes :');
  for (const n of out.notes) console.log(`  ${n.kind} : ${n.note}${n.values ? ' → ' + n.values.join(', ') : ''}`);
}
console.log(`\n→ ${OUT}`);
