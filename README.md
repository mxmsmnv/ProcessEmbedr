# Embedr Module for ProcessWire

**Version:** 0.2.13  
**Author:** Maxim Alex
**License:** MIT  
**ProcessWire:** 3.0+

Dynamic content embed management system with live preview, custom PHP templates, and visual card builder for ProcessWire CMS.

---

## Features

### Core Features
- 🎯 **Dynamic Content Blocks** - Create reusable content blocks with ProcessWire selectors
- 🔄 **Live Preview** - Real-time preview in the admin interface
- 📝 **Custom PHP Templates** - Full control with custom PHP rendering templates
- 🎨 **Visual Card Builder** - Built-in UIKit-based card renderer (no PHP needed)
- 🏷️ **Shortcode System** - Simple `{% raw %}((embed-name)){% endraw %}` tags in any text field
- 🔍 **Debug Mode** - Comprehensive logging for troubleshooting

### Advanced Features
- **Multiple Embed Types** - Define reusable types with templates and settings
- **Auto-Discovery** - Automatically find and register PHP templates from components folder
- **Guest-Safe** - Works for both logged-in users and guests
- **Error Handling** - Graceful error handling with detailed logging
- **Permissions System** - Granular permission control for viewing and editing

---

## Quick Start

### Installation

1. **Upload module files** to `/site/modules/Embedr/`:
```
/site/modules/Embedr/
├── ProcessEmbedr.module.php
├── TextformatterEmbedr.module.php
├── Embedr.php
├── Embedrs.php
├── EmbedrType.php
├── EmbedrTypes.php
└── EmbedrRenderer.php
```

2. **Install the module:**
```
Modules → Refresh → Embedr → Install
```

3. **Add Textformatter to fields:**
```
Setup → Fields → body (or your field)
Details → Textformatters → ☑ Embedr Text Formatter
```

### Basic Usage

1. **Create an embed type:**
```
Setup → Embedr → Types → Add New
Name: articles
Template: articles.php (optional)
```

2. **Create an embed:**
```
Setup → Embedr → Add New
Name: latest-articles
Title: Latest Articles
Type: articles
Selector: template=article, limit=6
```

3. **Use in templates:**
```
{% raw %}Body field: ((latest-articles)){% endraw %}
```

---

## Architecture

### Component Structure

```
Embedr Ecosystem
├── ProcessEmbedr (Admin Interface)
├── TextformatterEmbedr (Parser)
├── Embedr (Single Embed Object)
├── Embedrs (Embed Collection + Database)
├── EmbedrType (Single Type Object)
├── EmbedrTypes (Type Collection + Database)
└── EmbedrRenderer (Visual Card Builder)
```

### Data Flow

```
{% raw %}
1. Text Field with ((embed-name))
   ↓
2. TextformatterEmbedr (Textformatter)
   ↓
3. Embedrs::get('embed-name')
   ↓
4. Embedr::render()
   ↓
5. Check: PHP Template exists?
   ├─→ YES: Include custom PHP template
   └─→ NO:  Use EmbedrRenderer (visual cards)
   ↓
6. Return HTML
{% endraw %}
```

---

## Configuration

### Module Settings

Access via: `Setup → Modules → ProcessEmbedr → Configure`

**Components Path** (default: `components/`)
- Path to PHP template files relative to `/site/templates/`
- Example: `components/` → `/site/templates/components/`

**Opening Tag** (default: `((`)
- Tag that starts an embed
- Can be customized (e.g., `{% raw %}{{{% endraw %}`, `[[`)

**Closing Tag** (default: `))`)
- Tag that ends an embed
- Must match opening tag style

**Auto-discover Types** (default: unchecked)
- Automatically find `.php` files in components path
- Creates types on first module access

**Show Type Icons** (default: checked)
- Display Font Awesome icons next to type names

**Debug Mode** (default: unchecked)
- Enable detailed logging to `embedr-debug` log
- Shows full execution flow for troubleshooting

---

## Usage Guide

### Creating Embed Types

Types define reusable configurations for similar embeds.

**Example: Article List Type**
```
Name: articles
Title: Article Lists
Icon: file-text
Template: articles.php
Mode: array
Card Width: 400px
Image Width: 192
Image Height: 192
```

### Creating Embeds

Embeds are instances of types with specific selectors.

**Example: Latest Articles Embed**
```
Name: latest-articles
Title: Latest Articles
Type: articles
Selector: template=article, sort=-created, limit=6
```

### Using Shortcodes

**In any text field:**
```html
{% raw %}
<h2>Recent Posts</h2>
((latest-articles))

<h2>Featured Products</h2>
((featured-products))
{% endraw %}
```

**In PHP templates:**
```php
echo $page->body; // Textformatter processes {% raw %}((tags)){% endraw %} automatically
```

---

## Custom PHP Templates

### Template Structure

**Location:** `/site/templates/components/your-template.php`

**Available Variables:**
```php
$items      // PageArray - Found pages from selector
$page       // Page - Current page
$config     // Config - ProcessWire config
$input      // WireInput - Request data
$sanitizer  // Sanitizer - Sanitization methods
$embed      // Embedr - The embed object
```

