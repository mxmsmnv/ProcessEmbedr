# Embedr Examples

Real-world examples and use cases for the Embedr module.

---

## Table of Contents

1. [Basic Examples](#basic-examples)
2. [Article Listings](#article-listings)
3. [Product Catalogs](#product-catalogs)
4. [Image Galleries](#image-galleries)
5. [Event Calendars](#event-calendars)
6. [User Listings](#user-listings)
7. [Advanced Examples](#advanced-examples)

---

## Basic Examples

### Example 1: Latest Blog Posts

**Type Configuration:**
```
Name: blog-post
Title: Blog Posts
Icon: file-text
Template: blog-posts.php
Mode: array
```

**Embed Configuration:**
```
Name: latest-posts
Title: Latest Blog Posts
Type: blog-post
Selector: template=blog-post, sort=-created, limit=6
```

**Template (`/site/templates/components/blog-posts.php`):**
```php
<?php namespace ProcessWire;

if(!$items->count()) return;
?>
<div class="blog-grid">
    <?php foreach($items as $post): ?>
        <article class="blog-card">
            <?php if($post->images->count()): ?>
                <a href="<?= $post->url ?>">
                    <img src="<?= $post->images->first()->width(600)->url ?>" 
                         alt="<?= $post->title ?>"
                         loading="lazy">
                </a>
            <?php endif; ?>
            
            <div class="blog-meta">
                <time datetime="<?= date('Y-m-d', $post->created) ?>">
                    <?= date('F j, Y', $post->created) ?>
                </time>
                
                <?php if($post->author): ?>
                    <span class="author">By <?= $post->author->name ?></span>
                <?php endif; ?>
            </div>
            
            <h3>
                <a href="<?= $post->url ?>"><?= $post->title ?></a>
            </h3>
            
            <?php if($post->summary): ?>
                <p><?= $post->summary ?></p>
            <?php endif; ?>
            
            <a href="<?= $post->url ?>" class="read-more">
                Read More →
            </a>
        </article>
    <?php endforeach; ?>
</div>

<style>
.blog-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
}
.blog-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s;
}
.blog-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.blog-card img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}
.blog-meta {
    padding: 1rem 1.5rem 0;
    font-size: 0.875rem;
    color: #666;
}
.blog-card h3 {
    padding: 0.5rem 1.5rem;
    margin: 0;
}
.blog-card p {
    padding: 0 1.5rem;
    color: #555;
}
.read-more {
    display: inline-block;
    padding: 1rem 1.5rem;
    color: #0066cc;
    text-decoration: none;
}
</style>
```

**Usage:**
```html
<h2>Latest from Our Blog</h2>
((latest-posts))
```

---

## Article Listings

### Example 2: Category-Specific Articles

**Multiple Embeds for Different Categories:**

```
Name: tech-articles
Selector: template=article, category=technology, limit=8

Name: business-articles
Selector: template=article, category=business, limit=8

Name: lifestyle-articles
Selector: template=article, category=lifestyle, limit=8
```

**Template (`articles.php`):**
```php
<?php namespace ProcessWire;

if(!$items->count()) return;

// Get category from first item (they should all be the same)
$category = $items->first()->category;
?>
<section class="article-section">
    <?php if($category): ?>
        <h2 class="category-title">
            <?= $category->title ?>
        </h2>
    <?php endif; ?>
    
    <div class="article-list">
        <?php foreach($items as $article): ?>
            <article class="article-item">
                <div class="article-content">
                    <?php if($article->hasField('date') && $article->date): ?>
                        <time datetime="<?= date('Y-m-d', $article->date) ?>">
                            <?= date('M j, Y', $article->date) ?>
                        </time>
                    <?php endif; ?>
                    
                    <h3>
                        <a href="<?= $article->url ?>"><?= $article->title ?></a>
                    </h3>
                    
                    <?php if($article->hasField('excerpt') && $article->excerpt): ?>
                        <p class="excerpt"><?= $article->excerpt ?></p>
                    <?php endif; ?>
                    
                    <?php if($article->hasField('tags') && $article->tags->count()): ?>
                        <div class="tags">
                            <?php foreach($article->tags as $tag): ?>
                                <span class="tag"><?= $tag->title ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if($article->hasField('images') && $article->images->count()): ?>
                    <div class="article-image">
                        <a href="<?= $article->url ?>">
                            <img src="<?= $article->images->first()->width(300)->url ?>" 
                                 alt="<?= $article->title ?>">
                        </a>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    
    <?php if($category): ?>
        <a href="<?= $category->url ?>" class="view-all">
            View All <?= $category->title ?> Articles →
        </a>
    <?php endif; ?>
</section>
```

---

## Product Catalogs

### Example 3: Featured Products

**Type Configuration:**
```
Name: product
Title: Products
Icon: shopping-cart
Template: products.php
Mode: array
```

**Embed Configuration:**
```
Name: featured-products
Title: Featured Products
Type: product
Selector: template=product, featured=1, limit=12
```

**Template (`products.php`):**
```php
<?php namespace ProcessWire;

if(!$items->count()) return;
?>
<div class="product-grid">
    <?php foreach($items as $product): ?>
        <div class="product-card">
            <?php if($product->images->count()): ?>
                <div class="product-image">
                    <a href="<?= $product->url ?>">
                        <img src="<?= $product->images->first()->size(400, 400)->url ?>" 
                             alt="<?= $product->title ?>"
                             loading="lazy">
                    </a>
                    
                    <?php if($product->hasField('sale') && $product->sale): ?>
                        <span class="badge sale-badge">Sale</span>
                    <?php endif; ?>
                    
                    <?php if($product->hasField('new') && $product->new): ?>
                        <span class="badge new-badge">New</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="product-info">
                <?php if($product->hasField('brand') && $product->brand): ?>
                    <span class="product-brand"><?= $product->brand ?></span>
                <?php endif; ?>
                
                <h3 class="product-title">
                    <a href="<?= $product->url ?>"><?= $product->title ?></a>
                </h3>
                
                <?php if($product->hasField('rating') && $product->rating): ?>
                    <div class="product-rating">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= $product->rating ? 'filled' : '' ?>">★</span>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
                
                <div class="product-price">
                    <?php if($product->hasField('sale_price') && $product->sale_price): ?>
                        <span class="original-price">$<?= number_format($product->price, 2) ?></span>
                        <span class="sale-price">$<?= number_format($product->sale_price, 2) ?></span>
                    <?php else: ?>
                        <span class="price">$<?= number_format($product->price, 2) ?></span>
                    <?php endif; ?>
                </div>
                
                <a href="<?= $product->url ?>" class="btn-view-product">
                    View Product
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 2rem;
}
.product-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
}
.product-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.product-image {
    position: relative;
    overflow: hidden;
}
.product-image img {
    width: 100%;
    transition: transform 0.3s;
}
.product-card:hover .product-image img {
    transform: scale(1.05);
}
.badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 4px 12px;
    font-size: 12px;
    font-weight: bold;
    border-radius: 4px;
}
.sale-badge {
    background: #e74c3c;
    color: white;
}
.new-badge {
    background: #2ecc71;
    color: white;
}
.product-info {
    padding: 1rem;
}
.product-brand {
    display: block;
    font-size: 0.875rem;
    color: #666;
    margin-bottom: 0.5rem;
}
.product-title {
    margin: 0 0 0.5rem 0;
    font-size: 1rem;
}
.product-title a {
    color: #333;
    text-decoration: none;
}
.product-rating .star {
    color: #ddd;
}
.product-rating .star.filled {
    color: #ffa500;
}
.product-price {
    margin: 1rem 0;
    font-size: 1.25rem;
    font-weight: bold;
}
.original-price {
    text-decoration: line-through;
    color: #999;
    font-size: 1rem;
    margin-right: 0.5rem;
}
.sale-price {
    color: #e74c3c;
}
.btn-view-product {
    display: block;
    text-align: center;
    padding: 0.75rem;
    background: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    transition: background 0.2s;
}
.btn-view-product:hover {
    background: #2980b9;
}
</style>
```

---

## Image Galleries

### Example 4: Photo Gallery

**Embed Configuration:**
```
Name: gallery-recent
Title: Recent Photos
Type: gallery
Selector: template=photo, sort=-created, limit=20
```

**Template (`gallery.php`):**
```php
<?php namespace ProcessWire;

if(!$items->count()) return;
?>
<div class="photo-gallery">
    <?php foreach($items as $photo): ?>
        <?php if($photo->images->count()): ?>
            <div class="gallery-item">
                <a href="<?= $photo->images->first()->url ?>" 
                   data-lightbox="gallery"
                   data-title="<?= $photo->title ?>">
                    <img src="<?= $photo->images->first()->size(400, 300)->url ?>" 
                         alt="<?= $photo->title ?>"
                         loading="lazy">
                    <div class="gallery-overlay">
                        <span class="gallery-title"><?= $photo->title ?></span>
                    </div>
                </a>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<style>
.photo-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}
.gallery-item {
    position: relative;
    overflow: hidden;
    border-radius: 4px;
    aspect-ratio: 4/3;
}
.gallery-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}
.gallery-item:hover img {
    transform: scale(1.1);
}
.gallery-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.7));
    padding: 2rem 1rem 1rem;
    opacity: 0;
    transition: opacity 0.3s;
}
.gallery-item:hover .gallery-overlay {
    opacity: 1;
}
.gallery-title {
    color: white;
    font-weight: bold;
}
</style>
```

---

## Event Calendars

### Example 5: Upcoming Events

**Embed Configuration:**
```
Name: upcoming-events
Title: Upcoming Events
Type: event
Selector: template=event, event_date>=today, sort=event_date, limit=10
```

**Template (`events.php`):**
```php
<?php namespace ProcessWire;

if(!$items->count()) {
    echo "<p>No upcoming events at this time.</p>";
    return;
}
?>
<div class="events-list">
    <?php foreach($items as $event): ?>
        <article class="event-item">
            <div class="event-date">
                <span class="month"><?= date('M', $event->event_date) ?></span>
                <span class="day"><?= date('d', $event->event_date) ?></span>
            </div>
            
            <div class="event-content">
                <h3>
                    <a href="<?= $event->url ?>"><?= $event->title ?></a>
                </h3>
                
                <div class="event-meta">
                    <span class="event-time">
                        <i class="fa fa-clock"></i>
                        <?= date('g:i A', $event->event_date) ?>
                    </span>
                    
                    <?php if($event->hasField('location') && $event->location): ?>
                        <span class="event-location">
                            <i class="fa fa-map-marker"></i>
                            <?= $event->location ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if($event->hasField('summary') && $event->summary): ?>
                    <p class="event-summary"><?= $event->summary ?></p>
                <?php endif; ?>
                
                <a href="<?= $event->url ?>" class="event-link">
                    Learn More →
                </a>
            </div>
            
            <?php if($event->images->count()): ?>
                <div class="event-image">
                    <img src="<?= $event->images->first()->width(200)->url ?>" 
                         alt="<?= $event->title ?>">
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
```

---

## User Listings

### Example 6: Team Members

**Embed Configuration:**
```
Name: team-members
Title: Our Team
Type: user
Selector: template=user, roles=team-member, sort=sort
```

**Template (`team.php`):**
```php
<?php namespace ProcessWire;

if(!$items->count()) return;
?>
<div class="team-grid">
    <?php foreach($items as $member): ?>
        <div class="team-member">
            <?php if($member->hasField('photo') && $member->photo): ?>
                <div class="member-photo">
                    <img src="<?= $member->photo->size(300, 300)->url ?>" 
                         alt="<?= $member->title ?>">
                </div>
            <?php else: ?>
                <div class="member-photo placeholder">
                    <span class="initials">
                        <?= strtoupper(substr($member->title, 0, 2)) ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <h3 class="member-name"><?= $member->title ?></h3>
            
            <?php if($member->hasField('position') && $member->position): ?>
                <p class="member-position"><?= $member->position ?></p>
            <?php endif; ?>
            
            <?php if($member->hasField('bio') && $member->bio): ?>
                <p class="member-bio"><?= $sanitizer->truncate($member->bio, 150) ?></p>
            <?php endif; ?>
            
            <div class="member-social">
                <?php if($member->hasField('email') && $member->email): ?>
                    <a href="mailto:<?= $member->email ?>" title="Email">
                        <i class="fa fa-envelope"></i>
                    </a>
                <?php endif; ?>
                
                <?php if($member->hasField('linkedin') && $member->linkedin): ?>
                    <a href="<?= $member->linkedin ?>" target="_blank" title="LinkedIn">
                        <i class="fa fa-linkedin"></i>
                    </a>
                <?php endif; ?>
                
                <?php if($member->hasField('twitter') && $member->twitter): ?>
                    <a href="<?= $member->twitter ?>" target="_blank" title="Twitter">
                        <i class="fa fa-twitter"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
```

---

## Advanced Examples

### Example 7: Related Posts

**Embed Configuration:**
```
Name: related-posts
Title: Related Posts
Type: article
Selector: template=article, tags=$page->tags, id!=$page->id, limit=3
```

**Note:** This uses `$page` context, which refers to the current page where the embed is rendered.

---

### Example 8: Conditional Content

**Template with conditions:**
```php
<?php namespace ProcessWire;

if(!$items->count()) return;

// Get current user
$user = $this->wire('user');
$isLoggedIn = !$user->isGuest();
?>
<div class="content-list">
    <?php foreach($items as $item): ?>
        <article class="item">
            <h3><?= $item->title ?></h3>
            
            <?php if($isLoggedIn): ?>
                <!-- Full content for logged-in users -->
                <?php if($item->hasField('body') && $item->body): ?>
                    <div class="full-content">
                        <?= $item->body ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Summary for guests -->
                <?php if($item->hasField('summary') && $item->summary): ?>
                    <p><?= $item->summary ?></p>
                    <p><em>Sign in to read the full article.</em></p>
                <?php endif; ?>
            <?php endif; ?>
            
            <a href="<?= $item->url ?>">Read More</a>
        </article>
    <?php endforeach; ?>
</div>
```

---

### Example 9: Multi-Template Selector

**Embed that finds multiple template types:**
```
Name: all-content
Title: All Recent Content
Selector: template=article|blog-post|news, sort=-created, limit=10
```

**Template handles different types:**
```php
<?php namespace ProcessWire;

if(!$items->count()) return;
?>
<div class="mixed-content">
    <?php foreach($items as $item): ?>
        <article class="content-item type-<?= $item->template->name ?>">
            <?php
            // Different icon per type
            $icons = [
                'article' => 'file-text',
                'blog-post' => 'pencil',
                'news' => 'newspaper',
            ];
            $icon = $icons[$item->template->name] ?? 'file';
            ?>
            
            <i class="fa fa-<?= $icon ?>"></i>
            
            <div class="item-content">
                <span class="item-type"><?= $item->template->label ?></span>
                <h3><a href="<?= $item->url ?>"><?= $item->title ?></a></h3>
                
                <?php if($item->hasField('summary') && $item->summary): ?>
                    <p><?= $item->summary ?></p>
                <?php endif; ?>
            </div>
            
            <time datetime="<?= date('Y-m-d', $item->created) ?>">
                <?= date('M j, Y', $item->created) ?>
            </time>
        </article>
    <?php endforeach; ?>
</div>
```

---

### Example 10: Pagination

**Template with pagination:**
```php
<?php namespace ProcessWire;

// Set items per page
$limit = 12;
$start = ($input->pageNum - 1) * $limit;

// Override selector to add pagination
$selector = str_replace("limit={$embed->selector_limit}", "start=$start, limit=$limit", $embed->selector);
$paginatedItems = $pages->find($selector);

if(!$paginatedItems->count()) {
    echo "<p>No items found.</p>";
    return;
}
?>
<div class="items-grid">
    <?php foreach($paginatedItems as $item): ?>
        <div class="item">
            <h3><a href="<?= $item->url ?>"><?= $item->title ?></a></h3>
        </div>
    <?php endforeach; ?>
</div>

<?php if($paginatedItems->getTotal() > $limit): ?>
    <nav class="pagination">
        <?= $paginatedItems->renderPager() ?>
    </nav>
<?php endif; ?>
```

---

## CSS Framework Examples

### Using Tailwind CSS

```php
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach($items as $item): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition">
            <?php if($item->images->count()): ?>
                <img src="<?= $item->images->first()->width(400)->url ?>" 
                     class="w-full h-48 object-cover"
                     alt="<?= $item->title ?>">
            <?php endif; ?>
            
            <div class="p-4">
                <h3 class="text-xl font-bold mb-2">
                    <a href="<?= $item->url ?>" class="text-gray-900 hover:text-blue-600">
                        <?= $item->title ?>
                    </a>
                </h3>
                
                <?php if($item->summary): ?>
                    <p class="text-gray-600 mb-4"><?= $item->summary ?></p>
                <?php endif; ?>
                
                <a href="<?= $item->url ?>" 
                   class="inline-block bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Read More
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
```

### Using Bootstrap

```php
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
    <?php foreach($items as $item): ?>
        <div class="col">
            <div class="card h-100">
                <?php if($item->images->count()): ?>
                    <img src="<?= $item->images->first()->width(400)->url ?>" 
                         class="card-img-top"
                         alt="<?= $item->title ?>">
                <?php endif; ?>
                
                <div class="card-body">
                    <h5 class="card-title">
                        <a href="<?= $item->url ?>"><?= $item->title ?></a>
                    </h5>
                    
                    <?php if($item->summary): ?>
                        <p class="card-text"><?= $item->summary ?></p>
                    <?php endif; ?>
                    
                    <a href="<?= $item->url ?>" class="btn btn-primary">
                        Read More
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
```

---

## Tips for Creating Templates

1. **Always check field existence** - Use `$page->hasField('fieldname')`
2. **Provide fallbacks** - Show placeholders when content is missing
3. **Use lazy loading** - Add `loading="lazy"` to images
4. **Optimize images** - Use appropriate sizes with `->width()` or `->size()`
5. **Test as guest** - Always test in incognito mode
6. **Keep it simple** - Start with basic layouts, add complexity as needed
7. **Use comments** - Document your templates for future reference
8. **Handle empty states** - Show meaningful messages when no items found

---

More examples and community templates available in the [Embedr GitHub repository](https://github.com/your-repo/embedr).
