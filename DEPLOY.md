# Deploying to Render (free tier)

This repo includes a `Dockerfile` and a Render Blueprint (`render.yaml`)
that provision the web app plus a free PostgreSQL database.

## Steps

1. Go to [render.com](https://render.com) and sign up (GitHub login is
   the fastest option). Render's free web service + free database tier
   does not require a credit card at signup.
2. Click **New +** → **Blueprint**.
3. Connect your GitHub account if you haven't yet, and grant Render
   access to the `mohammedm98/bakery` repository.
4. Pick the `claude/bakery-management-system-a98u1c` branch (or `main`
   once this is merged).
5. Render detects `render.yaml` and shows a preview: one **bakery-app**
   web service and one **bakery-db** Postgres database. Click **Apply**
   (or **Create New Resources**).
6. Wait for the first build (~3-5 minutes — it builds the Docker image,
   runs migrations, and seeds demo data automatically).
7. Open the URL Render assigns (something like
   `https://bakery-app-xxxx.onrender.com`).

## Demo login

| Role | Email | Password |
|---|---|---|
| Site owner (super admin) | `admin@bakery.test` | `password` |
| Bakery owner | `owner@bakery.test` | `password` |

**Change these passwords immediately** since the URL is public. Log in
as the bakery owner, go to the profile page, and update the password —
do the same for the super admin account.

## Notes / limitations of the free tier

- The free web service **spins down after ~15 minutes of inactivity**
  and takes 30-60 seconds to wake up on the next visit — the first
  request after idling will feel slow, that's expected.
- The free Postgres database **expires after 90 days** on Render's free
  plan. Fine for trying the app out; upgrade the database plan (or
  re-provision) before that if you want to keep it running longer.
- `APP_KEY` and other config are provisioned via `render.yaml`. If you
  want a fresh app key of your own, generate one with
  `php artisan key:generate --show` and set it as the `APP_KEY`
  environment variable in the Render dashboard.
- The seeder only runs once (it's guarded to skip if a super admin
  already exists), so restarts/redeploys won't duplicate demo data.

## Redeploying after changes

Render redeploys automatically on every push to the connected branch.
Each deploy re-runs `php artisan migrate --force`, so new migrations
apply automatically.
