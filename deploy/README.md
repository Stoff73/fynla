# Deployment Configurations

This directory contains environment-specific configurations for deploying Fynla to different targets.

> **IMPORTANT: LOCAL BUILD REQUIRED**
>
> The server does not have enough memory to run `npm install` or `npm run build`.
> You MUST build the frontend assets locally and include `public/build/` in the deployment package.

---

## CRITICAL: .htaccess File Warning

> **DO NOT upload `public/.htaccess` from your local folder!**
>
> The local `public/.htaccess` is configured for csjones.co/tengo (subdirectory) and will cause **500 Internal Server Error** on fynla.org.

**Correct files to use:**
| Target | .htaccess Source | Upload To |
|--------|------------------|-----------|
| fynla.org | `deploy/fynla-org/.htaccess` | `public_html/public/.htaccess` |
| csjones.co/fynla | `deploy/csjones-fynla/.htaccess` | `public_html/public/.htaccess` |

The wrong .htaccess causes:
- `<DirectoryMatch not allowed here` error (not allowed in .htaccess on shared hosting)
- Wrong `RewriteBase` path
- CSS/JS MIME type issues

---

## Directory Structure

```
deploy/
├── README.md               # This file
├── fynla-org/              # ROOT deployment at https://fynla.org
│   ├── .env.production     # Environment template
│   ├── .htaccess           # Apache config for root deployment
│   └── build.sh            # Build script (creates deployment package)
└── csjones-fynla/          # SUBDIRECTORY deployment at https://csjones.co/fynla
    ├── .env.production     # Environment template
    ├── .htaccess           # Apache config for subdirectory deployment
    └── build.sh            # Build script (creates deployment package)
```

## Usage

### Building for a Target

Each build script:
1. Sets the correct environment variables
2. Builds frontend assets locally
3. Creates a deployment-ready ZIP package

```bash
# For fynla.org (root deployment)
./deploy/fynla-org/build.sh

# For csjones.co/fynla (subdirectory deployment)
./deploy/csjones-fynla/build.sh
```

### What the Build Script Does

1. Builds `public/build/` with the correct base path
2. Copies all necessary files (excluding `node_modules`, `.git`, `tests`)
3. Includes the correct `.htaccess` for the target environment
4. Creates a ZIP file ready to upload to the server

### Key Differences Between Environments

| Setting | fynla.org (ROOT) | csjones.co/fynla (SUBDIRECTORY) |
|---------|------------------|----------------------------------|
| `VITE_BASE_PATH` | `/build/` | `/fynla/build/` |
| `APP_URL` | `https://fynla.org` | `https://csjones.co/fynla` |
| `RewriteBase` | `/` | `/fynla/` |
| `SANCTUM_STATEFUL_DOMAINS` | `fynla.org,www.fynla.org` | `csjones.co,www.csjones.co` |

## Deployment Steps

1. **Run the build script** - This builds assets and creates the ZIP package
2. **Upload the ZIP** to the server via File Manager or SFTP
3. **Extract the ZIP** on the server
4. **Copy `.env.production`** to `.env` and update credentials
5. **Copy `.htaccess`** to the server's `public/` directory
6. **Run post-upload commands** via SSH (see deployment guide)

Full guide: `DEPLOYMENT_FYNLA_ORG.md`

## What MUST Be Built Locally

| Component | Build Locally? | Include in Package? | Why? |
|-----------|----------------|---------------------|------|
| `public/build/` | YES | YES | Server lacks memory for npm |
| `vendor/` | YES (if updated) | YES | Server may lack memory for composer |
| `node_modules/` | YES | NO | Not needed on server |

## DO NOT Run These Commands on Server

```bash
# These will FAIL on shared hosting due to memory limits
npm install        # Requires 1-2GB RAM
npm run build      # Requires 1-2GB RAM
```

## Development

Local development uses:
- `VITE_BASE_PATH=/` (default when not set)
- `.env` in project root (not committed)
- `public/.htaccess` for local development

Run `./dev.sh` to start the development servers.
