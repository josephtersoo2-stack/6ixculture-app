# 6ixCulture Enterprise AI Support — Phase 12 Production Hardening & Deployment Runbook

## 1. Scope & Purpose
This runbook provides step-by-step instructions for deploying, monitoring, operating, and troubleshooting the 6ixCulture Enterprise AI Support system on cPanel shared hosting and Linux server environments.

---

## 2. Pre-Deployment Verification Checklist

Before deploying changes to the production server:

1. **Verify Git History & Branch State**:
   ```bash
   git status
   git log -3 --oneline
   ```
   Ensure you are deploying the validated release candidate commit.

2. **Verify Local Automated Test Health**:
   ```bash
   php artisan test tests/Feature/Support/SupportPhase12ProductionHardeningTest.php
   php artisan test --filter=Support
   php artisan test
   ```
   All test executions must exit with code 0 and 0 failures.

3. **Verify Asset Build**:
   ```bash
   npm run build
   ```
   Confirm `public/build/manifest.json` and bundled assets exist.

---

## 3. Production Deployment Execution Steps

### 3.1 Step 1: Put Application into Maintenance Mode (Optional for Zero-Downtime)
```bash
php artisan down --secret="6ixculture-deploy-token"
```

### 3.2 Step 2: Code Transfer & Pull
Deploy the release code to the cPanel web root (`public_html` or application directory).

### 3.3 Step 3: Install PHP & Composer Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### 3.4 Step 4: Run Safe Database Migrations
```bash
php artisan migrate --force
```
*Note*: This executes the performance indexes migration `2026_08_19_000002_add_support_production_performance_indexes.php` without dropping any legacy tables.

### 3.5 Step 5: Seed Core Governance (If First Production Deployment)
```bash
php artisan db:seed --class=Database\\Seeders\\SupportDomainSeeder --force
```

### 3.6 Step 6: Execute Production Cache Warming
```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3.7 Step 7: Bring Application Back Online
```bash
php artisan up
```

---

## 4. cPanel Background Processing & Cron Configuration

### 4.1 Laravel Task Scheduler Cron (cPanel Cron Jobs)
Add the following cron entry via cPanel > Cron Jobs (running every minute):

```cron
* * * * * cd /home/username/public_html && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```
*(Replace `/home/username/public_html` and `/usr/local/bin/php` with your actual cPanel home path and PHP binary location)*

### 4.2 Queue Worker Configuration
For cPanel shared hosting without long-running supervisor daemons:
- Default queue driver: `sync` or `database`.
- If using `database` queue driver, add a scheduled cron to process queues periodically:
  ```cron
  * * * * * cd /home/username/public_html && /usr/local/bin/php artisan queue:work --stop-when-empty >> /dev/null 2>&1
  ```

---

## 5. Post-Deployment Verification & Health Checks

### 5.1 Query Public Support Health Endpoint
```bash
curl -i https://yourdomain.com/api/v1/support/health
```
**Expected Response**:
```json
{
  "success": true,
  "status": "ready",
  "services": {
    "support": true,
    "text": true,
    "voice": true,
    "realtime": false,
    "polling_fallback": true
  }
}
```

### 5.2 Test Core Operations
1. **Customer Storefront Widget**: Open storefront, submit question, verify streaming/polling response.
2. **Staff Support Workspace**: Log in as support agent (`/admin/support`), review queue, submit reply and internal note.
3. **Admin Governance**: Check knowledge articles and tool permissions at `/admin/support/governance`.

---

## 6. Rollback & Disaster Recovery Procedures

If an unforeseen blocker occurs in production:

1. **Clear Caches**:
   ```bash
   php artisan optimize:clear
   ```
2. **Revert Performance Migration (If Necessary)**:
   ```bash
   php artisan migrate:rollback --step=1
   ```
3. **Re-activate Legacy Cutover Flag (If Fallback Required on main `3cf1f2d`)**:
   ```bash
   php artisan support:cutover --enable-legacy
   ```
4. **Inspect Audit & Error Logs**:
   ```bash
   tail -n 100 storage/logs/laravel.log
   ```
