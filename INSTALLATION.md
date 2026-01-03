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
├── ProcessEmbedr.module         (Main admin module)
├── TextformatterEmbedr.module   (Text formatter)
├── Embedr.php                   (Single embed class)
├── Embedrs.php                  (Embed collection)
├── EmbedrType.php               (Type class)
├── EmbedrTypes.php              (Type collection)
└── EmbedrRenderer.php           (Visual renderer)
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
git clone https://github.com/your-repo/Embedr.git
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
chmod 644 site/modules/Embedr/*.module
```

3. **Check ProcessWire requirements:**
   - Must have `ProcessEmbedr.module` and `TextformatterEmbedr.module`
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
CREATE TABLE IF NOT EXISTS `embedr` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `type_id` int(10) unsigned NOT NULL DEFAULT 0,
  `selector` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `type_id` (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `embedr_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `icon` varchar(64) NOT NULL DEFAULT 'file-text',
  `template` varchar(255) DEFAULT NULL,
  `mode` enum('array','single') NOT NULL DEFAULT 'array',
  `data` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
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

### Issue: 500 error on frontend for guests

**Symptoms:**
- Works for logged-in admin
- 500 error for guests
- Error log shows: "You do not have permission to execute this module - ProcessEmbedr"

**Solution:**

**Ensure you're using version 0.2.12 or later.**

This was a known issue in versions < 0.2.12. Update to the latest version.

If still having issues:
1. Enable Debug Mode
2. Check Setup → Logs → errors
3. Check Setup → Logs → embedr-debug

---

## Upgrading

### From 0.2.11 or Earlier

**Important:** Version 0.2.12 fixes critical guest access bug.

**Upgrade steps:**

1. **Backup your database**
```bash
mysqldump -u username -p database_name > backup.sql
```

2. **Backup current module files**
```bash
cp -r site/modules/Embedr site/modules/Embedr.backup
```

3. **Replace files:**
   - ProcessEmbedr.module
   - TextformatterEmbedr.module
   - Embedr.php
   - EmbedrType.php
   - Embedrs.php

4. **Refresh modules:**
```
Modules → Refresh
```

5. **Verify version:**
```
Modules → ProcessEmbedr
Version should show: 0.2.12
```

6. **Test as guest:**
   - Open site in incognito mode
   - Verify embeds render
   - No 500 errors

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

1. **Enable Debug Mode**
2. **Check logs:** Setup → Logs
3. **Search [ProcessWire forums](https://processwire.com/talk/)**
4. **Report bugs** with:
   - ProcessWire version
   - PHP version
   - Error messages
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
