<?php namespace ProcessWire;

require_once(__DIR__ . '/Embedrs.php');

/**
 * Embedr Text Formatter
 * 
 * Parses ((name)) tags and replaces them with rendered content blocks
 * 
 * @property string $openTag
 * @property string $closeTag
 */
class TextformatterEmbedr extends Textformatter implements ConfigurableModule {
    
    public static function getModuleInfo() {
        return [
            'title' => 'Embedr Text Formatter',
            'version' => '0.2.13',
            'summary' => 'Dynamic content blocks embedding - parses ((name)) tags',
            'author' => 'Maxim Alex',
            'icon' => 'code',
            'requires' => 'ProcessWire>=3.0.0',
        ];
    }
    
    /**
     * Default configuration
     */
    const defaultOpenTag = '((';
    const defaultCloseTag = '))';
    
    /**
     * Open tag
     * 
     * @var string
     */
    protected $openTag = '';
    
    /**
     * Close tag
     * 
     * @var string
     */
    protected $closeTag = '';
    
    /**
     * Page object
     * 
     * @var Page
     */
    protected $page;
    
    /**
     * Field object
     * 
     * @var Field
     */
    protected $field;
    
    /**
     * Current value
     * 
     * @var string
     */
    protected $value;
    
    /**
     * Embedrs collection
     * 
     * @var Embedrs|null
     */
    protected $embedrs = null;
    
    /**
     * Construct
     */
    public function __construct() {
        $this->openTag = self::defaultOpenTag;
        $this->closeTag = self::defaultCloseTag;
        parent::__construct();
    }
    
    /**
     * Set config property
     * 
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value) {
        if($key === 'openTag' || $key === 'closeTag') {
            $this->$key = $value;
        } else if($key === 'value') {
            $this->value = $value;
        } else {
            parent::set($key, $value);
        }
    }
    
    /**
     * Get config property
     * 
     * @param string $key
     * @return mixed
     */
    public function __get($key) {
        if($key === 'openTag') return $this->openTag;
        if($key === 'closeTag') return $this->closeTag;
        if($key === 'value') return $this->value;
        if($key === 'page') return $this->page;
        if($key === 'field') return $this->field;
        return parent::__get($key);
    }
    
    /**
     * Format value (when Page/Field not known)
     * 
     * @param string $str
     */
    public function format(&$str) {
        $page = new NullPage();
        $field = new NullField();
        $this->formatValue($page, $field, $str);
    }
    
    /**
     * Format value
     * 
     * @param Page $page
     * @param Field $field
     * @param string $value
     */
    public function formatValue(Page $page, Field $field, &$value) {
        $openTag = $this->openTag;
        $closeTag = $this->closeTag;
        
        // Get debug mode safely without loading Process module
        $debugMode = false;
        try {
            $config = $this->wire('modules')->getModuleConfigData('ProcessEmbedr');
            $debugMode = !empty($config['debugMode']);
        } catch(\Exception $e) {
            // Config not accessible, continue without debug
        }
        
        if($debugMode) {
            $this->wire('log')->save('embedr-debug', sprintf(
                '[TextformatterEmbedr::formatValue] Called | Page=%s, User=%s',
                $page->id ? $page->path : 'unknown',
                $this->wire('user')->name
            ));
        }
        
        // Exit early when possible
        if(strpos($value, $openTag) === false) return;
        if(strpos($value, $closeTag) === false) return;
        
        // Build regex pattern
        // Matches: ((name)) with optional surrounding HTML tags
        $regex = '!' .
            '(?:<([a-zA-Z]+)' .         // 1=optional HTML open tag
            '[^>]*>[\s\r\n]*)?' .       // HTML open tag attributes and whitespace
            preg_quote($openTag, '!') . // Embedr open tag ((
            '([a-z0-9_-]+)' .           // 2=embed name
            preg_quote($closeTag, '!') .// Embedr close tag ))
            '(?:[\s\r\n]*</(\1)>)?' .   // 3=optional close HTML tag
            '!i';
        
        if(!preg_match_all($regex, $value, $matches)) return;
        
        if($debugMode) {
            $this->wire('log')->save('embedr-debug', sprintf(
                '[TextformatterEmbedr::formatValue] Found %d embed(s): %s',
                count($matches[2]),
                implode(', ', $matches[2])
            ));
        }
        
        $prevPage = $this->page;
        $prevField = $this->field;
        $prevValue = $this->value;
        
        $this->page = $page;
        $this->field = $field;
        $this->value = $value;
        
        // Process each match
        foreach($matches[2] as $key => $name) {
            $name = $this->wire('sanitizer')->name($name);
            if(!$name) continue;
            
            $replacement = $this->getReplacement($name);
            if($replacement === false) continue;
            
            $openHTML = $matches[1][$key];
            $closeHTML = $matches[3][$key];
            
            // Consume surrounding <p> tags if they match
            if($openHTML && $openHTML === $closeHTML && strtolower($openHTML) === 'p') {
                $this->value = str_replace($matches[0][$key], $replacement, $this->value);
            } else {
                // Just replace the tag itself
                $this->value = str_replace("$openTag$name$closeTag", $replacement, $this->value);
            }
        }
        
        $value = $this->value;
        
        $this->value = $prevValue;
        $this->page = $prevPage;
        $this->field = $prevField;
    }
    
