<?php namespace ProcessWire;

require_once(__DIR__ . '/Embedrs.php');

/**
 * Process Embedr
 * 
 * Admin interface for managing content embeds
 * 
 * @property string $componentsPath
 * @property string $openTag
 * @property string $closeTag
 * @property bool $autoDiscoverTypes
 * @property bool $showTypeIcons
 */
class ProcessEmbedr extends Process implements ConfigurableModule {
    
    public static function getModuleInfo() {
        return [
            'title' => 'Embedr',
            'version' => '0.2.12',
            'summary' => 'Manage dynamic content embeds with live preview',
            'author' => 'Maxim Alex',
            'href' => 'https://github.com/mxmsmnv/Embedr',
            'icon' => 'code',
            'permission' => 'embedr',
            'permissions' => [
                'embedr' => 'List and view embeds',
                'embedr-edit' => 'Add/edit/delete embeds'
            ],
            'requires' => 'InputfieldSelector, TextformatterEmbedr, ProcessWire>=3.0.0'
        ];
    }
    
    /**
     * Default configuration
     * 
     * @var array
     */
    protected static $defaultConfig = [
        'componentsPath' => 'components/',
        'openTag' => '((',
        'closeTag' => '))',
        'autoDiscoverTypes' => false,
        'showTypeIcons' => true,
        'debugMode' => false,
    ];
    
    /**
     * Embedrs collection
     * 
     * @var Embedrs|null
     */
    protected $embedrs = null;
    
    /**
     * EmbedrTypes collection
     * 
     * @var EmbedrTypes|null
     */
    protected $types = null;
    
    /**
     * Debug log - write to both messages and log file
     * 
     * @param string $message
     * @param array $context Additional context data
     */
    protected function debugLog($message, $context = []) {
        if(!$this->debugMode) return;
        
        // Format message with context
        if(!empty($context)) {
            $contextStr = json_encode($context, JSON_UNESCAPED_UNICODE);
            $fullMessage = $message . ' | Context: ' . $contextStr;
        } else {
            $fullMessage = $message;
        }
        
        // Save to log file
        $this->wire('log')->save('embedr-debug', $fullMessage);
        
        // Also show in admin interface
        $this->message("DEBUG: " . $message);
    }
    
    /**
     * Construct
     */
    public function __construct() {
        foreach(self::$defaultConfig as $key => $value) {
            if(!isset($this->$key)) $this->$key = $value;
        }
        parent::__construct();
    }
    
    /**
     * Init
     */
    public function init() {
        parent::init();
        $this->embedrs = $this->wire(new Embedrs());
        $this->types = $this->wire(new EmbedrTypes());
    }
    
    /**
     * Install module
     */
    public function ___install() {
        // Initialize collections
        $types = $this->wire(new EmbedrTypes());
        $embedrs = $this->wire(new Embedrs());
        
        // Install database tables
        $types->install();
        $embedrs->install();
        
        // Ensure admin page exists
        $parent = $this->wire('pages')->get('name=setup');
        $adminPage = $this->wire('pages')->get('name=embedr, parent=' . $parent->id);
        
        if(!$adminPage->id) {
            $adminPage = new Page();
            $adminPage->template = 'admin';
            $adminPage->parent = $parent;
            $adminPage->name = 'embedr';
            $adminPage->title = 'Embedr';
            $adminPage->process = $this;
            
            // Set process and save
            $adminPage->save();
            
            $this->message("Created admin page at Setup → Embedr");
        }
        
        // Auto-discover types if enabled
        if($this->autoDiscoverTypes) {
            $types->autoDiscover($this->componentsPath);
        }
    }
    
