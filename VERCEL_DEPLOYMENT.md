# Vercel Deployment Guide

This Laravel application is pre-configured to run on **Vercel Serverless Functions** using `vercel-php`.

---

## 1. Import Repository on Vercel

1. Go to [https://vercel.com/new](https://vercel.com/new).
2. Select your GitHub repository.
3. In **Build and Output Settings**:
   - **Framework Preset**: Select **Other** (not Vite).
   - **Build Command**: `npm run vercel-build` (or leave default to let `vercel.json` handle it).
   - **Output Directory**: `public` (or leave default if configured in `vercel.json`).
4. Click **Import**.

---

## 2. Environment Variables

Add the following Environment Variables in your Vercel Project Settings (**Settings → Environment Variables**):

| Variable | Recommended Value | Description |
|---|---|---|
| `APP_NAME` | `SEO Management Services` | App Name |
| `APP_ENV` | `production` | Production Environment |
| `APP_KEY` | `base64:...` | Generate using `php artisan key:generate --show` |
| `APP_DEBUG` | `false` | Disable debug mode for security |
| `APP_URL` | `https://your-app-name.vercel.app` | Your Vercel app URL |
| `DB_CONNECTION` | `mysql` (or `pgsql` / `sqlite`) | Database driver |
| `DB_HOST` | `your-db-host.com` | Database host |
| `DB_DATABASE` | `your_db_name` | Database name |
| `DB_USERNAME` | `your_db_user` | Database username |
| `DB_PASSWORD` | `your_db_password` | Database password |
| `SESSION_DRIVER` | `cookie` | Cookie-based session storage |
| `CACHE_STORE` | `array` | Array cache store for serverless |
| `LOG_CHANNEL` | `stderr` | Log directly to Vercel function logs |

> [!NOTE]
> Serverless environments (like Vercel) have a read-only filesystem except for `/tmp`. For persistent database storage, use a managed remote database (PlanetScale, Supabase, Neon, AWS RDS, or Railway).

---

## 3. Deploy

Click **Deploy**. Vercel will automatically run `npm run vercel-build` to compile Vite assets and PHP dependencies, then launch your serverless deployment.
