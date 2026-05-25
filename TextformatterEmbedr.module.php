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
            'version' => '0.3.0',
            'summary' => 'Dynamic content blocks embedding - parses ((name)) tags',
            'author' => 'Maxim Semenov',
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
     * Whether ProcessEmbedr config has been loaded into this instance
     *
     * @var bool
     */
    protected $configLoaded = false;

    /**
     * Cached debug mode flag
     *
     * @var bool
     */
    protected $debugMode = false;
    
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
     * Load config from ProcessEmbedr (primary source) once per instance.
     * Falls back to own saved values or hard-coded defaults.
     */
    protected function loadConfig() {
        if($this->configLoaded) return;
        try {
            $config = $this->wire('modules')->getModuleConfigData('ProcessEmbedr');
            if(!empty($config['openTag']))  $this->openTag  = $config['openTag'];
            if(!empty($config['closeTag'])) $this->closeTag = $config['closeTag'];
            $this->debugMode = !empty($config['debugMode']);
        } catch(\Exception $e) {
            // ProcessEmbedr not available — keep own defaults
        }
        $this->configLoaded = true;
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
        $this->loadConfig();

        $openTag  = $this->openTag;
        $closeTag = $this->closeTag;

        if($this->debugMode) {
            $this->wire('log')->save('embedr-debug', sprintf(
                '[TextformatterEmbedr::formatValue] Called | Page=%s, User=%s',
                $page->id ? $page->path : 'unknown',
                $this->wire('user')->name
            ));
        }

        if(strpos($value, $openTag) === false) return;
        if(strpos($value, $closeTag) === false) return;

        // Matches ((name)) with optional surrounding HTML wrapper tag
        $regex = '!' .
            '(?:<([a-zA-Z]+)[^>]*>[\s\r\n]*)?' .
            preg_quote($openTag, '!') .
            '([a-z0-9_-]+)' .
            preg_quote($closeTag, '!') .
            '(?:[\s\r\n]*</(\1)>)?' .
            '!i';

        if(!preg_match_all($regex, $value, $matches)) return;

        if($this->debugMode) {
            $this->wire('log')->save('embedr-debug', sprintf(
                '[TextformatterEmbedr::formatValue] Found %d embed(s): %s',
                count($matches[2]), implode(', ', $matches[2])
            ));
        }

        $prevPage  = $this->page;
        $prevField = $this->field;
        $prevValue = $this->value;

        $this->page  = $page;
        $this->field = $field;
        $this->value = $value;

        foreach($matches[2] as $key => $name) {
            $name = $this->wire('sanitizer')->name($name);
            if(!$name) continue;

            $replacement = $this->getReplacement($name);
            if($replacement === false) continue;

            $openHTML  = $matches[1][$key];
            $closeHTML = $matches[3][$key];

            // Strip surrounding <p> wrapper if it exists
            if($openHTML && $openHTML === $closeHTML && strtolower($openHTML) === 'p') {
                $this->value = str_replace($matches[0][$key], $replacement, $this->value);
            } else {
                $this->value = str_replace("$openTag$name$closeTag", $replacement, $this->value);
            }
        }

        $value = $this->value;

        $this->value = $prevValue;
        $this->page  = $prevPage;
        $this->field = $prevField;
    }
    
    /**
     * Get replacement for embed name
     *
     * @param string $name
     * @return string|false
     */
    protected function getReplacement($name) {
        $debugMode = $this->debugMode;

        if($debugMode) {
            $this->wire('log')->save('embedr-debug',
                '[TextformatterEmbedr::getReplacement] Looking for embed: ' . $name
            );
        }

        try {
            $embedrs = $this->embedrs();
            $embed   = $embedrs->get($name);

            if(!$embed || !$embed->id) {
                if($debugMode) {
                    $this->wire('log')->save('embedr-debug',
                        '[TextformatterEmbedr::getReplacement] Embed NOT FOUND: ' . $name
                    );
                }
                return "<!-- Embedr: '{$name}' not found -->";
            }

            if($debugMode) {
                $this->wire('log')->save('embedr-debug', sprintf(
                    '[TextformatterEmbedr::getReplacement] Embed found | ID=%s, Name=%s, Type=%s',
                    $embed->id, $embed->name, $embed->type ? $embed->type->name : 'unknown'
                ));
            }

            $rendered = $embed->render();

            if($debugMode) {
                $this->wire('log')->save('embedr-debug', sprintf(
                    '[TextformatterEmbedr::getReplacement] Rendered (%d chars): %s...',
                    strlen($rendered), substr(strip_tags($rendered), 0, 100)
                ));
            }

            return $rendered;

        } catch(\Exception $e) {
            if($debugMode) {
                $this->wire('log')->save('embedr-debug',
                    '[TextformatterEmbedr::getReplacement] EXCEPTION: ' . $e->getMessage()
                );
            }
            return "<!-- Embedr: render error -->";
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
    public function render($value, ?Page $page = null, ?Field $field = null) {
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

        // Tags are now managed exclusively in ProcessEmbedr settings
        $f = $modules->get('InputfieldMarkup');
        $f->label = 'Tag Configuration';
        $f->value = '<p class="uk-text-meta">Opening and closing tags are configured in ' .
            '<a href="../ProcessEmbedr/">Embedr module settings</a> and applied here automatically.</p>';
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
