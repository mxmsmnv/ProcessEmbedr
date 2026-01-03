<?php namespace ProcessWire;

/**
 * Embedr Type
 * 
 * Represents a type of embed block (product, article, brand, etc.)
 * 
 * @property int $id
 * @property string $name
 * @property string $title
 * @property string $template
 * @property string $icon
 * @property int $sort
 */
class EmbedrType extends WireData {
    
    /**
     * Default values
     * 
     * @var array
     */
    protected $defaults = [
        'id' => 0,
        'name' => '',
        'title' => '',
        'template' => '',
        'icon' => '',
        'sort' => 0,
        'config' => '',
        'mode' => 'array',
    ];
    
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
        // CRITICAL: Preserve ID - never allow existing ID to be overwritten with 0
        if($key === 'id') {
            $currentId = (int) $this->get('id');
            $newId = (int) $value;
            
            // If we have an existing ID and new value is 0, keep the existing ID
            if($currentId > 0 && $newId === 0) {
                return $this;
            }
        }
        
        if(isset($this->defaults[$key]) && is_int($this->defaults[$key])) {
            $value = (int) $value;
        }
        return parent::set($key, $value);
    }
    
    /**
     * Get full template path
     * 
     * @return string
     */
    public function getTemplatePath() {
        $config = $this->wire('config');
        
        // Get components path from ProcessEmbedr module settings
        // Use getModuleConfigData to avoid permission issues for guests
        $componentsPath = 'components/'; // Default
        
        try {
            $moduleConfig = $this->wire('modules')->getModuleConfigData('ProcessEmbedr');
            if(!empty($moduleConfig['componentsPath'])) {
                $componentsPath = $moduleConfig['componentsPath'];
            }
        } catch(\Exception $e) {
            // Config not accessible - use default
        }
        
        return $config->paths->templates . $componentsPath . $this->template;
    }
    
    /**
     * Check if template file exists
     * 
     * @return bool
     */
    public function templateExists() {
        if(empty($this->template)) {
            return false;
        }
        return file_exists($this->getTemplatePath());
    }
    
    /**
     * Validate type data
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
        
        // Template is now optional - can use visual builder instead
        
        return true;
    }
    
    /**
     * String representation
     * 
     * @return string
     */
    public function __toString() {
        return $this->title ?: $this->name;
    }
    
    /**
     * Get config as array
     * 
     * @return array
     */
    public function getConfig() {
        if(empty($this->config)) {
            return $this->getDefaultConfig();
        }
        
        $config = json_decode($this->config, true);
        if(!is_array($config)) {
            return $this->getDefaultConfig();
        }
        
        return array_merge($this->getDefaultConfig(), $config);
    }
    
    /**
     * Set config from array
     * 
     * @param array $config
     * @return self
     */
    public function setConfig(array $config) {
        $this->config = json_encode($config, JSON_PRETTY_PRINT);
        return $this;
    }
    
    /**
     * Get default config structure
     * 
     * @return array
     */
    public function getDefaultConfig() {
        return [
            'layout' => 'grid',
            'columns' => 6,
            'fields' => [
                'image' => 'images',
                'title' => 'title',
                'description' => 'summary',
                'price' => '',
                'meta' => '',
            ],
            'link' => true,
            'imageWidth' => 192,
            'imageHeight' => 192,
        ];
    }
}
