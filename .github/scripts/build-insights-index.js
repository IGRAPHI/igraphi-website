#!/usr/bin/env node
/**
 * Reads all .md files in content/insights/, parses their frontmatter,
 * and regenerates:
 *   - content/insights/_list.json  (consumed by the SPA at runtime)
 *   - insights/rss.xml             (RSS feed)
 *
 * Runs automatically via GitHub Actions when any article is saved/published.
 * You can also run it locally: node .github/scripts/build-insights-index.js
 */

const fs   = require('fs');
const path = require('path');

const CONTENT_DIR = path.join(__dirname, '../../content/insights');
const LIST_OUT    = path.join(CONTENT_DIR, '_list.json');
const RSS_OUT     = path.join(__dirname, '../../insights/rss.xml');
const SITE_URL    = 'https://igraphi.com';

// ── Parse frontmatter ────────────────────────────────────────────────────────
function parseFrontmatter(raw) {
  const m = raw.match(/^---\n([\s\S]*?)\n---\n?([\s\S]*)$/);
  if (!m) return { meta: {}, body: raw };
  const meta = {};
  m[1].split('\n').forEach(line => {
    const idx = line.indexOf(':');
    if (idx < 0) return;
    const key = line.slice(0, idx).trim();
    let val = line.slice(idx + 1).trim().replace(/^["']|["']$/g, '');
    if (val === 'true')  val = true;
    if (val === 'false') val = false;
    meta[key] = val;
  });
  return { meta, body: m[2] };
}

// ── Read all articles ────────────────────────────────────────────────────────
const files = fs.readdirSync(CONTENT_DIR).filter(f => f.endsWith('.md'));
const articles = [];

for (const file of files) {
  const raw  = fs.readFileSync(path.join(CONTENT_DIR, file), 'utf8');
  const { meta } = parseFrontmatter(raw);
  if (!meta.slug) continue;
  articles.push({
    slug:        meta.slug,
    title:       meta.title       || '',
    category:    meta.category    || '',
    publishDate: meta.publishDate || '',
    readTime:    meta.readTime    || '',
    excerpt:     meta.excerpt     || '',
    isPublished: meta.isPublished === true || meta.isPublished === 'true',
  });
}

// Sort newest first
articles.sort((a, b) => new Date(b.publishDate) - new Date(a.publishDate));

// ── Write _list.json ─────────────────────────────────────────────────────────
fs.writeFileSync(LIST_OUT, JSON.stringify(articles, null, 2));
console.log(`_list.json → ${articles.length} articles`);

// ── Write RSS ────────────────────────────────────────────────────────────────
const published = articles.filter(a => a.isPublished);
const rssItems  = published.map(a => `
  <item>
    <title><![CDATA[${a.title}]]></title>
    <link>${SITE_URL}/insights/${a.slug}</link>
    <guid isPermaLink="true">${SITE_URL}/insights/${a.slug}</guid>
    <pubDate>${new Date(a.publishDate + 'T12:00:00').toUTCString()}</pubDate>
    <category><![CDATA[${a.category}]]></category>
    <description><![CDATA[${a.excerpt}]]></description>
  </item>`).join('');

const rss = `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>IGRAPHI Insights</title>
    <link>${SITE_URL}/insights</link>
    <description>Articles from IGRAPHI on visual communication, presentation design, and clarity for international organizations and mission-driven teams.</description>
    <language>en-us</language>
    <lastBuildDate>${new Date().toUTCString()}</lastBuildDate>
    <atom:link href="${SITE_URL}/insights/rss.xml" rel="self" type="application/rss+xml"/>
    ${rssItems}
  </channel>
</rss>`;

fs.mkdirSync(path.dirname(RSS_OUT), { recursive: true });
fs.writeFileSync(RSS_OUT, rss.trim());
console.log(`rss.xml → ${published.length} published articles`);
