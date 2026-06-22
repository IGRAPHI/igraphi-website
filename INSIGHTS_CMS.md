# IGRAPHI Insights — CMS Guide

## How it works

Articles are stored as Markdown files in `content/insights/`. The SPA fetches
`content/insights/_list.json` at runtime to build the index, then fetches each
`.md` file on demand when a reader opens an article. No build step required.

---

## File structure

```
content/insights/
  _list.json                          ← index manifest (auto-regenerated)
  annual-report-credibility.md
  executive-communication-architecture.md
  institutional-brand-design.md
  executive-ready-presentation.md
  confusing-slides-cost.md

images/insights/                      ← uploaded images go here
insights/rss.xml                      ← auto-regenerated RSS feed
admin/index.html                      ← Decap CMS login UI
admin/config.yml                      ← CMS field configuration
```

---

## Writing or editing an article

**Via CMS (recommended):**
1. Go to `https://igraphi.com/admin/`
2. Log in with your GitHub account
3. Click **Insights Articles → New Insight Article**
4. Fill in all fields, write the body in the Markdown editor
5. Set **Published** to ON when ready
6. Click **Publish** — this commits the file to GitHub

**Manually:**
1. Create or edit a `.md` file in `content/insights/`
2. Use the frontmatter format from any existing article
3. Commit to `main` — the GitHub Action will update `_list.json` and `rss.xml`

---

## Publishing / unpublishing

- Set `isPublished: true` in frontmatter to show the article on the site
- Set `isPublished: false` (or remove it) to hide it without deleting the file
- The GitHub Action automatically excludes unpublished articles from `_list.json`

---

## Adding a new article

1. In the CMS, click **New Insight Article**
2. Set a slug in lowercase-with-hyphens (e.g. `how-design-builds-trust`)
3. Write the article body using Markdown:
   - `## Heading` for subheadings (renders as styled uppercase label)
   - `**bold**` for emphasis
   - Regular paragraphs need no special syntax
4. Upload a featured image — it goes to `images/insights/`
5. Fill in SEO fields (title, description, OG image)
6. Toggle **Published** and click **Publish**

---

## Where images go

Images uploaded through the CMS go to `images/insights/` on the repo.
They are publicly accessible at `https://igraphi.com/images/insights/filename.jpg`.

---

## How the Insights page pulls articles

On page load (when a visitor navigates to `/insights`), the SPA:
1. Fetches `/content/insights/_list.json`
2. Filters for `isPublished: true`
3. Sorts by `publishDate` descending
4. Renders article cards dynamically

When a visitor opens an article (e.g. `/insights/annual-report-credibility`):
1. The SPA fetches `/content/insights/annual-report-credibility.md`
2. Parses frontmatter and renders the Markdown body via `marked.js`
3. Applies SEO meta tags from the frontmatter fields

---

## How the RSS feed is generated

The GitHub Action at `.github/workflows/rebuild-insights-index.yml` runs
whenever any `.md` file in `content/insights/` is committed. It:
1. Reads all article frontmatter
2. Regenerates `content/insights/_list.json`
3. Regenerates `insights/rss.xml`
4. Commits both files back to the repo with `[skip ci]` to prevent a loop

The RSS feed is available at: `https://igraphi.com/insights/rss.xml`

---

## One-time setup: authentication

This CMS uses Decap CMS with a GitHub backend and PKCE authentication.
No Netlify account or OAuth server needed.

### Steps:

1. **Push this project to GitHub** if it isn't there already

2. **Create a GitHub App** at `github.com/settings/apps/new`:
   - Name: IGRAPHI CMS (or anything)
   - Homepage URL: `https://igraphi.com`
   - Callback URL: `https://igraphi.com/admin/`
   - Uncheck "Active" under Webhook
   - Permissions: Repository contents → Read & write; Metadata → Read
   - Save, copy the **App ID**

3. **Install the GitHub App** on your repo:
   - From the App settings page → Install App → select the igraphi repo

4. **Edit `admin/config.yml`**:
   - Replace `owner/repo` with your GitHub repo path (e.g. `igraphi/igraphi-website`)
   - Replace `YOUR_GITHUB_APP_ID` with the App ID from step 2

5. **Deploy** — push to GitHub, Firebase picks up the changes

6. **Test** — go to `https://igraphi.com/admin/`, log in with GitHub

---

## Deployment

The site deploys to Firebase Hosting. Firebase serves static files directly —
`content/insights/*.md`, `content/insights/_list.json`, `admin/index.html`, and
`insights/rss.xml` are all served as-is. Clean article URLs
(`/insights/annual-report-credibility`) are handled by the SPA rewrite in
`firebase.json`.
