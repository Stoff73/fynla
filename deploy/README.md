# Deployment Configurations

This directory contains environment-specific configurations for deploying Fynla to different targets.

## Directory Structure

```
deploy/
├── README.md               # This file
├── fynla-org/              # ROOT deployment at https://fynla.org
│   ├── .env.production     # Environment template
│   ├── .htaccess           # Apache config for root deployment
│   └── build.sh            # Build script
└── csjones-fynla/          # SUBDIRECTORY deployment at https://csjones.co/fynla
    ├── .env.production     # Environment template
    ├── .htaccess           # Apache config for subdirectory deployment
    └── build.sh            # Build script
```

## Usage

### Building for a Target

```bash
# For fynla.org (root deployment)
./deploy/fynla-org/build.sh

# For csjones.co/fynla (subdirectory deployment)
./deploy/csjones-fynla/build.sh
```

### Key Differences

| Setting | fynla.org (ROOT) | csjones.co/fynla (SUBDIRECTORY) |
|---------|------------------|----------------------------------|
| `VITE_BASE_PATH` | `/build/` | `/fynla/build/` |
| `APP_URL` | `https://fynla.org` | `https://csjones.co/fynla` |
| `RewriteBase` | `/` | `/fynla/` |
| `SANCTUM_STATEFUL_DOMAINS` | `fynla.org,www.fynla.org` | `csjones.co,www.csjones.co` |

## Deployment Steps

1. Run the appropriate build script
2. Copy `.env.production` to server as `.env`
3. Update placeholder values (database, email, API keys)
4. Copy `.htaccess` to the server's `public/` directory
5. Follow the full guide in `DEPLOYMENT_FYNLA_ORG.md` (for fynla.org)

## Development

Local development uses:
- `VITE_BASE_PATH=/` (default when not set)
- `.env` in project root (not committed)
- `public/.htaccess` for local development

Run `./dev.sh` to start the development servers.
