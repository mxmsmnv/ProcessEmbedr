<?php namespace ProcessWire;

/**
 * Embedr
 * 
 * Represents a single embed instance
 * 
 * @property int $id
 * @property string $name
 * @property string $title
 * @property int $type_id
 * @property EmbedrType $type
 * @property string $selector
 * @property int $created
 * @property int $modified
 */
class Embedr extends WireData {
    
    /**
     * Default values
     * 
     * @var array
     */
    protected $defaults = [
        'id' => 0,
        'name' => '',
        'title' => '',
        'type_id' => 0,
        'selector' => '',
        'created' => 0,
        'modified' => 0,
    ];
    
    /**
     * Type object
     * 
     * @var EmbedrType|null
     */
    protected $typeObject = null;
    
    /**
     * Construct
     */
    public function __construct() {
        $this->setArray($this->defaults);
        parent::__construct();
    }
    
    /**
     * Set property
     * 
     * @param string $key
     * @param mixed $value
     * @return self|WireData
     */
    public function set($key, $value) {
        if($key === 'type' && $value instanceof EmbedrType) {
            $this->typeObject = $value;
            $this->type_id = $value->id;
            return $this;
        }
        
        // CRITICAL: Preserve ID - never allow existing ID to be overwritten with 0
        if($key === 'id') {
            $currentId = (int) $this->get('id');
            $newId = (int) $value;
            
            // Debug logging if module available
            $debugMode = false;
            try {
                $config = $this->wire('modules')->getModuleConfigData('ProcessEmbedr');
                $debugMode = !empty($config['debugMode']);
            } catch(\Exception $e) {
                // Config not accessible, continue without debug
            }
            
            if($debugMode) {
                $this->wire('log')->save('embedr-debug', sprintf(
                    '[Embedr::set] ID change attempt | Current=%s, New=%s',
                    $currentId,
                    $newId
                ));
            }
            
            // If we have an existing ID and new value is 0, keep the existing ID
            if($currentId > 0 && $newId === 0) {
                if($debugMode) {
                    $this->wire('log')->save('embedr-debug', '[Embedr::set] BLOCKED - preventing ID reset to 0');
                }
                return $this;
            }
        }
        
        if(isset($this->defaults[$key]) && is_int($this->defaults[$key])) {
            $value = (int) $value;
        }
        
        return parent::set($key, $value);
    }
    
    /**
     * Get property
     * 
     * @param string $key
     * @return mixed
     */
    public function get($key) {
        if($key === 'type') {
            return $this->getType();
        }
        
        return parent::get($key);
    }
    
    /**
     * Get type object
     * 
     * @return EmbedrType|null
     */
    public function getType() {
        if($this->typeObject !== null) {
            return $this->typeObject;
        }
        
        if(!$this->type_id) {
            return null;
        }
        
        $types = $this->wire(new EmbedrTypes());
        $this->typeObject = $types->getById($this->type_id);
        
        return $this->typeObject;
    }
    
