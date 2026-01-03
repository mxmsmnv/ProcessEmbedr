# Embedr Quick Start Guide

Get up and running with Embedr in 5 minutes.

---

## 1. Install (2 minutes)

### Upload Files
```
/site/modules/Embedr/
├── ProcessEmbedr.module
├── TextformatterEmbedr.module
├── Embedr.php
├── Embedrs.php
├── EmbedrType.php
├── EmbedrTypes.php
└── EmbedrRenderer.php
```

### Install Module
```
Admin → Modules → Refresh → Embedr → Install
```

### Add Textformatter
```
Setup → Fields → body
Details → Textformatters → ☑ Embedr Text Formatter
Save
```

---

## 2. Create Your First Embed (2 minutes)

### Step 1: Create a Type
```
Setup → Embedr → Types → Add New

Name: article
Title: Article Lists
Icon: file-text
Template: (leave empty for now)
Save
```

### Step 2: Create an Embed
```
Setup → Embedr → Add New

Name: latest-news
Title: Latest News
Type: article
Selector: template=article, limit=6
Save
```

You should see a preview with 6 articles!

---

## 3. Use It (1 minute)

### In Any Page
```
Edit any page
In the body field, add:

<h2>Latest News</h2>
((latest-news))

Save
```

### View the Page
The `((latest-news))` tag will be replaced with a grid of 6 article cards!

---

## What You Get

**Out of the box:**
- ✅ Automatic card layout
- ✅ Responsive grid (1-6 columns)
- ✅ Image thumbnails (192×192)
- ✅ Title and summary
- ✅ Links to full articles
- ✅ UIKit styling

**No PHP coding required!**

---

## Next Steps

### Want Custom Designs?

Create a PHP template for full control:

**1. Create components folder:**
```bash
mkdir site/templates/components
```

**2. Create template file:**
```
File: site/templates/components/articles.php
```

**3. Add this code:**
```php
<?php namespace ProcessWire;

if(!$items->count()) return;
?>
<div class="my-articles">
    <?php foreach($items as $article): ?>
        <article>
            <?php if($article->images->count()): ?>
                <img src="<?= $article->images->first()->width(400)->url ?>" 
                     alt="<?= $article->title ?>">
            <?php endif; ?>
            
            <h3><a href="<?= $article->url ?>"><?= $article->title ?></a></h3>
            
            <?php if($article->summary): ?>
                <p><?= $article->summary ?></p>
            <?php endif; ?>
            
            <a href="<?= $article->url ?>">Read More</a>
        </article>
    <?php endforeach; ?>
</div>
```

**4. Update your type:**
```
Setup → Embedr → Types → Edit article
Template: articles.php
Save
```

**Done!** Now your embed uses your custom design with 400px images.

---

## Common Use Cases

### Latest Blog Posts
```
Selector: template=blog-post, sort=-created, limit=6
```

### Featured Products
```
Selector: template=product, featured=1, limit=12
```

### Upcoming Events
```
Selector: template=event, event_date>=today, sort=event_date
```

### Related Articles (on article pages)
```
Selector: template=article, tags=$page->tags, id!=$page->id, limit=3
```

---

## Tips

1. **Test selectors** in the Admin preview before saving
2. **Use descriptive names** like `home-featured-products` not `fp1`
3. **Create one type** for similar embeds (e.g., all article lists use `article` type)
4. **Start simple** with visual renderer, add custom templates later
5. **Test as guest** in incognito mode to ensure it works for everyone

---

## Troubleshooting

### Embed not showing?

**Check:**
1. Textformatter added to field: `Setup → Fields → body → Textformatters`
2. Correct syntax: `((name))` not `(name)` or `{{name}}`
3. Embed exists: `Setup → Embedr` (check spelling)
4. Preview works in admin
5. Cache cleared: `Setup → Advanced → Clear All Caches`

### See HTML comment instead?

```html
<!-- Embedr: 'name' not found -->
```

**Fix:** Name misspelled or embed doesn't exist

```html
<!-- Embedr: No items found -->
```

**Fix:** Selector matches no pages, adjust selector

### 500 Error for guests?

**Fix:** Upgrade to v0.2.12+ (critical fix for guest access)

---

## Resources

- **Full Documentation:** [README.md](README.md)
- **Installation Guide:** [INSTALLATION.md](INSTALLATION.md)
- **Examples:** [EXAMPLES.md](EXAMPLES.md)
- **ProcessWire Forums:** [processwire.com/talk](https://processwire.com/talk/)

---

## Get Help

1. Enable Debug Mode: `Setup → Modules → ProcessEmbedr → Configure`
2. Check logs: `Setup → Logs → embedr-debug`
3. Look for errors: `Setup → Logs → embedr-errors`
4. Ask in forums with log output

---

**You're ready to go!** 🚀

Create beautiful, dynamic content blocks in minutes with Embedr.
