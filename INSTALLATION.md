# Embedr Installation Guide

Complete step-by-step installation and setup guide for the Embedr module.

---

## Requirements

- **ProcessWire:** 3.0.0 or higher
- **PHP:** 8.0 or higher
- **MySQL:** 5.7 or higher / MariaDB 10.2 or higher
- **Apache/Nginx:** Any modern web server
- **Permissions:** Write access to `/site/modules/` directory

---

## Installation Methods

### Method 1: Manual Installation (Recommended)

#### Step 1: Download Files

Download the Embedr module files to your computer.

#### Step 2: Upload to Server

Upload all files to `/site/modules/Embedr/`:

```
/site/modules/Embedr/
├── ProcessEmbedr.module.php         (Main admin module)
├── TextformatterEmbedr.module.php   (Text formatter)
├── Embedr.php                       (Single embed class)
├── Embedrs.php                      (Embed collection)
├── EmbedrType.php                   (Type class)
├── EmbedrTypes.php                  (Type collection)
└── EmbedrRenderer.php               (Visual renderer)
```

**Via FTP:**
```
Upload to: /public_html/site/modules/Embedr/
```

**Via SSH:**
```bash
cd /path/to/your/site
mkdir -p site/modules/Embedr
cd site/modules/Embedr
# Upload files here
```

#### Step 3: Set Permissions

```bash
chmod 755 site/modules/Embedr
chmod 644 site/modules/Embedr/*
```

#### Step 4: Install Module

1. Login to ProcessWire admin
2. Navigate to **Modules**
3. Click **Refresh** (important!)
4. Find "Embedr" in the Site modules section
5. Click **Install**
6. Confirm installation

**Expected outcome:** You should see two new items installed:
- ✅ **Embedr** (ProcessEmbedr)
- ✅ **Embedr Text Formatter** (TextformatterEmbedr)

---

### Method 2: Git Installation

```bash
cd /path/to/your/site/site/modules
git clone https://github.com/mxmsmnv/Embedr.git
cd Embedr
chmod 644 *
```

Then follow steps 4 in Method 1.

---

## Initial Configuration

### Step 1: Configure Module Settings

Navigate to: **Setup → Modules → ProcessEmbedr → Configure**

**Recommended Initial Settings:**

| Setting | Value | Notes |
|---------|-------|-------|
| Components Path | `components/` | Default location for PHP templates |
| Opening Tag | `((` | Keep default |
| Closing Tag | `))` | Keep default |
| Auto-discover Types | ☐ Unchecked | Enable later if needed |
| Show Type Icons | ☑ Checked | Recommended for visual clarity |
| Debug Mode | ☐ Unchecked | Enable only for troubleshooting |

Click **Save**.

---

### Step 2: Set Up Permissions

Navigate to: **Access → Roles**

**For Admin Role:**
1. Edit the **admin** role
2. Check both permissions:
   - ☑ **embedr** (View embeds)
   - ☑ **embedr-edit** (Create/edit/delete)
3. Save

**For Other Roles:**
- **Editor role:** Check both if they should manage embeds
- **Guest role:** No permissions needed (embeds render automatically)

---

### Step 3: Add Textformatter to Fields

You need to add the Embedr Text Formatter to any field where you want to use embeds.

**Common fields:** body, content, description, summary

**For each field:**

1. Navigate to: **Setup → Fields → [field name]**
2. Click on **Details** tab
3. Scroll to **Textformatters**
4. Check: ☑ **Embedr Text Formatter**
5. **Important:** Drag "Embedr Text Formatter" to desired position
   - Usually place it AFTER "Markdown" or "HTML Entity Encoder"
   - But BEFORE "Smart UTF-8 Characters"
6. Save

**Example order for body field:**
```
1. Markdown Extra
2. Embedr Text Formatter  ← Add here
3. HTML Entity Encoder
4. Smart UTF-8 Characters
```

---

### Step 4: Create Components Directory

If you plan to use custom PHP templates:

```bash
mkdir -p /path/to/site/site/templates/components
chmod 755 /path/to/site/site/templates/components
```

---

## Post-Installation Verification

### Test 1: Access Admin Interface

1. Navigate to: **Setup → Embedr**
2. You should see:
   - Empty list with "No embeds found"
   - **Add New** button
   - **Types** tab
3. ✅ **Pass:** Admin interface loads
4. ❌ **Fail:** See troubleshooting below

### Test 2: Create Test Type

1. Go to **Setup → Embedr → Types**
2. Click **Add New**
3. Fill in:
   - **Name:** test
   - **Title:** Test Type
   - **Icon:** file-text
4. Click **Save**
5. ✅ **Pass:** Type appears in list
6. ❌ **Fail:** Error message appears - see troubleshooting

### Test 3: Create Test Embed

1. Go to **Setup → Embedr**
2. Click **Add New**
3. Fill in:
   - **Name:** test-embed
   - **Title:** Test Embed
   - **Type:** test
   - **Selector:** `template=basic-page, limit=3`
4. Click **Save**
5. ✅ **Pass:** Embed appears in list with preview
6. ❌ **Fail:** Error or no preview - see troubleshooting

### Test 4: Use in Template

1. Edit any page
2. In the body field, add: `((test-embed))`
3. Save page
4. View page on frontend
5. ✅ **Pass:** See cards or list of pages
6. ❌ **Fail:** See HTML comment or error - see troubleshooting

---

## Troubleshooting Installation

### Issue: Module doesn't appear after refresh

**Possible causes:**
- Files not uploaded correctly
- Wrong directory structure
- Permission issues

**Solutions:**

1. **Check file structure:**
```bash
ls -la site/modules/Embedr/
```

Should show all 7 PHP files.

