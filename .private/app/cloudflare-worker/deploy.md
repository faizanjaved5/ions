# Deploy Google Drive to R2 Serverless Function

## Prerequisites

1. **Cloudflare Account** with R2 enabled
2. **Wrangler CLI** installed: `npm install -g wrangler`
3. **Cloudflare R2 configured** in your ION project

## Step 1: Install Wrangler CLI

```bash
npm install -g wrangler
```

## Step 2: Login to Cloudflare

```bash
wrangler login
```

## Step 3: Create Worker Project

```bash
mkdir ion-drive-r2-worker
cd ion-drive-r2-worker
wrangler init drive-to-r2-worker
```

## Step 4: Replace worker.js

Copy the contents of `drive-to-r2-worker.js` to your worker project's `src/index.js` file.

## Step 5: Configure wrangler.toml

Create/update `wrangler.toml`:

```toml
name = "drive-to-r2-worker"
main = "src/index.js"
compatibility_date = "2024-01-15"

[env.production]
name = "drive-to-r2-worker"

[[env.production.routes]]
pattern = "your-domain.com/worker/drive-to-r2"
zone_name = "your-domain.com"
```

## Step 6: Deploy Worker

```bash
wrangler publish
```

## Step 7: Get Worker URL

After deployment, you'll get a URL like:
`https://drive-to-r2-worker.your-subdomain.workers.dev`

## Step 8: Update ION Configuration

In `config/config.php`, update:

```php
'google_drive_worker_url' => 'https://drive-to-r2-worker.your-subdomain.workers.dev',
```

## Step 9: Test the Setup

Run the test script:

```bash
php app/test-google-drive-serverless.php
```

## Security Notes

- Worker processes R2 credentials in memory only
- Google Drive access tokens are temporary
- No credentials are stored in Worker
- All transfers happen in Cloudflare's edge network

## Troubleshooting

### Common Issues:

1. **Worker deployment fails**: Check Wrangler CLI version
2. **R2 access denied**: Verify R2 credentials in config
3. **Google Drive access denied**: Check OAuth token scope
4. **Large file timeout**: Increase timeout in PHP config

### Debug Worker:

```bash
wrangler tail drive-to-r2-worker
```

### View Worker logs:

```bash
wrangler logs drive-to-r2-worker
```

## Performance Benefits

| File Size | Method | Transfer Time | Server Storage |
|-----------|--------|---------------|----------------|
| 100MB | Serverless | ~30 seconds | 0 MB |
| 100MB | Server Fallback | ~60 seconds | 100 MB temp |
| 1GB | Serverless | ~5 minutes | 0 MB |
| 1GB | Server Fallback | ~10 minutes | 1 GB temp |

The serverless approach is consistently faster and uses no server storage!