### Basic Template Example

```php
<?php namespace ProcessWire;
/**
 * Articles List Template
 */

if(!$items->count()) {
    echo "<!-- No articles found -->";
    return;
}
?>
<div class="article-grid">
    <?php foreach($items as $article): ?>
        <article class="card">
            <?php if($article->images->count()): ?>
                <img src="<?= $article->images->first()->width(400)->url ?>" 
                     alt="<?= $article->title ?>">
            <?php endif; ?>
            
            <h3><?= $article->title ?></h3>
            
            <?php if($article->summary): ?>
                <p><?= $article->summary ?></p>
            <?php endif; ?>
            
            <a href="<?= $article->url ?>">Read more</a>
        </article>
    <?php endforeach; ?>
</div>
```

### Guest-Safe Template

Always check field existence for guest users:

```php
<?php namespace ProcessWire;

if(!$items->count()) return;

// Get current user
$user = $this->wire('user');
$isGuest = $user->isGuest();
?>
<div class="articles">
    <?php foreach($items as $article): ?>
        <article>
            <?php 
            // Safe image access
            if($article->hasField('images') && $article->images && $article->images->count()): 
                $img = $article->images->first();
                if($img):
            ?>
                <img src="<?= $img->width(400)->url ?>" alt="<?= $article->title ?>">
            <?php 
                endif;
            endif; 
            ?>
            
            <h3><?= $article->title ?></h3>
            
            <?php 
            // Safe summary access
            if($article->hasField('summary') && $article->summary): 
            ?>
                <p><?= $article->summary ?></p>
            <?php endif; ?>
            
            <a href="<?= $article->url ?>">Read more</a>
        </article>
    <?php endforeach; ?>
</div>
```

---

## Visual Card Renderer

When no PHP template is specified, Embedr uses the built-in visual renderer.

### Configuration

Configure card appearance in the Type settings:

**Layout:**
- Card Width: 400px (default)
- Image Aspect Ratio: 1:1, 16:9, 4:3, etc.
- Grid Columns: 1-6 columns
- Gap Size: small, medium, large

**Card Sections:**
- ☑ Show Image
- ☑ Show Title
- ☑ Show Summary
- ☑ Show Link

**Image Settings:**
- Width: 192px (default)
- Height: 192px (default)
- Crop: true/false

**Styling:**
- UIKit CSS classes
- Custom CSS support

---

## Permissions

### Permission Levels

**embedr** - View embeds
- Can access Embedr page
- Can view embed list
- Cannot modify

**embedr-edit** - Edit embeds
- Can create new embeds
- Can edit existing embeds
- Can delete embeds
- Can manage types

### Assigning Permissions

```
Access → Roles → [Role Name]
Permissions → ☑ embedr
Permissions → ☑ embedr-edit (if needed)
```

---

## Debug Mode

### Enabling Debug Mode

```
Setup → Modules → ProcessEmbedr → Configure
☑ Debug Mode
Save
```

### Log Locations

**embedr-debug** - Full execution log
```
Setup → Logs → embedr-debug
```

Shows:
- Textformatter calls
- Embed lookups
- Type loading
- Selector execution
- Template rendering
- User context (guest/logged-in)

**embedr-errors** - Error log only
```
Setup → Logs → embedr-errors
```

Shows:
- Template errors
- Selector errors
- Permission errors
- Exception details

### Log Example

```
[TextformatterEmbedr::formatValue] Called | Page=/news/hello-world/, User=guest
[TextformatterEmbedr::formatValue] Found 2 embed(s): latest-articles, featured
[TextformatterEmbedr::getReplacement] Looking for embed: latest-articles
[TextformatterEmbedr::getReplacement] Embed found | ID=3, Name=latest-articles, Type=articles
[Embedr::render] Starting | Embed=latest-articles (ID=3), Selector=template=53 | User=guest (guest=YES)
[Embedr::render] Type loaded | Name=articles, Template=articles.php, Mode=array
[Embedr::render] Executing selector: template=53
[Embedr::render] Selector found 6 items
[Embedr::render] Using PHP template: /site/templates/components/articles.php | Exists: YES
[Embedr::render] PHP template rendered (5089 chars)
[TextformatterEmbedr::getReplacement] Rendered (5089 chars): ...
```

---

## Troubleshooting

### Common Issues

#### 1. Embed not found
```html
<!-- Embedr: 'embed-name' not found -->
```

**Causes:**
- Embed name misspelled
- Embed doesn't exist in database
- Wrong shortcode format

**Solutions:**
- Check embed exists: `Setup → Embedr`
- Verify name spelling (lowercase, no spaces)
- Use correct format: `{% raw %}((name)){% endraw %}` not `(name)` or `{% raw %}{{name}}{% endraw %}`

#### 2. Template not found
```
[Embedr::render] Using visual renderer (template file not found)
```

**Causes:**
- Template file doesn't exist
- Wrong path configuration
- Incorrect file permissions