    /**
     * Render this embed
     * 
     * @return string
     */
    public function render() {
        // Get debug mode safely without loading Process module
        $debugMode = false;
        try {
            $config = $this->wire('modules')->getModuleConfigData('ProcessEmbedr');
            $debugMode = !empty($config['debugMode']);
        } catch(\Exception $e) {
            // Config not accessible, continue without debug
        }
        
        // Get current user info
        $user = $this->wire('user');
        $userName = $user ? $user->name : 'unknown';
        $isGuest = $user ? $user->isGuest() : true;
        
        if($debugMode) {
            $this->wire('log')->save('embedr-debug', sprintf(
                '[Embedr::render] Starting | Embed=%s (ID=%s), Selector=%s | User=%s (guest=%s)',
                $this->name,
                $this->id,
                $this->selector,
                $userName,
                $isGuest ? 'YES' : 'NO'
            ));
        }
        
        $type = $this->getType();
        
        if(!$type) {
            if($debugMode) {
                $this->wire('log')->save('embedr-debug', '[Embedr::render] ERROR: Type not found');
            }
            return "<!-- Embedr: Type not found -->";
        }
        
        if($debugMode) {
            $this->wire('log')->save('embedr-debug', sprintf(
                '[Embedr::render] Type loaded | Name=%s, Template=%s, Mode=%s',
                $type->name,
                $type->template ?: 'none',
                $type->mode ?: 'array'
            ));
        }
        
        try {
            // Execute selector
            if($debugMode) {
                $this->wire('log')->save('embedr-debug', sprintf(
                    '[Embedr::render] Executing selector: %s',
                    $this->selector
                ));
            }
            
            $items = $this->wire('pages')->find($this->selector);
            
            if($debugMode) {
                $this->wire('log')->save('embedr-debug', sprintf(
                    '[Embedr::render] Selector found %d items',
                    $items->count()
                ));
            }
            
            if(!$items->count()) {
                if($debugMode) {
                    $this->wire('log')->save('embedr-debug', '[Embedr::render] No items found - returning empty comment');
                }
                return "<!-- Embedr: No items found -->";
            }
            
            // Check if custom template exists
            if($type->template && $type->templateExists()) {
                $templatePath = $type->getTemplatePath();
                
                if($debugMode) {
                    $this->wire('log')->save('embedr-debug', sprintf(
                        '[Embedr::render] Using PHP template: %s | Exists: %s',
                        $templatePath,
                        file_exists($templatePath) ? 'YES' : 'NO'
                    ));
                }
                
                // Use custom PHP template with error handling
                try {
                    $page = $this->wire('page');
                    $config = $this->wire('config');
                    $input = $this->wire('input');
                    $sanitizer = $this->wire('sanitizer');
                    $embed = $this;
                    
                    ob_start();
                    
                    // Wrap include in try-catch to catch template errors
                    try {
                        include $templatePath;
                    } catch(\Throwable $e) {
                        ob_end_clean();
                        throw $e;
                    }
                    
                    $output = ob_get_clean();
                    
                    if($debugMode) {
                        $this->wire('log')->save('embedr-debug', sprintf(
                            '[Embedr::render] PHP template rendered (%d chars)',
                            strlen($output)
                        ));
                    }
                    
                    return $output;
                    
                } catch(\Throwable $e) {
                    // Log to both debug log and error log
                    $errorMsg = sprintf(
                        '[Embedr::render] Template ERROR: %s in %s:%d',
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine()
                    );
                    
                    if($debugMode) {
                        $this->wire('log')->save('embedr-debug', $errorMsg);
                    }
                    
                    // Always log errors to separate error log
                    $this->wire('log')->save('embedr-errors', sprintf(
                        'Template: %s | Embed: %s | User: %s | Error: %s',
                        $templatePath,
                        $this->name,
                        $this->wire('user')->name,
                        $e->getMessage()
                    ));
                    
                    // Return error comment instead of breaking the page
                    return sprintf(
                        "<!-- Embedr Template Error: %s -->",
                        htmlspecialchars($e->getMessage())
                    );
                }
            } else {
                if($debugMode) {
                    $reason = $type->template ? 'template file not found' : 'no template specified';
                    $this->wire('log')->save('embedr-debug', sprintf(
                        '[Embedr::render] Using visual renderer (%s)',
                        $reason
                    ));
                }
                
                // Use visual renderer
                require_once(__DIR__ . '/EmbedrRenderer.php');
                $renderer = new EmbedrRenderer($type, $items);
                $output = $renderer->render();
                
                if($debugMode) {
                    $this->wire('log')->save('embedr-debug', sprintf(
                        '[Embedr::render] Visual renderer output (%d chars)',
                        strlen($output)
                    ));
                }
                
                return $output;
            }
            
        } catch(\Exception $e) {
            $errorMsg = sprintf(
                '[Embedr::render] EXCEPTION: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            
            if($debugMode) {
                $this->wire('log')->save('embedr-debug', $errorMsg);
            }
            
            // Always log to error log
            $this->wire('log')->save('embedr-errors', sprintf(
                'Embed: %s | User: %s | Selector: %s | Error: %s',
                $this->name,
                $this->wire('user')->name,
                $this->selector,
                $e->getMessage()
            ));
            
            return "<!-- Embedr Error: {$e->getMessage()} -->";
        }
    }
    
    /**
     * Get result count without rendering
     * 
     * @return int
     */
    public function getCount() {
        try {
            return $this->wire('pages')->count($this->selector);
        } catch(\Exception $e) {
            return 0;
        }
    }
    
    /**
     * Validate embed data
     * 
     * @return bool|string Returns true if valid, error message if not
     */
    public function validate() {
        if(empty($this->name)) {
            return 'Name is required';
        }
        
        if(!preg_match('/^[a-z][a-z0-9_-]*$/i', $this->name)) {
            return 'Name must start with a letter and contain only letters, numbers, hyphens and underscores';
        }
        
        if(empty($this->title)) {
            return 'Title is required';
        }
        
        if(!$this->type_id) {
            return 'Type is required';
        }
        
        $type = $this->getType();
        if(!$type) {
            return 'Invalid type';
        }
        
        if(empty($this->selector)) {
            return 'Selector is required';
        }
        
        // Validate selector syntax
        try {
            $this->wire('pages')->find($this->selector);
        } catch(\Exception $e) {
            return 'Invalid selector: ' . $e->getMessage();
        }
        
        return true;
    }
    
    /**
     * Get shortcode for this embed
     * 
     * @return string
     */
    public function getShortcode() {
        $embedr = $this->wire('modules')->get('ProcessEmbedr');
        $openTag = $embedr ? $embedr->openTag : '((';
        $closeTag = $embedr ? $embedr->closeTag : '))';
        
        return $openTag . $this->name . $closeTag;
    }
    
    /**
     * String representation
     * 
     * @return string
     */
    public function __toString() {
        return $this->title ?: $this->name;
    }
}
