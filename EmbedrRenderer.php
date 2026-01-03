<?php namespace ProcessWire;

/**
 * Embedr Renderer
 * 
 * Renders items using visual card builder (no PHP templates needed)
 */
class EmbedrRenderer {
    
    /**
     * Type
     * 
     * @var EmbedrType
     */
    protected $type;
    
    /**
     * Items to render
     * 
     * @var PageArray
     */
    protected $items;
    
    /**
     * Config
     * 
     * @var array
     */
    protected $config;
    
    /**
     * Construct
     * 
     * @param EmbedrType $type
     * @param PageArray $items
     */
    public function __construct(EmbedrType $type, $items) {
        $this->type = $type;
        $this->items = $items;
        $this->config = $type->getConfig();
    }
    
    /**
     * Render items
     * 
     * @return string
     */
    public function render() {
        $layout = $this->config['layout'];
        $mode = $this->type->mode ?: 'array';
        
        // If mode is 'once', show only first item as single card
        if($mode === 'once') {
            if(!$this->items->count()) {
                return "<!-- Embedr: No items found -->";
            }
            return $this->renderCard($this->items->first());
        }
        
        // Array mode - show multiple items
        switch($layout) {
            case 'grid':
                return $this->renderGrid();
            case 'list':
                return $this->renderList();
            case 'table':
                return $this->renderTable();
            default:
                return $this->renderGrid();
        }
    }
    
    /**
     * Render grid layout
     * 
     * @return string
     */
    protected function renderGrid() {
        $columns = (int) $this->config['columns'];
        
        // UIKit supports 2, 3, 4, 6 - default to 6 for anything else
        if(!in_array($columns, [2, 3, 4, 6])) {
            $columns = 6;
        }
        
        // UIKit responsive grid classes
        switch($columns) {
            case 2:
                $widthClass = 'uk-child-width-1-2@m';
                break;
            case 3:
                $widthClass = 'uk-child-width-1-3@m';
                break;
            case 4:
                $widthClass = 'uk-child-width-1-2@s uk-child-width-1-4@m';
                break;
            case 6:
                $widthClass = 'uk-child-width-1-2@s uk-child-width-1-3@m uk-child-width-1-6@l';
                break;
            default:
                $widthClass = 'uk-child-width-1-2@s uk-child-width-1-3@m uk-child-width-1-6@l';
        }
        
        $out = "<div class='uk-grid uk-grid-small {$widthClass} uk-grid-match' uk-grid>";
        
        foreach($this->items as $item) {
            $out .= "<div>{$this->renderCard($item)}</div>";
        }
        
        $out .= "</div>";
        
        return $out;
    }
    
    /**
     * Render list layout
     * 
     * @return string
     */
    protected function renderList() {
        $out = "<div class='embedr-list space-y-4'>";
        
        foreach($this->items as $item) {
            $out .= $this->renderListItem($item);
        }
        
        $out .= "</div>";
        
        return $out;
    }
    
