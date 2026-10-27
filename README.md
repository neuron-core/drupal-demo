# Drupal Demo

A local Drupal 11 site running in Docker, pre-loaded with demo content.

| What | URL | Login |
|---|---|---|
| Drupal site | http://localhost:8080 | `admin` / `admin` |
| Drupal admin | http://localhost:8080/admin | |
| Adminer (database UI) | http://localhost:8081 | server `db`, user `drupal`, password `drupal`, database `drupal` |
| Mailpit (caught emails) | http://localhost:8025 | |
| **Concierge** (AI editorial assistant) | http://localhost:8080/admin/concierge | admin |
| **Intelligence** (AI site analyst) | http://localhost:8080/admin/intelligence | admin |
| Mercure hub (agent streaming) | http://localhost:8082 | anonymous |

## What's inside

- **Drupal core 11.4** (Composer project in `app/`), installed with the **Umami** demo
  profile: a food magazine with real articles, recipes, images, menus, and a Spanish translation.
- **Devel Generate** demo data on top of Umami:
  - 50 extra users (10 editors, 15 authors, 25 plain members)
  - 100 extra articles with up to 15 comments each, plus 10 basic pages
  - 30 extra tags
- **Comments enabled on articles** (Umami ships without them).
- **Fake site activity** (`scripts/demo-activity.php`, seeded so every rebuild is identical):
  - *Timeline*: content published over the last ~2 years, comments in the days after each
    article, and users who joined over 2 years with realistic last login/access times
    (active this week, a few months ago, long dormant, or never logged in). Articles are
    authored by editors/authors, and nobody posts before joining or after their last visit.
  - *Page views*: per-node total and today's views via the
    [Statistics](https://www.drupal.org/project/statistics) module. A "Popular content"
    block appears on `/articles` and `/recipes`. Real visits keep adding to the counts.
  - *System log*: 30 days of backdated entries: logins and logouts, failed logins (including
    a brute-force burst against `admin` about 9 days ago from `198.51.100.66`), content and
    comment activity, new registrations, 404s from scanners and stale links, 403s, and cron runs.
    Fake IPs come from the RFC 5737 documentation ranges.

Where to look in the admin:

| Data | Page |
|---|---|
| Users' "member for" / "last access" | People: `/admin/people` |
| System log | Reports → Recent log messages: `/admin/reports/dblog` |
| Top 404s / 403s | `/admin/reports/page-not-found`, `/admin/reports/access-denied` |
| Popular content | `/en/articles`, `/en/recipes` (bottom of page) |

## AI agents: Concierge and Intelligence

Two admin sections built with [Neuron AI](https://neuron-ai.dev) through the
[ai_neuron](https://www.drupal.org/project/ai_neuron) module, using Claude (`claude-opus-5-5`).

- **Concierge** finds, summarizes and reviews articles, edits them and drafts new ones.
  Saving an edit or a draft (`update_article`, `create_article`) is marked `requireApproval()`:
  the run pauses and the editor approves or rejects the change in the chat.
- **Intelligence** answers questions about the site's usage by querying the database, and
  answers with interactive metric cards, charts and sortable tables, plus a short insight.

**Add your API key** to `.env` (`ANTHROPIC_API_KEY=sk-ant-...`), then run `docker compose up -d`
to restart the containers with it. Until then, a chat turn ends with "The run failed.", and
`docker compose logs worker` says the key is missing.

### How a turn flows

```
Browser (Vue + CopilotKit)             Drupal                         worker container
MercureAgent ──POST /admin/neuron-demo/{agent}/run──▶ Symfony Messenger ──▶ AgentRunHandler
     ▲          (the AG-UI RunAgentInput)              (SQL queue)           builds the Neuron agent
     │                                                                       (ai_neuron), runs it with
     └──── EventSource ◀── Mercure hub ◀── MercureChannel + AGUIAdapter ◀─── conversation + run state
                                                                             in the Drupal database
```

The browser subscribes to the run's Mercure topic before posting the turn, then replays the
AG-UI events into CopilotKit. An approval ends the run with an AG-UI interrupt; the editor's
answer is posted as a new run with `resume`, and the worker resumes the suspended Neuron run.

### Where the code is (`app/web/modules/custom/neuron_demo`)

| File | What it does |
|---|---|
| `src/Plugin/NeuronAgent/*` | The two agents: instructions and tool list |
| `src/Plugin/NeuronTool/*` | The tools: articles (find, read, update, create), database query, metrics, chart, table |
| `src/Controller/ChatController.php` | The two admin pages, and the endpoint that queues a turn |
| `src/AgentRun.php`, `src/Messenger/AgentRunHandler.php` | The Messenger message, and the worker code that runs a turn |
| `src/MercureHubFactory.php`, `neuron_demo.services.yml` | The Mercure hub as a service (`HubInterface`), built from the environment |
| `src/EventSubscriber/ModelSettings.php` | Claude's `max_tokens` and `effort` (`medium`; `low` answers faster) |
| `neuron_demo.install`, `config/install/` | Points the AI module at Anthropic, with the key read from the environment |
| `ui/src/` | The chat: `MercureAgent.js` (AG-UI over Mercure), CopilotKit chat, cards, charts, approvals |

### Working on it

```bash
drush cr                                          # after changing PHP: the worker restarts with the new code
docker compose run --rm node npm run build        # after changing ui/src, then: drush cr
docker compose logs -f worker                     # see each turn being handled
docker compose exec --user www-data drupal dr messenger:failed:show   # failed turns
```

Conversations and suspended runs are stored by ai_neuron: see Configuration → AI → Neuron AI
(`/admin/config/ai/neuron/messages`).

**Conversations.** The open conversation is in the URL (`?thread=…`), so a page refresh reopens it,
including an approval still waiting for an answer. The bar above each chat lists the agent's latest
conversations (titled by their first message) and starts new ones. Behind it, `MercureAgent.connect()`
replays a conversation into CopilotKit from `GET /admin/neuron-demo/{agent}/threads/{thread}`, which
rebuilds it with Neuron's `AGUIAdapter::hydrate()`; `GET /admin/neuron-demo/{agent}/threads` lists them.

### Notes

- `app/patches/ai_provider_anthropic-streamed-tool-calls.patch` (applied by Composer) fixes the
  Anthropic provider dropping tool calls from streamed answers, which Neuron relies on.
- `settings.php` sets `$settings['ai_output']['full_trust_mode'] = TRUE`: otherwise the AI module
  re-encodes every tool argument as HTML (to filter links), and `uid > 0` reaches the SQL tool as `uid &gt; 0`.
- The Neuron agent skills live in `.agents/skills/`, linked into `vendor/`, so they follow
  Neuron updates; `.claude/skills/` points at them, next to the Drupal coding standards skill.

## Services (`docker-compose.yml`)

| Service | Image | Purpose |
|---|---|---|
| `drupal` | `drupal:11.4-php8.4-apache` (+ tweaks in `docker/`) | PHP + Apache, serves `app/web` |
| `db` | `mariadb:11.8` | Database, stored in the `db-data` volume |
| `adminer` | `adminer` | Browse the database |
| `mailpit` | `axllent/mailpit` | Catches every email Drupal sends |
| `worker` | same as `drupal` | Runs the AI agents in the background (`dr messenger:consume asynchronous`) |
| `mercure` | `dunglas/mercure:v1.0.4` | Pushes the agents' AG-UI events to the browser |
| `node` | `node:24-alpine` (profile `tools`) | Builds the chat UI, only when you run it |

Files created inside the container are owned by your host user, so you can edit
everything in `app/` directly.

## Everyday commands

```bash
docker compose up -d          # start
docker compose stop           # stop (data is kept)
docker compose logs -f drupal # watch web server logs

# Drush = Drupal's command-line tool
alias drush='docker compose exec --user www-data drupal drush'
drush status                  # site info
drush cr                      # clear caches (try this first when something looks wrong)
drush uli                     # one-time admin login link
drush user:password admin new-pass
drush pm:list --status=enabled

# Add a contrib module (e.g. Pathauto), then enable it
docker compose exec --user www-data drupal composer require drupal/pathauto
drush en pathauto -y

# More fake content
drush devel-generate:content 20 --bundles=article
```

## Rebuild from scratch

```bash
./scripts/setup.sh
```

This **wipes the database** and recreates everything, including the fake activity (it takes about 1 minute).
Code in `app/` is kept. Activity timestamps are relative to when you run it.
`scripts/demo-content.php` holds the small fixes that make the generated content work with Umami.

## Where things live (Drupal crash course)

- `app/composer.json`: the project's PHP dependencies (core, modules, Drush).
- `app/web/`: the document root. Don't edit `web/core/`.
- `app/web/modules/contrib/`: downloaded modules. Put your own in `app/web/modules/custom/`.
- `app/web/themes/`: themes. The front end uses **Umami**, the admin uses **Claro**.
- `app/web/sites/default/settings.php`: database connection and site settings.
- `app/web/sites/default/files/`: uploaded files and images.
- Content is made of **nodes** (articles, recipes, pages), classified with **taxonomy terms**.
  Article and recipe pages are laid out with **Layout Builder**.

> Local demo only: default passwords, no HTTPS. Don't expose it to the internet.