    /**
     * Get replacement for embed name
     * 
     * @param string $name
     * @return string|false
     */
    protected function getReplacement($name) {
        // Get debug mode safely without loading Process module
        $debugMode = false;
        try {
            $config = $this->wire('modules')->getModuleConfigData('ProcessEmbedr');
            $debugMode = !empty($config['debugMode']);
        } catch(\Exception $e) {
            // Config not accessible, continue without debug
        }
        
        if($debugMode) {
            $this->wire('log')->save('embedr-debug', sprintf(
                '[TextformatterEmbedr::getReplacement] Looking for embed: %s',
                $name
            ));
        }
        
        try {
            $embedrs = $this->embedrs();
            $embed = $embedrs->get($name);
            
            if(!$embed || !$embed->id) {
                if($debugMode) {
                    $this->wire('log')->save('embedr-debug', sprintf(
                        '[TextformatterEmbedr::getReplacement] Embed NOT FOUND: %s',
                        $name
                    ));
                }
                return "<!-- Embedr: '{$name}' not found -->";
            }
            
            if($debugMode) {
                $this->wire('log')->save('embedr-debug', sprintf(
                    '[TextformatterEmbedr::getReplacement] Embed found | ID=%s, Name=%s, Type=%s',
                    $embed->id,
                    $embed->name,
                    $embed->type ? $embed->type->name : 'unknown'
                ));
            }
            
            $rendered = $embed->render();
            
            if($debugMode) {
                $renderedPreview = substr(strip_tags($rendered), 0, 100);
                $this->wire('log')->save('embedr-debug', sprintf(
                    '[TextformatterEmbedr::getReplacement] Rendered (%d chars): %s...',
                    strlen($rendered),
                    $renderedPreview
                ));
            }
            
            return $rendered;
            
        } catch(\Exception $e) {
            // Log error if debug enabled
            if($debugMode) {
                $this->wire('log')->save('embedr-debug', sprintf(
                    '[TextformatterEmbedr::getReplacement] EXCEPTION: %s',
                    $e->getMessage()
                ));
            }
            
            // Return error comment instead of throwing
            return "<!-- Embedr Error: {$e->getMessage()} -->";
        }
    }
    
    /**
     * Get Embedrs collection
     * 
     * @return Embedrs
     */
    protected function embedrs() {
        if($this->embedrs !== null) {
            return $this->embedrs;
        }
        
        $this->embedrs = $this->wire(new Embedrs());
        return $this->embedrs;
    }
    
    /**
     * Render embed by name (API usage)
     * 
     * @param string $value
     * @param Page|null $page
     * @param Field|null $field
     * @return string
     */
    public function render($value, Page $page = null, Field $field = null) {
        if(is_null($page)) $page = $this->wire('page');
        if(is_null($field)) $field = $this->wire(new Field());
        
        $this->formatValue($page, $field, $value);
        return $value;
    }
    
    /**
     * Module configuration
     * 
     * @param array $data
     * @return InputfieldWrapper
     */
    public static function getModuleConfigInputfields(array $data) {
        $inputfields = new InputfieldWrapper();
        $modules = wire('modules');
        
        // Open tag
        $f = $modules->get('InputfieldText');
        $f->attr('name', 'openTag');
        $f->label = 'Opening Tag';
        $f->description = 'Tag that starts an embed';
        $f->notes = 'Default: ((';
        $f->value = isset($data['openTag']) ? $data['openTag'] : self::defaultOpenTag;
        $f->columnWidth = 50;
        $inputfields->add($f);
        
        // Close tag
        $f = $modules->get('InputfieldText');
        $f->attr('name', 'closeTag');
        $f->label = 'Closing Tag';
        $f->description = 'Tag that ends an embed';
        $f->notes = 'Default: ))';
        $f->value = isset($data['closeTag']) ? $data['closeTag'] : self::defaultCloseTag;
        $f->columnWidth = 50;
        $inputfields->add($f);
        
        // Usage instructions
        $f = $modules->get('InputfieldMarkup');
        $f->label = 'How to Use';
        $f->value = '
            <h3>Setup</h3>
            <ol>
                <li>Go to <strong>Setup → Embedr</strong> to create your embeds</li>
                <li>Add this Textformatter to your textarea fields (e.g. body field)</li>
                <li>Use embed tags in your content: <code>((embed-name))</code></li>
            </ol>
            
            <h3>Example</h3>
            <p>In your article body:</p>
            <pre>
Text about French wines...

((bordeaux-wines))

More text...

((featured-articles))
            </pre>
            
            <h3>Tips</h3>
            <ul>
                <li>Embed names must be lowercase with letters, numbers, hyphens or underscores</li>
                <li>Embeds are reusable - create once, use many times</li>
                <li>Edit embeds in Setup → Embedr - all usages update automatically</li>
            </ul>
        ';
        $inputfields->add($f);
        
        return $inputfields;
    }
}
