<?php namespace ProcessWire;

/**
 * Embedr Renderer
 *
 * Renders items using visual card builder (no PHP templates needed)
 */
class EmbedrRenderer {

    /**
     * @var EmbedrType
     */
    protected $type;

    /**
     * @var PageArray
     */
    protected $items;

    /**
     * @var array
     */
    protected $config;

    public function __construct(EmbedrType $type, $items) {
        $this->type = $type;
        $this->items = $items;
        $this->config = $type->getConfig();
    }

    /**
     * Escape value for HTML output
     */
    protected function e($str) {
        return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Render items
     */
    public function render() {
        $layout = $this->config['layout'];
        $mode = $this->type->mode ?: 'array';

        if($mode === 'once') {
            if(!$this->items->count()) return "<!-- Embedr: No items found -->";
            return $this->renderCard($this->items->first());
        }

        switch($layout) {
            case 'grid':  return $this->renderGrid();
            case 'list':  return $this->renderList();
            case 'table': return $this->renderTable();
            default:      return $this->renderGrid();
        }
    }

    /**
     * Render grid layout
     */
    protected function renderGrid() {
        $columns = (int) $this->config['columns'];

        if(!in_array($columns, [2, 3, 4, 6])) $columns = 6;

        switch($columns) {
            case 2: $widthClass = 'uk-child-width-1-2@m'; break;
            case 3: $widthClass = 'uk-child-width-1-3@m'; break;
            case 4: $widthClass = 'uk-child-width-1-2@s uk-child-width-1-4@m'; break;
            default: $widthClass = 'uk-child-width-1-2@s uk-child-width-1-3@m uk-child-width-1-6@l';
        }

        $out = "<div class='uk-grid uk-grid-small {$widthClass} uk-grid-match' uk-grid>";
        foreach($this->items as $item) {
            $out .= "<div>{$this->renderCard($item)}</div>";
        }
        $out .= "</div>";

        return $out;
    }

    /**
     * Render list layout (UIKit)
     */
    protected function renderList() {
        $out = "<ul class='uk-list uk-list-divider'>";
        foreach($this->items as $item) {
            $out .= $this->renderListItem($item);
        }
        $out .= "</ul>";

        return $out;
    }

    /**
     * Render table layout (UIKit)
     */
    protected function renderTable() {
        $out = "<table class='uk-table uk-table-divider uk-table-small'>";
        $out .= "<thead><tr>";

        foreach($this->config['fields'] as $label => $fieldName) {
            if(empty($fieldName)) continue;
            $out .= "<th>" . $this->e(ucfirst($label)) . "</th>";
        }

        $out .= "</tr></thead><tbody>";
        foreach($this->items as $item) {
            $out .= $this->renderTableRow($item);
        }
        $out .= "</tbody></table>";

        return $out;
    }

    /**
     * Render single card
     */
    protected function renderCard($item) {
        $fields = $this->config['fields'];
        $link = $this->config['link'];
        $itemUrl  = $this->e($item->url);
        $itemTitle = $this->e($item->title);

        $out = "<div class='uk-card uk-card-default uk-card-hover' style='height: 100%;'>";

        if(!empty($fields['image'])) {
            $raw = $this->getFieldValue($item, $fields['image']);
            $imageUrl = $raw ? $this->e($this->getImageUrl($raw)) : '';
            if($imageUrl) {
                $out .= "<div class='uk-card-media-top' style='aspect-ratio: 1/1; overflow: hidden;'>";
                if($link) $out .= "<a href='{$itemUrl}'>";
                $out .= "<img src='{$imageUrl}' alt='{$itemTitle}' style='width: 100%; height: 100%; object-fit: cover;'>";
                if($link) $out .= "</a>";
                $out .= "</div>";
            }
        }

        $out .= "<div class='uk-card-body'>";

        if(!empty($fields['title'])) {
            $title = $this->e($this->getFieldValue($item, $fields['title']));
            $out .= "<h3 class='uk-card-title uk-text-small uk-margin-remove'>";
            $out .= $link ? "<a href='{$itemUrl}'>{$title}</a>" : $title;
            $out .= "</h3>";
        }

        // description may intentionally contain HTML (rich text), output as-is
        if(!empty($fields['description'])) {
            $out .= "<p class='uk-text-small uk-text-muted'>" .
                $this->getFieldValue($item, $fields['description']) . "</p>";
        }

        if(!empty($fields['meta'])) {
            $meta = $this->getFieldValue($item, $fields['meta']);
            if($meta) $out .= "<p class='uk-text-meta'>" . $this->e($meta) . "</p>";
        }

        if(!empty($fields['price'])) {
            $price = $this->getFieldValue($item, $fields['price']);
            if($price) $out .= "<p class='uk-text-bold uk-margin-small-top'>" . $this->e($price) . "</p>";
        }

        $out .= "</div></div>";

        return $out;
    }

    /**
     * Render list item (UIKit grid)
     */
    protected function renderListItem($item) {
        $fields = $this->config['fields'];
        $link = $this->config['link'];
        $itemUrl   = $this->e($item->url);
        $itemTitle = $this->e($item->title);

        $out = "<li><div class='uk-grid uk-grid-small' uk-grid>";

        if(!empty($fields['image'])) {
            $raw = $this->getFieldValue($item, $fields['image']);
            $imageUrl = $raw ? $this->e($this->getImageUrl($raw)) : '';
            if($imageUrl) {
                $out .= "<div class='uk-width-auto'>";
                if($link) $out .= "<a href='{$itemUrl}'>";
                $out .= "<img src='{$imageUrl}' alt='{$itemTitle}' style='width: 80px; height: 80px; object-fit: cover;'>";
                if($link) $out .= "</a>";
                $out .= "</div>";
            }
        }

        $out .= "<div class='uk-width-expand'>";

        if(!empty($fields['title'])) {
            $title = $this->e($this->getFieldValue($item, $fields['title']));
            $out .= "<h3 class='uk-text-lead uk-margin-remove'>";
            $out .= $link ? "<a href='{$itemUrl}'>{$title}</a>" : $title;
            $out .= "</h3>";
        }

        if(!empty($fields['description'])) {
            $out .= "<p class='uk-text-small uk-text-muted uk-margin-small-top'>" .
                $this->getFieldValue($item, $fields['description']) . "</p>";
        }

        if(!empty($fields['price'])) {
            $price = $this->getFieldValue($item, $fields['price']);
            if($price) $out .= "<p class='uk-text-bold uk-margin-small-top'>" . $this->e($price) . "</p>";
        }

        $out .= "</div></div></li>";

        return $out;
    }

    /**
     * Render table row
     */
    protected function renderTableRow($item) {
        $out = "<tr>";

        foreach($this->config['fields'] as $label => $fieldName) {
            if(empty($fieldName)) continue;

            $value = $this->getFieldValue($item, $fieldName);

            if($label === 'image' && $value) {
                $imageUrl = $this->e($this->getImageUrl($value));
                $cell = $imageUrl ? "<img src='{$imageUrl}' style='width: 48px; height: 48px; object-fit: cover;'>" : '';
            } else {
                $cell = $this->e($value);
            }

            $out .= "<td>{$cell}</td>";
        }

        $out .= "</tr>";

        return $out;
    }

    /**
     * Get field value — supports dot notation (e.g. category.title)
     */
    protected function getFieldValue($item, $fieldPath) {
        if(empty($fieldPath)) return '';

        if(strpos($fieldPath, '.') !== false) {
            $value = $item;
            foreach(explode('.', $fieldPath) as $part) {
                if(!$value) break;
                $value = $value->get($part);
            }
            return $value;
        }

        return $item->get($fieldPath);
    }

    /**
     * Get image URL from a Pageimage or Pageimages field
     */
    protected function getImageUrl($image) {
        $width  = (int) $this->config['imageWidth'];
        $height = (int) $this->config['imageHeight'];

        if($image instanceof \ProcessWire\Pageimages && $image->count()) {
            $image = $image->first();
        }

        if($image instanceof \ProcessWire\Pageimage) {
            if($width && $height) return $image->size($width, $height)->url;
            if($width)            return $image->width($width)->url;
            return $image->url;
        }

        return '';
    }
}