2. **Check permissions:**
```bash
chmod 755 site/modules/Embedr
chmod 644 site/modules/Embedr/*.php
```

3. **Check ProcessWire requirements:**
   - Must have `ProcessEmbedr.module.php` and `TextformatterEmbedr.module.php`
   - Files must be valid PHP (no syntax errors)

4. **Check PHP error log** for syntax errors

---

### Issue: Database tables not created

**Symptoms:**
- Module installs but errors when accessing Setup → Embedr
- Error mentions missing table `embedr` or `embedr_types`

**Solutions:**

1. **Manually create tables** (run in phpMyAdmin or MySQL client):

```sql
CREATE TABLE IF NOT EXISTS `embedr_types` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name`     VARCHAR(64) NOT NULL,
  `title`    VARCHAR(255) NOT NULL,
  `template` VARCHAR(255) DEFAULT '',
  `icon`     VARCHAR(64) DEFAULT '',
  `sort`     INT UNSIGNED DEFAULT 0,
  `mode`     ENUM('once','array') DEFAULT 'array',
  `config`   TEXT,
  UNIQUE KEY `name` (`name`),
  KEY `sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `embedr` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name`     VARCHAR(128) NOT NULL,
  `title`    VARCHAR(255) NOT NULL,
  `type_id`  INT UNSIGNED NOT NULL,
  `selector` TEXT NOT NULL,
  `created`  INT UNSIGNED NOT NULL,
  `modified` INT UNSIGNED NOT NULL,
  UNIQUE KEY `name` (`name`),
  KEY `type_id` (`type_id`),
  FOREIGN KEY (`type_id`) REFERENCES `embedr_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

2. **Reinstall module:**
   - Uninstall Embedr
   - Refresh modules
   - Install again

---

### Issue: Textformatter not working

**Symptoms:**
- `((embed-name))` appears as-is on frontend
- No rendering happens

**Solutions:**

1. **Check textformatter is added to field:**
```
Setup → Fields → body → Details → Textformatters
☑ Embedr Text Formatter
```

2. **Check field is being output:**
```php
// In template, make sure you're outputting the field:
echo $page->body; // Correct
// NOT:
echo $page->getUnformatted('body'); // Wrong - bypasses textformatters
```

3. **Clear cache:**
```
Setup → Advanced → Clear All Caches
```

---

### Issue: Permission denied errors

**Symptoms:**
- Can't access Setup → Embedr
- "You do not have permission" message

**Solutions:**

1. **Check role permissions:**
```
Access → Roles → [Your Role]
Permissions → ☑ embedr
Permissions → ☑ embedr-edit
```

2. **Check you're logged in as superuser** during setup

---

### Issue: Render error on frontend for guests

**Symptoms:**
- Works for logged-in admin
- Error or blank output for guests

**Solution:**

1. Enable Debug Mode (`Setup → Modules → ProcessEmbedr → Configure`)
2. Open the failing page as a guest (incognito)
3. Check `Setup → Logs → embedr-errors` for the full error
4. Common causes:
   - Custom template accesses a field without `hasField()` guard
   - Guest role does not have view permission for the pages in the selector

---

## Upgrading

### Upgrading to a new version

1. **Backup your database**
```bash
mysqldump -u username -p database_name > backup.sql
```

2. **Backup current module files**
```bash
cp -r site/modules/Embedr site/modules/Embedr.backup
```

3. **Replace all module files** with the new version

4. **Refresh modules:**
```
Modules → Refresh
```

5. **Verify version** in `Modules → ProcessEmbedr`

6. **Test as guest** — open the site in incognito mode and verify embeds render correctly

See [CHANGELOG.md](CHANGELOG.md) for what changed between versions.

---

## Uninstallation

### Clean Uninstall

**Warning:** This will delete all embeds and types from database.

1. **Backup data first** (export embeds if needed)

2. **Uninstall module:**
```
Modules → Embedr → Uninstall
Confirm deletion
```

3. **Remove files:**
```bash
rm -rf site/modules/Embedr
```

4. **Remove database tables** (if they weren't auto-removed):
```sql
DROP TABLE IF EXISTS embedr;
DROP TABLE IF EXISTS embedr_types;
```

5. **Remove textformatter from fields:**
```
Setup → Fields → [each field]
Details → Textformatters → ☐ Embedr Text Formatter
```

---

## Next Steps

After successful installation:

1. **Read the [Usage Guide](README.md#usage-guide)** to learn how to create embeds
2. **Check [Examples](EXAMPLES.md)** for common use cases
3. **Review [Best Practices](README.md#best-practices)** for optimal setup
4. **Create your first custom PHP template** for full control

---

## Getting Help

If installation fails:

1. **Enable Debug Mode** (`Setup → Modules → ProcessEmbedr → Configure`)
2. **Check logs:** `Setup → Logs → embedr-debug` and `embedr-errors`
3. **Open an issue:** [github.com/mxmsmnv/Embedr](https://github.com/mxmsmnv/Embedr)
4. **Contact the author:** maxim@smnv.org

Include in your report:
- ProcessWire version
- PHP version
- Error messages from the log
- Steps to reproduce

---

## Checklist

Use this checklist to verify installation:

- [ ] All 7 module files uploaded
- [ ] Files have correct permissions (644)
- [ ] Module appears in Modules list
- [ ] Module installed successfully
- [ ] Both permissions assigned to admin role
- [ ] Textformatter added to body field
- [ ] Components directory created
- [ ] Admin interface accessible (Setup → Embedr)
- [ ] Can create test type
- [ ] Can create test embed
- [ ] Test embed works on frontend
- [ ] Works for guest users (test in incognito)

✅ **All checked?** You're ready to use Embedr!