    /**
     * Render table layout
     * 
     * @return string
     */
    protected function renderTable() {
        $out = "<table class='embedr-table w-full'>";
        $out .= "<thead><tr>";
        
        $fields = $this->config['fields'];
        foreach($fields as $label => $fieldName) {
            if(empty($fieldName)) continue;
            $out .= "<th>" . ucfirst($label) . "</th>";
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
     * 
     * @param Page $item
     * @return string
     */
    protected function renderCard($item) {
        $fields = $this->config['fields'];
        $link = $this->config['link'];
        
        $out = "<div class='uk-card uk-card-default uk-card-hover' style='height: 100%;'>";
        
        // Image
        if(!empty($fields['image'])) {
            $image = $this->getFieldValue($item, $fields['image']);
            if($image) {
                $imageUrl = $this->getImageUrl($image);
                if($imageUrl) {
                    $out .= "<div class='uk-card-media-top' style='aspect-ratio: 1/1; overflow: hidden;'>";
                    if($link) $out .= "<a href='{$item->url}'>";
                    $out .= "<img src='{$imageUrl}' alt='{$item->title}' style='width: 100%; height: 100%; object-fit: cover;'>";
                    if($link) $out .= "</a>";
                    $out .= "</div>";
                }
            }
        }
        
        $out .= "<div class='uk-card-body'>";
        
        // Title
        if(!empty($fields['title'])) {
            $title = $this->getFieldValue($item, $fields['title']);
            $out .= "<h3 class='uk-card-title uk-text-small uk-margin-remove'>";
            if($link) {
                $out .= "<a href='{$item->url}'>{$title}</a>";
            } else {
                $out .= $title;
            }
            $out .= "</h3>";
        }
        
        // Description
        if(!empty($fields['description'])) {
            $description = $this->getFieldValue($item, $fields['description']);
            $out .= "<p class='uk-text-small uk-text-muted'>{$description}</p>";
        }
        
        // Meta
        if(!empty($fields['meta'])) {
            $meta = $this->getFieldValue($item, $fields['meta']);
            if($meta) {
                $out .= "<p class='uk-text-meta'>{$meta}</p>";
            }
        }
        
        // Price
        if(!empty($fields['price'])) {
            $price = $this->getFieldValue($item, $fields['price']);
            if($price) {
                $out .= "<p class='uk-text-bold uk-margin-small-top'>{$price}</p>";
            }
        }
        
        $out .= "</div>";
        $out .= "</div>";
        
        return $out;
    }
    
    /**
     * Render list item
     * 
     * @param Page $item
     * @return string
     */
    protected function renderListItem($item) {
        $fields = $this->config['fields'];
        $link = $this->config['link'];
        
        $out = "<div class='embedr-list-item flex gap-4 border-b pb-4'>";
        
        // Image
        if(!empty($fields['image'])) {
            $image = $this->getFieldValue($item, $fields['image']);
            if($image) {
                $imageUrl = $this->getImageUrl($image);
                if($imageUrl) {
                    $out .= "<div class='embedr-list-image flex-shrink-0'>";
                    if($link) $out .= "<a href='{$item->url}'>";
                    $out .= "<img src='{$imageUrl}' alt='{$item->title}' class='w-24 h-24 object-cover rounded'>";
                    if($link) $out .= "</a>";
                    $out .= "</div>";
                }
            }
        }
        
        $out .= "<div class='embedr-list-content flex-1'>";
        
        // Title
        if(!empty($fields['title'])) {
            $title = $this->getFieldValue($item, $fields['title']);
            $out .= "<h3 class='text-lg font-bold'>";
            if($link) {
                $out .= "<a href='{$item->url}' class='hover:underline'>{$title}</a>";
            } else {
                $out .= $title;
            }
            $out .= "</h3>";
        }
        
        // Description
        if(!empty($fields['description'])) {
            $description = $this->getFieldValue($item, $fields['description']);
            $out .= "<p class='text-sm text-gray-600 mt-1'>{$description}</p>";
        }
        
        // Price
        if(!empty($fields['price'])) {
            $price = $this->getFieldValue($item, $fields['price']);
            if($price) {
                $out .= "<p class='text-lg font-bold mt-2'>{$price}</p>";
            }
        }
        
        $out .= "</div>";
        $out .= "</div>";
        
        return $out;
    }
    
    /**
     * Render table row
     * 
     * @param Page $item
     * @return string
     */
    protected function renderTableRow($item) {
        $fields = $this->config['fields'];
        
        $out = "<tr>";
        
        foreach($fields as $label => $fieldName) {
            if(empty($fieldName)) continue;
            
            $value = $this->getFieldValue($item, $fieldName);
            
            if($label === 'image' && $value) {
                $imageUrl = $this->getImageUrl($value);
                $value = $imageUrl ? "<img src='{$imageUrl}' class='w-12 h-12 object-cover'>" : '';
            }
            
            $out .= "<td class='p-2'>{$value}</td>";
        }
        
        $out .= "</tr>";
        
        return $out;
    }
    
    /**
     * Get field value (supports dot notation)
     * 
     * @param Page $item
     * @param string $fieldPath
     * @return mixed
     */
    protected function getFieldValue($item, $fieldPath) {
        if(empty($fieldPath)) return '';
        
        // Support dot notation: category.title
        if(strpos($fieldPath, '.') !== false) {
            $parts = explode('.', $fieldPath);
            $value = $item;
            
            foreach($parts as $part) {
                if(!$value) break;
                $value = $value->get($part);
            }
            
            return $value;
        }
        
        return $item->get($fieldPath);
    }
    
    /**
     * Get image URL
     * 
     * @param mixed $image
     * @return string
     */
    protected function getImageUrl($image) {
        $width = (int) $this->config['imageWidth'];
        $height = (int) $this->config['imageHeight'];
        
        // If it's a Pageimages field, get first image
        if($image instanceof \ProcessWire\Pageimages && $image->count()) {
            $image = $image->first();
        }
        
        // If it's a Pageimage
        if($image instanceof \ProcessWire\Pageimage) {
            if($width && $height) {
                return $image->size($width, $height)->url;
            } else if($width) {
                return $image->width($width)->url;
            }
            return $image->url;
        }
        
        return '';
    }
    
    /**
     * Get CSS column class for grid
     * 
     * @param int $columns
     * @return string
     */
    protected function getColumnClass($columns) {
        // UIKit uses uk-child-width-1-{n}@m format
        // Already handled in renderGrid
        return '';
    }
}