    /**
     * Uninstall module
     */
    public function ___uninstall() {
        // Initialize collections
        $embedrs = $this->wire(new Embedrs());
        $types = $this->wire(new EmbedrTypes());
        
        // Drop database tables (embeds first due to foreign key)
        $embedrs->uninstall();
        $types->uninstall();
        
        // Remove admin page if it exists
        $parent = $this->wire('pages')->get('name=setup');
        $adminPage = $this->wire('pages')->get('name=embedr, parent=' . $parent->id);
        
        if($adminPage->id) {
            $adminPage->delete();
            $this->message("Removed admin page from Setup → Embedr");
        }
    }
    
    /**
     * Execute - list embeds
     * 
     * @return string
     */
    public function ___execute() {
        
        // Check if auto-discover needed
        if($this->autoDiscoverTypes) {
            $typesCount = $this->types->getAll()->count();
            if($typesCount === 0) {
                $this->types->autoDiscover($this->componentsPath);
            }
        }
        
        $embeds = $this->embedrs->getAll(true);
        $types = $this->types->getAll(true);
        
        // Sort by ID descending (newest first)
        $embeds->sort('-id');
        
        // Group embeds by type
        $embedsByType = [];
        $noTypeEmbeds = [];
        
        foreach($embeds as $embed) {
            if($embed->type_id && $embed->type) {
                if(!isset($embedsByType[$embed->type_id])) {
                    $embedsByType[$embed->type_id] = [];
                }
                $embedsByType[$embed->type_id][] = $embed;
            } else {
                $noTypeEmbeds[] = $embed;
            }
        }
        
        $out = '';
        
        // Render by type sections
        foreach($types as $type) {
            if(!isset($embedsByType[$type->id]) || count($embedsByType[$type->id]) === 0) {
                continue; // Skip types with no embeds
            }
            
            $typeEmbeds = $embedsByType[$type->id];
            $icon = $type->icon ? "<i class='fa fa-{$type->icon}'></i> " : '';
            
            $out .= "<div class='uk-margin-large-top'>";
            $out .= "<h3 style='margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e5e5e5;'>";
            $out .= $icon . $type->title . " <span style='color: #999; font-weight: normal; font-size: 0.9em;'>(" . count($typeEmbeds) . ")</span>";
            $out .= "</h3>";
            
            // Build table for this type
            $table = $this->modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->headerRow([
                'Name',
                'Title',
                'Limit',
                'Results',
                'Shortcode',
                'Modified',
                '', // Actions column
            ]);
            $table->setClass('embedr-table');
            
            foreach($typeEmbeds as $embed) {
                // Extract limit from selector
                $limit = '—';
                if(preg_match('/limit=(\d+)/i', $embed->selector, $matches)) {
                    $limit = $matches[1];
                }
                
                $count = $embed->getCount();
                $countClass = $count > 0 ? 'text-success' : 'text-muted';
                
                $shortcode = "<code>{$this->openTag}{$embed->name}{$this->closeTag}</code> " .
                    "<a href='#' class='copy-shortcode' data-shortcode='{$this->openTag}{$embed->name}{$this->closeTag}'>" .
                    "<i class='fa fa-copy'></i></a>";
                
                $modified = date('Y-m-d H:i', $embed->modified);
                
                // Actions column with delete button
                $actions = "<a href='./delete/?id={$embed->id}' " .
                    "class='delete-embed' " .
                    "onclick='return confirm(\"Delete embed \\\"{$embed->title}\\\"?\")' " .
                    "title='Delete'>" .
                    "<i class='fa fa-trash'></i></a>";
                
                $table->row([
                    "<a href='./edit/?id={$embed->id}'><strong>{$embed->name}</strong></a>",
                    $embed->title,
                    $limit,
                    "<span class='$countClass'>{$count}</span>",
                    $shortcode,
                    $modified,
                    $actions,
                ]);
            }
            
            $out .= $table->render();
            $out .= "</div>";
        }
        
        // No type embeds
        if(count($noTypeEmbeds) > 0) {
            $out .= "<div class='uk-margin-large-top'>";
            $out .= "<h3 style='margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e5e5e5;'>";
            $out .= "No Type <span style='color: #999; font-weight: normal; font-size: 0.9em;'>(" . count($noTypeEmbeds) . ")</span>";
            $out .= "</h3>";
            
            $table = $this->modules->get('MarkupAdminDataTable');
            $table->setEncodeEntities(false);
            $table->headerRow([
                'Name',
                'Title',
                'Limit',
                'Results',
                'Shortcode',
                'Modified',
                '', // Actions column
            ]);
            
            foreach($noTypeEmbeds as $embed) {
                $limit = '—';
                if(preg_match('/limit=(\d+)/i', $embed->selector, $matches)) {
                    $limit = $matches[1];
                }
                
                $count = $embed->getCount();
                $countClass = $count > 0 ? 'text-success' : 'text-muted';
                
                $shortcode = "<code>{$this->openTag}{$embed->name}{$this->closeTag}</code> " .
                    "<a href='#' class='copy-shortcode' data-shortcode='{$this->openTag}{$embed->name}{$this->closeTag}'>" .
                    "<i class='fa fa-copy'></i></a>";
                
                $modified = date('Y-m-d H:i', $embed->modified);
                
                // Actions column with delete button
                $actions = "<a href='./delete/?id={$embed->id}' " .
                    "class='delete-embed' " .
                    "onclick='return confirm(\"Delete embed \\\"{$embed->title}\\\"?\")' " .
                    "title='Delete'>" .
                    "<i class='fa fa-trash'></i></a>";
                
                $table->row([
                    "<a href='./edit/?id={$embed->id}'><strong>{$embed->name}</strong></a>",
                    $embed->title,
                    $limit,
                    "<span class='$countClass'>{$count}</span>",
                    $shortcode,
                    $modified,
                    $actions,
                ]);
            }
            
            $out .= $table->render();
            $out .= "</div>";
        }
        
        // Add button
        $button = $this->modules->get('InputfieldButton');
        $button->href = './edit/';
        $button->icon = 'plus';
        $button->value = 'Add New Embed';
        
        // Manage Types button
        $typesButton = $this->modules->get('InputfieldButton');
        $typesButton->href = './types/';
        $typesButton->icon = 'cubes';
        $typesButton->value = 'Manage Types';
        $typesButton->setSecondary();
        
        $output = '';
        
        // Quick instructions at the top
        if($embeds->count() > 0 || $types->count() > 0) {
            $output .= "<div class='uk-alert uk-alert-primary' style='margin-bottom: 20px;'>";
            $output .= "<h3 style='margin-top: 0;'>Quick Start</h3>";
            $output .= "<ol style='margin-bottom: 0;'>";
            $output .= "<li><strong>Create Type:</strong> Click 'Manage Types' → Define block type (products, articles, etc.)</li>";
            $output .= "<li><strong>Create Embed:</strong> Click 'Add New Embed' → Select type → Build selector → Save</li>";
            $output .= "<li><strong>Use in Content:</strong> Copy shortcode (e.g. <code>{$this->openTag}name{$this->closeTag}</code>) → Paste in body field</li>";
            $output .= "<li><strong>Enable:</strong> Add 'Embedr Text Formatter' to your textarea field (Setup → Fields → body → Details)</li>";
            $output .= "</ol>";
            $output .= "</div>";
        }
        
        // Check if types exist
        if(!$types->count()) {
            $output .= "<div class='uk-alert uk-alert-warning'>";
            $output .= "<h3>No Embed Types Found</h3>";
            $output .= "<p>You can:</p>";
            $output .= "<ol>";
            $output .= "<li>Add types manually using <a href='./types/'>Manage Types</a></li>";
            $output .= "<li>Or configure auto-discovery: Check that your components path is correct: <code>{$this->componentsPath}</code></li>";
            $output .= "<li>Make sure you have .php template files in <code>/site/templates/{$this->componentsPath}</code></li>";
            $output .= "<li>Enable 'Auto-discover types' in module settings</li>";
            $output .= "</ol>";
            $output .= "<p>";
            $output .= "<a href='./types/' class='uk-button uk-button-primary'><i class='fa fa-cubes'></i> Manage Types</a> ";
            $output .= "<a href='../module/edit?name=ProcessEmbedr' class='uk-button'>Module Settings</a>";
            $output .= "</p>";
            $output .= "</div>";
        }
        
        // Instructions if no embeds
        if(!$embeds->count()) {
            $output .= "<div class='uk-alert uk-alert-primary'>";
            $output .= "<h3>Welcome to Embedr!</h3>";
            $output .= "<p>You don't have any embeds yet. Click the button below to create your first one.</p>";
            $output .= "<p>Embeds allow you to insert dynamic content blocks into your articles using simple tags like <code>{$this->openTag}name{$this->closeTag}</code></p>";
            $output .= "</div>";
        }
        
        $output .= $button->render();
        $output .= " ";
        $output .= $typesButton->render();
        
        // Add grouped tables
        $output .= $out;
        
        // Add JavaScript for copy functionality
        $output .= "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.copy-shortcode').forEach(function(el) {
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    var shortcode = this.dataset.shortcode;
                    navigator.clipboard.writeText(shortcode).then(function() {
                        var icon = el.querySelector('i');
                        icon.className = 'fa fa-check';
                        setTimeout(function() {
                            icon.className = 'fa fa-copy';
                        }, 2000);
                    });
                });
            });
        });
        </script>
        <style>
        .copy-shortcode { 
            margin-left: 5px; 
            opacity: 0.5; 
            text-decoration: none;
        }
        .copy-shortcode:hover { 
            opacity: 1; 
        }
        .delete-embed {
            color: #d9534f;
            text-decoration: none;
            opacity: 0.6;
        }
        .delete-embed:hover {
            opacity: 1;
        }
        /* Fixed column widths */
        .embedr-table thead th:nth-child(1) { width: 14%; }  /* Name */
        .embedr-table thead th:nth-child(2) { width: 23%; }  /* Title */
        .embedr-table thead th:nth-child(3) { width: 7%; }   /* Limit */
        .embedr-table thead th:nth-child(4) { width: 7%; }   /* Results */
        .embedr-table thead th:nth-child(5) { width: 20%; }  /* Shortcode */
        .embedr-table thead th:nth-child(6) { width: 15%; }  /* Modified */
        .embedr-table thead th:nth-child(7) { width: 5%; }   /* Actions */
        </style>
        ";
        
        return $output;
    }
    
    /**
     * Execute edit - add/edit embed
     * 
     * @return string
     */
    public function ___executeEdit() {
        $this->breadcrumb('../', 'Embedr');
        
        $id = (int) $this->input->get('id');
        
        if($id) {
            $embed = $this->embedrs->getById($id);
            if(!$embed) throw new WireException("Embed not found");
            $this->headline("Edit Embed: {$embed->title}");
        } else {
            $embed = $this->wire(new Embedr());
            $this->headline("Add New Embed");
        }
        
        // Build form
        $form = $this->buildEditForm($embed);
        
        // Process form
        if($this->input->post('submit_save')) {
            $form->processInput($this->input->post);
            
            if(!$form->getErrors()) {
                $this->processEditForm($form, $embed);
            }
        }
        
        return $form->render();
    }
    
    /**
     * Build edit form
     * 
     * @param Embedr $embed
     * @return InputfieldForm
     */
    protected function buildEditForm(Embedr $embed) {
        $form = $this->modules->get('InputfieldForm');
        $form->attr('method', 'post');
        // Don't set action - let ProcessWire handle it automatically to avoid /edit/edit/ issue
        
        // Add hidden ID field for existing embeds
        if($embed->id) {
            $f = $this->modules->get('InputfieldHidden');
            $f->attr('name', 'embed_id');
            $f->value = $embed->id;
            $form->add($f);
        }
        
        // Name
        $f = $this->modules->get('InputfieldName');
        $f->attr('name', 'embed_name');
        $f->label = 'Name';
        $f->description = 'Unique identifier for this embed (lowercase, letters, numbers, hyphens, underscores)';
        $f->required = true;
        $f->value = $embed->name;
        $f->columnWidth = 50;
        if($embed->id) $f->collapsed = Inputfield::collapsedNo;
        $form->add($f);
        
        // Title
        $f = $this->modules->get('InputfieldText');
        $f->attr('name', 'embed_title');
        $f->label = 'Title';
        $f->description = 'Human-readable title for this embed';
        $f->required = true;
        $f->value = $embed->title;
        $f->columnWidth = 50;
        $form->add($f);
        
        // Type
        $f = $this->modules->get('InputfieldSelect');
        $f->attr('name', 'embed_type_id');
        $f->label = 'Block Type';
        $f->description = 'Select the type of content block';
        $f->required = true;
        
        $types = $this->types->getAll();
        foreach($types as $type) {
            // Don't use HTML in select options - it's not rendered
            $label = $type->title;
            $f->addOption($type->id, $label);
        }
        
        $f->value = $embed->type_id;
        $form->add($f);
        
        // Selector
        $f = $this->modules->get('InputfieldSelector');
        $f->attr('name', 'embed_selector');
        $f->label = 'Content Selector';
        $f->description = 'Build a ProcessWire selector to find content';
        $f->required = true;
        $f->value = $embed->selector; // Use value instead of initValue
        $f->preview = true;
        $f->previewColumns = ['title', 'path'];
        $form->add($f);
        
        // Preview section
        $f = $this->modules->get('InputfieldMarkup');
        $f->attr('id', 'embed_preview');
        $f->label = 'Preview';
        $f->description = 'Live preview of the embed';
        
        if($embed->id && $embed->selector) {
            $count = $embed->getCount();
            $preview = $embed->render();
            
            $f->value = "
                <div class='uk-alert uk-alert-primary'>
                    <strong>Results: {$count}</strong>
                </div>
                <div style='border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #fafafa;'>
                    {$preview}
                </div>
            ";
        } else {
            $f->value = "<p class='description'>Save the embed to see a preview</p>";
        }
        
        $form->add($f);
        
        // Shortcode display
        if($embed->id) {
            $f = $this->modules->get('InputfieldMarkup');
            $f->label = 'Shortcode';
            $f->description = 'Copy this code to use in your content';
            
            $shortcode = $embed->getShortcode();
            $f->value = "
                <div class='uk-form-horizontal'>
                    <input type='text' value='{$shortcode}' readonly 
                           style='font-family: monospace; font-size: 14px; padding: 10px;'
                           onclick='this.select()'>
                    <button type='button' class='uk-button uk-button-small' 
                            onclick='navigator.clipboard.writeText(\"{$shortcode}\"); this.textContent=\"Copied!\"; var btn=this; setTimeout(function(){btn.textContent=\"Copy\";}, 2000);'>
                        Copy
                    </button>
                </div>
            ";
            
            $form->add($f);
        }
        
        // Submit
        $f = $this->modules->get('InputfieldSubmit');
        $f->attr('name', 'submit_save');
        $f->value = 'Save Embed';
        $form->add($f);
        
        // Delete button
        if($embed->id) {
            $f = $this->modules->get('InputfieldSubmit');
            $f->attr('name', 'submit_delete');
            $f->value = 'Delete';
            $f->icon = 'trash';
            $f->setSecondary();
            $form->add($f);
        }
        
        return $form;
    }
    
    /**
     * Process edit form
     * 
     * @param InputfieldForm $form
     * @param Embedr $embed
     */
    protected function processEditForm($form, Embedr $embed) {
        
        $this->debugLog('processEditForm started', [
            'embed_id' => $embed->id,
            'embed_name' => $embed->name,
            'post_data' => $this->input->post->getArray()
        ]);
        
        // Check for delete
        if($this->input->post('submit_delete')) {
            $this->debugLog('Delete button clicked', ['embed_id' => $embed->id]);
            
            if($this->embedrs->delete($embed)) {
                $this->message("Embed deleted");
                $this->session->redirect('../');
            }
            return;
        }
        
        // Restore ID from hidden field if editing
        if($this->input->post('embed_id')) {
            $embedId = (int) $this->input->post('embed_id');
            if($embedId > 0) {
                $embed->id = $embedId;
                $this->debugLog('Restored ID from hidden field', [
                    'restored_id' => $embedId,
                    'embed_id_after' => $embed->id
                ]);
            }
        }
        
        // Update embed properties
        $embed->name = $form->get('embed_name')->value;
        $embed->title = $form->get('embed_title')->value;
        $embed->type_id = (int) $form->get('embed_type_id')->value;
        $embed->selector = $form->get('embed_selector')->value;
        
        $this->debugLog('Before save', [
            'embed_id' => $embed->id,
            'embed_name' => $embed->name,
            'embed_title' => $embed->title,
            'type_id' => $embed->type_id
        ]);
        
        // Save
        $result = $this->embedrs->save($embed);
        
        $this->debugLog('After save', [
            'save_result' => $result,
            'embed_id_after' => $embed->id
        ]);
        
        if($result) {
            $this->message("Embed saved");
            $this->session->redirect("./edit/?id={$embed->id}");
        }
    }
    
    /**
     * Execute delete - delete embed from list
     * 
     * @return string
     */
    public function ___executeDelete() {
        $id = (int) $this->input->get('id');
        if(!$id) {
            $this->error("No ID provided");
            $this->session->redirect('../');
            return '';
        }
        
        $embed = $this->embedrs->getById($id);
        if(!$embed) {
            $this->error("Embed not found");
            $this->session->redirect('../');
            return '';
        }
        
        if($this->embedrs->delete($embed)) {
            $this->message("Embed '{$embed->title}' deleted");
        }
        
        $this->session->redirect('../');
        return '';
    }
    
    /**
     * Execute types - manage types
     * 
     * @return string
     */
    public function ___executeTypes() {
        $this->breadcrumb('../', 'Embedr');
        $this->headline('Manage Types');
        
        $types = $this->types->getAll(true);
        
        // Build table
        $table = $this->modules->get('MarkupAdminDataTable');
        $table->setEncodeEntities(false);
        $table->headerRow([
            'Name',
            'Title',
            'Mode',
            'Template',
            'Actions',
        ]);
        
        foreach($types as $type) {
            // Add icon to title
            $titleWithIcon = $type->title;
            if($type->icon) {
                $titleWithIcon = "<i class='fa fa-{$type->icon}'></i> " . $type->title;
            }
            
            // Template status with full path
            if(empty($type->template)) {
                $templateStatus = "<span style='color: #999;'>Not specified</span>";
            } else {
                $templateExists = $type->templateExists();
                $fullPath = $this->componentsPath . $type->template;
                
                if($templateExists) {
                    $templateStatus = "<span style='color: #4caf50;'><i class='fa fa-check'></i> {$fullPath}</span>";
                } else {
                    $templateStatus = "<span style='color: #f44336;'><i class='fa fa-times'></i> {$fullPath}</span>";
                }
            }
            
            // Mode display - plain text without colors
            $mode = $type->mode ?: 'array';
            $modeDisplay = $mode === 'once' ? 'Once' : 'Array';
            
            $actions = "<a href='../type-edit/?id={$type->id}'><i class='fa fa-edit'></i> Edit</a> | " .
                      "<a href='../type-delete/?id={$type->id}' class='uk-text-danger'><i class='fa fa-trash'></i> Delete</a>";
            
            $table->row([
                "<a href='../type-edit/?id={$type->id}'><strong>{$type->name}</strong></a>",
                $titleWithIcon,
                $modeDisplay,
                $templateStatus,
                $actions,
            ]);
        }
        
        // Buttons
        $button = $this->modules->get('InputfieldButton');
        $button->href = '../type-edit/';
        $button->icon = 'plus';
        $button->value = 'Add New Type';
        
        $out = '';
        
        if(!$types->count()) {
            $out .= "<div class='uk-alert uk-alert-primary'>";
            $out .= "<h3>No Types Yet</h3>";
            $out .= "<p>Add your first embed type to get started.</p>";
            $out .= "<p>Types define what kind of content blocks you can create (products, articles, brands, etc.)</p>";
            $out .= "</div>";
        }
        
        $out .= $button->render();
        
        // Auto-discover button
        if($this->autoDiscoverTypes) {
            $discoverButton = $this->modules->get('InputfieldButton');
            $discoverButton->href = '../type-discover/';
            $discoverButton->icon = 'magic';
            $discoverButton->value = 'Auto-Discover From ' . $this->componentsPath;
            $discoverButton->setSecondary();
            $out .= " ";
            $out .= $discoverButton->render();
        }
        
        $out .= $table->render();
        
        return $out;
    }
    
    /**
     * Execute type-edit - add/edit type
     * 
     * @return string
     */
    public function ___executeTypeEdit() {
        $this->breadcrumb('../', 'Embedr');
        $this->breadcrumb('../types/', 'Types');
        
        $id = (int) $this->input->get('id');
        
        if($id) {
            $type = $this->types->getById($id);
            if(!$type) throw new WireException("Type not found");
            $this->headline("Edit Type: {$type->title}");
        } else {
            $type = $this->wire(new EmbedrType());
            $this->headline("Add New Type");
        }
        
        // Build form
        $form = $this->modules->get('InputfieldForm');
        $form->attr('method', 'post');
        
        // Hidden ID field
        if($type->id) {
            $f = $this->modules->get('InputfieldHidden');
            $f->attr('name', 'type_id');
            $f->value = $type->id;
            $form->add($f);
        }
        
        // Name
        $f = $this->modules->get('InputfieldName');
        $f->attr('name', 'type_name');
        $f->label = 'Name';
        $f->description = 'Lowercase identifier (letters, numbers, hyphens, underscores)';
        $f->required = true;
        $f->value = $type->name;
        $f->columnWidth = 50;
        $form->add($f);
        
        // Title
        $f = $this->modules->get('InputfieldText');
        $f->attr('name', 'type_title');
        $f->label = 'Title';
        $f->description = 'Human-readable name';
        $f->required = true;
        $f->value = $type->title;
        $f->columnWidth = 50;
        $form->add($f);
        
        // Template
        $f = $this->modules->get('InputfieldText');
        $f->attr('name', 'type_template');
        $f->label = 'Template File';
        $f->description = "Filename in {$this->componentsPath}";
        $f->notes = "Example: product.php";
        $f->required = true;
        $f->value = $type->template;
        $f->columnWidth = 70;
        $form->add($f);
        
        // Icon
        $f = $this->modules->get('InputfieldText');
        $f->attr('name', 'type_icon');
        $f->label = 'Icon';
        $f->description = 'Font Awesome icon name (without fa-)';
        $f->notes = 'Example: shopping-cart';
        $f->value = $type->icon;
        $f->columnWidth = 30;
        $form->add($f);
        
        // Mode
        $f = $this->modules->get('InputfieldRadios');
        $f->attr('name', 'type_mode');
        $f->label = 'Display Mode';
        $f->description = 'How to render the content';
        $f->addOption('array', 'Array (Multiple Items) - Show grid/list of items');
        $f->addOption('once', 'Once (Single Item) - Show only first matching item as single card');
        $f->value = $type->mode ?: 'array';
        $f->optionColumns = 1;
        $form->add($f);
        
        // Submit
        $f = $this->modules->get('InputfieldSubmit');
        $f->attr('name', 'submit_save');
        $f->value = 'Save Type';
        $form->add($f);
        
        // Process form
        if($this->input->post('submit_save')) {
            $form->processInput($this->input->post);
            
            if(!$form->getErrors()) {
                // Restore ID if editing
                if($this->input->post('type_id')) {
                    $type->id = (int) $this->input->post('type_id');
                }
                
                $type->name = $form->get('type_name')->value;
                $type->title = $form->get('type_title')->value;
                $type->template = $form->get('type_template')->value;
                $type->icon = $form->get('type_icon')->value;
                $type->mode = $form->get('type_mode')->value;
                
                if($this->types->save($type)) {
                    $this->message("Type saved");
                    $this->session->redirect('../types/');
                }
            }
        }
        
        return $form->render();
    }
    
    /**
     * Execute type-delete - delete type
     * 
     * @return string
     */
    public function ___executeTypeDelete() {
        $id = (int) $this->input->get('id');
        $type = $this->types->getById($id);
        
        if(!$type) throw new WireException("Type not found");
        
        // Check if type is in use
        $embeds = $this->embedrs->findByType($type);
        if($embeds->count()) {
            $this->error("Cannot delete type - it is used by {$embeds->count()} embed(s)");
            $this->session->redirect('../types/');
            return '';
        }
        
        if($this->types->delete($type)) {
            $this->message("Type deleted");
        }
        
        $this->session->redirect('../types/');
        return '';
    }
    
    /**
     * Execute type-discover - auto-discover types
     * 
     * @return string
     */
    public function ___executeTypeDiscover() {
        $count = $this->types->autoDiscover($this->componentsPath);
        
        if($count > 0) {
            $this->message("Discovered {$count} new type(s)");
        } else {
            $this->message("No new types found");
        }
        
        $this->session->redirect('../types/');
        return '';
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
        
        $data = array_merge(self::$defaultConfig, $data);
        
        // Components path
        $f = $modules->get('InputfieldText');
        $f->attr('name', 'componentsPath');
        $f->label = 'Components Path';
        $f->description = 'Path to block templates relative to /site/templates/';
        $f->notes = 'Example: includes/blocks/ or partials/shortcodes/';
        $f->value = $data['componentsPath'];
        $f->required = true;
        $f->columnWidth = 50;
        $inputfields->add($f);
        
        // Open tag
        $f = $modules->get('InputfieldText');
        $f->attr('name', 'openTag');
        $f->label = 'Opening Tag';
        $f->description = 'Tag that starts an embed';
        $f->notes = 'Default: ((';
        $f->value = $data['openTag'];
        $f->columnWidth = 25;
        $inputfields->add($f);
        
        // Close tag
        $f = $modules->get('InputfieldText');
        $f->attr('name', 'closeTag');
        $f->label = 'Closing Tag';
        $f->description = 'Tag that ends an embed';
        $f->notes = 'Default: ))';
        $f->value = $data['closeTag'];
        $f->columnWidth = 25;
        $inputfields->add($f);
        
        // Auto-discover types
        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'autoDiscoverTypes');
        $f->label = 'Auto-discover Types';
        $f->description = 'Automatically find and create types from template files in components path';
        $f->notes = 'Scans for .php files and creates types on first access';
        $f->attr('checked', $data['autoDiscoverTypes'] ? 'checked' : '');
        $f->columnWidth = 33;
        $inputfields->add($f);
        
        // Show type icons
        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'showTypeIcons');
        $f->label = 'Show Type Icons';
        $f->description = 'Display Font Awesome icons next to type names';
        $f->attr('checked', $data['showTypeIcons'] ? 'checked' : '');
        $f->columnWidth = 33;
        $inputfields->add($f);
        
        // Debug mode
        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'debugMode');
        $f->label = 'Debug Mode';
        $f->description = 'Enable detailed logging for troubleshooting';
        $f->notes = 'Logs saved to: Setup → Logs → embedr-debug';
        $f->attr('checked', $data['debugMode'] ? 'checked' : '');
        $f->columnWidth = 34;
        $inputfields->add($f);
        
        return $inputfields;
    }
}
