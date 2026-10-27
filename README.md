# Drupal Demo

A Drupal 11 site running in Docker, loaded with the Umami demo content (a food magazine), fake site activity,
and two AI agents in the admin panel built with [Neuron AI](https://neuron-ai.dev).

## Install and run locally

You need Docker with Docker Compose, and an [Anthropic API key](https://console.anthropic.com/) for the agents.

**1. Create the `.env` file.** It holds the local settings and isn't committed. Run this from the project root:
it writes the database and port settings, and generates a secret for the Mercure hub plus a publisher token
signed with it.

```bash
b64() { openssl base64 -A | tr '+/' '-_' | tr -d '='; }
secret=$(openssl rand -hex 32); now=$(date +%s)
header=$(printf '{"alg":"HS256","typ":"at+jwt"}' | b64)
payload=$(printf '{"iss":"drupal-demo","aud":"http://mercure/.well-known/mercure","sub":"drupal","client_id":"drupal","iat":%d,"exp":%d,"jti":"%s","authorization_details":[{"type":"https://mercure.rocks/authorization-detail","actions":["publish"],"topics":[{"match":"*"}]}]}' \
  "$now" $((now + 315360000)) "$(openssl rand -hex 16)" | b64)
sig=$(printf '%s.%s' "$header" "$payload" | openssl dgst -sha256 -hmac "$secret" -binary | b64)
cat > .env <<EOF
COMPOSE_PROJECT_NAME=drupal-demo
DB_NAME=drupal
DB_USER=drupal
DB_PASSWORD=drupal
DB_ROOT_PASSWORD=root
DRUPAL_PORT=8080
ADMINER_PORT=8081
MAILPIT_PORT=8025
MERCURE_PORT=8082
MERCURE_JWT_SECRET=$secret
MERCURE_PUBLISHER_JWT=$header.$payload.$sig
ANTHROPIC_API_KEY=
EOF
```

**2. Add your API key** to the last line of `.env`: `ANTHROPIC_API_KEY=sk-ant-...`

**3. Build the site:**

```bash
./scripts/setup.sh
```

This builds the containers, installs the PHP dependencies, installs Drupal with the demo content and the fake
activity, builds the chat UI and starts the background worker. It takes a few minutes the first time. Run it
again whenever you want a fresh site: it **wipes the database**, but keeps the code in `app/`.

**4. Open the site:**

| What | URL | Login |
|---|---|---|
| Drupal site | http://localhost:8080 | `admin` / `admin` |
| Concierge | http://localhost:8080/admin/concierge | `admin` |
| Intelligence | http://localhost:8080/admin/intelligence | `admin` |
| Adminer (database) | http://localhost:8081 | server `db`, user `drupal`, password `drupal` |
| Mailpit (caught emails) | http://localhost:8025 | |

Later, `docker compose up -d` starts the site and `docker compose stop` stops it, keeping the data.
If you change the API key in `.env`, run `docker compose up -d` to restart the containers with it.

> Local demo only: default passwords, no HTTPS. Don't expose it to the internet.

## The AI agents: Concierge and Intelligence

The admin panel has two chat pages, each backed by a Neuron AI agent running on Claude, plugged into Drupal
through the [ai_neuron](https://www.drupal.org/project/ai_neuron) module.

- **Concierge** is an editorial assistant. It finds, summarizes and reviews articles, improves them and
  drafts new ones. Before it saves a change to an article or creates a draft, the run pauses and asks you to
  approve or reject the change in the chat.
- **Intelligence** is a site analyst. Ask it how the site is used (popular content, active users, comments,
  failed logins, 404s...): it queries the database and answers with metric cards, charts and tables, plus a
  short insight.

### Background worker and Mercure

Agent runs take time (several model calls and tools), so they don't run inside the web request.
When you send a message, Drupal queues the turn with Symfony Messenger and returns right away.
The `worker` container picks it up and runs the agent. As the agent works, its output (text, tool calls,
cards, approval requests) is published as [AG-UI](https://docs.ag-ui.com) events to the
[Mercure](https://mercure.rocks) hub, and the browser, subscribed to that run, shows it as it streams.

```
Browser ──POST message──▶ Drupal ──queue──▶ worker (runs the agent)
   ▲                                              │
   └────────── Mercure hub ◀──── AG-UI events ────┘
```

The code is in `app/web/modules/custom/neuron_demo`: the agents in `src/Plugin/NeuronAgent`, their tools in
`src/Plugin/NeuronTool`, the worker code in `src/Messenger/AgentRunHandler.php`, and the chat UI in `ui/src`.
To watch the worker handle each turn, run `docker compose logs -f worker`.