**Solutions:**
- Create file: `/site/templates/components/template.php`
- Check path: `Setup → Modules → ProcessEmbedr → Components Path`
- Set permissions: `chmod 644 template.php`

#### 3. 500 Error for guests

**Cause:**
- Old module version (< 0.2.12)

**Solution:**
- Upgrade to v0.2.12+
- Check `errors` log for details
- Enable Debug Mode for full log

#### 4. No items found
```html
<!-- Embedr: No items found -->
```

**Causes:**
- Selector matches no pages
- Guest doesn't have view permission
- Template doesn't exist

**Solutions:**
- Test selector in Admin: `Setup → Embedr → Edit → Preview`
- Check page permissions: `Access → Templates → [Template] → View pages`
- Verify template exists

#### 5. Wrong image sizes

**Cause:**
- Using visual renderer (192x192) instead of PHP template

**Solution:**
- Create custom PHP template with desired sizes:
```php
<img src="<?= $page->images->first()->width(400)->url ?>">
```

### Debugging Workflow

1. **Enable Debug Mode**
```
Setup → Modules → ProcessEmbedr → Configure → ☑ Debug Mode
```

2. **Reproduce the issue**
Open the page where embed doesn't work

3. **Check logs**
```
Setup → Logs → embedr-debug (full log)
Setup → Logs → embedr-errors (errors only)
```

4. **Look for:**
- `Embed NOT FOUND` → Name mismatch
- `Template ERROR` → PHP error in template
- `Selector found 0 items` → Permission or selector issue
- `Exists: NO` → File path problem

5. **Fix and test**

6. **Disable Debug Mode** (production)

---

## API Reference

### Embedr Class

**Properties:**
```php
$embed->id          // int - Database ID
$embed->name        // string - Unique name (slug)
$embed->title       // string - Human-readable title
$embed->type_id     // int - Type ID
$embed->selector    // string - ProcessWire selector
$embed->type        // EmbedrType - Type object
```

**Methods:**
```php
$embed->render()           // string - Render to HTML
$embed->getType()          // EmbedrType|null - Get type object
$embed->getShortcode()     // string - Get {% raw %}((name)){% endraw %} tag
$embed->getCount()         // int - Count results without rendering
```

### Embedrs Class (Collection)

**Methods:**
```php
$embedrs->get($name)              // Embedr|null - Get by name
$embedrs->getById($id)            // Embedr|null - Get by ID
$embedrs->getAll($refresh=false)  // WireArray - Get all embeds
$embedrs->save(Embedr $embed)     // int|false - Save embed
$embedrs->delete($id)             // bool - Delete embed
```

### EmbedrType Class

**Properties:**
```php
$type->id           // int - Database ID
$type->name         // string - Unique name
$type->title        // string - Display title
$type->icon         // string - FA icon name
$type->template     // string - PHP template filename
$type->mode         // string - 'array' or 'single'
```

**Methods:**
```php
$type->getTemplatePath()     // string - Full path to template
$type->templateExists()      // bool - Check if file exists
```

### EmbedrTypes Class (Collection)

**Methods:**
```php
$types->get($name)               // EmbedrType|null - Get by name
$types->getById($id)             // EmbedrType|null - Get by ID
$types->getAll($refresh=false)   // WireArray - Get all types
$types->save(EmbedrType $type)   // int|false - Save type
$types->delete($id)              // bool - Delete type
```

---

## Best Practices

### 1. Naming Convention

**Embeds:**
- Use lowercase
- Use hyphens for spaces: `latest-articles`
- Be descriptive: `home-featured-products` not `products1`

**Types:**
- Singular form: `article` not `articles`
- Generic names: `product` not `wine-product`

### 2. Selectors

**Good:**
```
template=article, sort=-created, limit=6
template=product, category=wine, limit=12
parent=/products/, limit=24
```

**Avoid:**
```
template=5314  // Use template name, not ID (both work, but name is clearer)
title%=test    // Avoid test data in production
limit=1000     // Too many results
```

### 3. Template Organization

```
/site/templates/
├── components/           # Embedr templates
│   ├── articles.php
│   ├── products.php
│   └── gallery.php
├── layouts/              # Page layouts
└── partials/             # Includes
```

### 4. Performance

**Do:**
- Use reasonable limits (6-24 items)
- Cache selectors when possible
- Use image width/height parameters
- Lazy load images

**Don't:**
- Fetch 1000+ items
- Resize images in loops without caching
- Nest embeds deeply (embed inside embed)

### 5. Security

**Always:**
- Check field existence: `$page->hasField('field')`
- Sanitize output: `htmlspecialchars()`
- Validate user input
- Use guest-safe templates
- Test as guest user

**Never:**
- Assume fields exist
- Trust user input
- Expose admin functions
- Skip permission checks

---

## Support

### Reporting Bugs

Include:
- ProcessWire version
- Embedr version
- PHP version
- Debug log output
- Steps to reproduce
- Expected vs actual behavior


---

## License

MIT License - Free to use in personal and commercial projects.

---