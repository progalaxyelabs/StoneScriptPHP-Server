# Upgrading StoneScriptPHP Server

## Understanding Project Structure

StoneScriptPHP consists of two packages:

1. **Framework** (`progalaxyelabs/stonescriptphp`) - Core library in `vendor/`
2. **Server** (this project) - Project skeleton with CLI tools

When you run `composer create-project`, you get a **copy** of the server skeleton. This means:

- ✅ `composer update` updates the framework package in `vendor/`
- ❌ `composer update` does NOT update your CLI scripts (they're project files, not dependencies)

## Upgrade Methods

### Method 1: Automatic Upgrade (Recommended)

Use the built-in upgrade command:

```bash
# Check for updates
php stone upgrade --check

# Upgrade to latest version
php stone upgrade

# Force upgrade (even if version matches)
php stone upgrade --force

# Preview changes without applying
php stone upgrade --dry-run
```

This downloads and updates:
- `stone` CLI script
- All files in `cli/` directory
- Automatically creates backups

### Method 2: Manual Framework Update

Update only the framework package (in `vendor/`):

```bash
composer update progalaxyelabs/stonescriptphp
```

This updates the core framework but **not** CLI tools.

### Method 3: Manual File Replacement

If `php stone upgrade` doesn't work:

1. Download the latest release from GitHub:
   ```bash
   wget https://github.com/progalaxyelabs/StoneScriptPHP-Server/archive/refs/tags/v2.0.13.tar.gz
   tar -xzf v2.0.13.tar.gz
   ```

2. Backup your CLI files:
   ```bash
   cp -r cli cli.backup
   cp stone stone.backup
   ```

3. Copy new CLI files:
   ```bash
   cp -r StoneScriptPHP-Server-2.0.13/cli/* cli/
   cp StoneScriptPHP-Server-2.0.13/stone stone
   chmod +x stone
   ```

4. Review and merge any custom changes from backups

## What Gets Updated

### Automatically Updated (via `php stone upgrade`):
- ✅ `stone` CLI entry point
- ✅ `cli/generate-*.php` scripts
- ✅ `cli/migrate.php`
- ✅ `cli/upgrade.php` (self-update)

### Manually Update if Needed:
- ⚠️ `composer.json` - Review for new dependencies
- ⚠️ `Dockerfile` - Check for improvements
- ⚠️ `docker-compose.yml` - Check for new services
- ⚠️ `.env.example` - Check for new variables
- ⚠️ Project documentation files

### Never Touch (Your Custom Code):
- ❌ `src/App/` - Your application code
- ❌ `src/postgresql/` - Your database schema
- ❌ `public/` - Your public files
- ❌ `.env` - Your environment config

## Version-Specific Upgrade Notes

### Upgrading to v2.0.13

**Critical Fixes:**
1. Logger STDERR/STDOUT bug fixed in framework
2. CLI scripts now use `src/postgresql/` paths
3. Docker support added

**Steps:**
```bash
# 1. Update framework package
composer update progalaxyelabs/stonescriptphp

# 2. Update CLI tools
php stone upgrade

# 3. (Optional) Add Docker support
# Copy Dockerfile, docker-compose.yml, .dockerignore from release
```

**Breaking Changes:**
- None - fully backward compatible

**New Features:**
- `php stone upgrade` command added
- Docker deployment support
- Improved CLI help text

### Upgrading from v2.0.11 or Earlier

If upgrading from v2.0.11 or earlier:

```bash
# 1. Update framework
composer update

# 2. CLI upgrade won't work (command doesn't exist yet)
# Manually download and replace cli/ files from v2.0.13

# 3. After upgrade, future upgrades will work automatically
php stone upgrade --check
```

## Troubleshooting

### `php stone upgrade` fails with HTTP error

**Problem:** Can't reach GitHub API

**Solution:**
```bash
# Check internet connection
curl -I https://api.github.com

# Try with --force if version detection fails
php stone upgrade --force

# Or use manual method (see Method 3 above)
```

### Upgrade overwrote my custom CLI modifications

**Problem:** You customized CLI files and upgrade replaced them

**Solution:**
```bash
# Restore from automatic backup
ls -la cli/*.backup-*
cp cli/generate-route.php.backup-20251210153000 cli/generate-route.php

# In future, consider forking or extending instead of modifying
```

### Version still shows old after upgrade

**Problem:** `composer.json` version not updated

**Solution:**
```bash
# Manually update composer.json
nano composer.json
# Change "version": "2.0.12" to "version": "2.0.13"

# Or just ignore - it's cosmetic
```

### Need to upgrade specific file only

**Problem:** Only want to update one CLI script

**Solution:**
```bash
# Download specific file
VERSION=v2.0.13
FILE=cli/generate-model.php
curl -o $FILE https://raw.githubusercontent.com/progalaxyelabs/StoneScriptPHP-Server/$VERSION/$FILE
```

## Recommended Upgrade Schedule

- **Framework updates** - Run weekly: `composer update`
- **CLI updates** - Run monthly: `php stone upgrade --check`
- **Major releases** - Review changelog and test in development first

## Getting Help

If you encounter issues:

1. Check [CHANGELOG.md](CHANGELOG.md) for breaking changes
2. Review [GitHub Issues](https://github.com/progalaxyelabs/StoneScriptPHP-Server/issues)
3. Run with `--dry-run` first to preview changes
4. Keep backups before upgrading

## Future: Auto-Update (v3.0 Planned)

In v3.0, we plan to move CLI tools to the framework package, making them auto-update with `composer update`. This will eliminate the need for `php stone upgrade`.

Current structure (v2.x):
```
my-project/
├── cli/           ← Must manually update
├── vendor/
│   └── progalaxyelabs/
│       └── stonescriptphp/  ← Auto-updates with composer
```

Planned structure (v3.0):
```
my-project/
├── vendor/
│   └── progalaxyelabs/
│       └── stonescriptphp/
│           └── cli/  ← Will auto-update with composer
```
