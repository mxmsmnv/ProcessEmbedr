<?php namespace ProcessWire;

require_once(__DIR__ . '/EmbedrType.php');

/**
 * Embedr Types Collection
 * 
 * Manages embed types in database
 */
class EmbedrTypes extends Wire {
    
    /**
     * Database table name
     */
    const TABLE_NAME = 'embedr_types';
    
    /**
     * Cache of all types
     * 
     * @var WireArray|null
     */
    protected $types = null;
    
    /**
     * Get database table name
     * 
     * @return string
     */
    public function getTable() {
        return self::TABLE_NAME;
    }
    
    /**
     * Install database table
     * 
     * @return bool
     */
    public function install() {
        $database = $this->wire('database');
        $table = self::TABLE_NAME;
        
        $sql = "CREATE TABLE IF NOT EXISTS `$table` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(64) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `template` VARCHAR(255) DEFAULT '',
            `icon` VARCHAR(64) DEFAULT '',
            `sort` INT UNSIGNED DEFAULT 0,
            `mode` ENUM('once', 'array') DEFAULT 'array',
            `config` TEXT,
            UNIQUE KEY `name` (`name`),
            KEY `sort` (`sort`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            $database->exec($sql);
            return true;
        } catch(\Exception $e) {
            $this->error("Error creating table: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Uninstall database table
     * 
     * @return bool
     */
    public function uninstall() {
        $database = $this->wire('database');
        $table = self::TABLE_NAME;
        
        try {
            $database->exec("DROP TABLE IF EXISTS `$table`");
            return true;
        } catch(\Exception $e) {
            $this->error("Error dropping table: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all types
     * 
     * @param bool $refresh Force refresh from database
     * @return WireArray
     */
    public function getAll($refresh = false) {
        if($this->types !== null && !$refresh) {
            return $this->types;
        }
        
        $database = $this->wire('database');
        $table = self::TABLE_NAME;
        $types = $this->wire(new WireArray());
        
        try {
            $query = $database->prepare("SELECT * FROM `$table` ORDER BY `sort`, `name`");
            $query->execute();
            
            while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
                $type = $this->wire(new EmbedrType());
                $type->setArray($row);
                $types->add($type);
            }
            
            $this->types = $types;
            
        } catch(\Exception $e) {
            $this->error("Error loading types: " . $e->getMessage());
        }
        
        return $types;
    }
    
    /**
     * Get type by ID
     * 
     * @param int $id
     * @return EmbedrType|null
     */
    public function getById($id) {
        $id = (int) $id;
        if(!$id) return null;
        
        $types = $this->getAll();
        return $types->get("id=$id");
    }
    
    /**
     * Get type by name
     * 
     * @param string $name
     * @return EmbedrType|null
     */
    public function get($name) {
        if(is_numeric($name)) {
            return $this->getById((int) $name);
        }
        
        $name = $this->wire('sanitizer')->name($name);
        if(!$name) return null;
        
        $types = $this->getAll();
        return $types->get("name=$name");
    }
    
    /**
     * Save type
     * 
     * @param EmbedrType $type
     * @return bool|int Returns ID on success, false on failure
     */
    public function save(EmbedrType $type) {
        $database = $this->wire('database');
        $table = self::TABLE_NAME;
        
        // Validate
        $valid = $type->validate();
        if($valid !== true) {
            $this->error($valid);
            return false;
        }
        
        // Sanitize
        $name = $this->wire('sanitizer')->name($type->name);
        $title = $this->wire('sanitizer')->text($type->title);
        $template = $this->wire('sanitizer')->text($type->template);
        $icon = $this->wire('sanitizer')->name($type->icon);
        $sort = (int) $type->sort;
        
        try {
            if($type->id) {
                // Update existing - check if name changed
                $query = $database->prepare("SELECT name FROM `$table` WHERE id = :id");
                $query->execute([':id' => $type->id]);
                $oldName = $query->fetchColumn();
                
                // Only check duplicates if name actually changed
                if($oldName !== $name) {
                    $query = $database->prepare("SELECT COUNT(*) FROM `$table` WHERE name = :name AND id != :id");
                    $query->execute([':name' => $name, ':id' => $type->id]);
                    if($query->fetchColumn() > 0) {
                        $this->error("A type with the name '{$name}' already exists");
                        return false;
                    }
                }
                
                // Update existing
                $sql = "UPDATE `$table` SET 
                    `name` = :name,
                    `title` = :title,
                    `template` = :template,
                    `icon` = :icon,
                    `sort` = :sort,
                    `mode` = :mode,
                    `config` = :config
                    WHERE `id` = :id";
                
                $query = $database->prepare($sql);
                $query->execute([
                    ':id' => $type->id,
                    ':name' => $name,
                    ':title' => $title,
                    ':template' => $template,
                    ':icon' => $icon,
                    ':sort' => $sort,
                    ':mode' => $type->mode,
                    ':config' => $type->config,
                ]);
                
                $this->types = null; // Clear cache
                $this->message("Type '{$title}' updated");
                return $type->id;
                
            } else {
                // Insert new
                $sql = "INSERT INTO `$table` 
                    (`name`, `title`, `template`, `icon`, `sort`, `mode`, `config`) 
                    VALUES (:name, :title, :template, :icon, :sort, :mode, :config)";
                
                $query = $database->prepare($sql);
                $query->execute([
                    ':name' => $name,
                    ':title' => $title,
                    ':template' => $template,
                    ':icon' => $icon,
                    ':sort' => $sort,
                    ':mode' => $type->mode,
                    ':config' => $type->config,
                ]);
                
                $type->id = (int) $database->lastInsertId();
                $this->types = null; // Clear cache
                $this->message("Type '{$title}' created");
                return $type->id;
            }
            
        } catch(\Exception $e) {
            $this->error("Error saving type: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete type
     * 
     * @param int|EmbedrType $type
     * @return bool
     */
    public function delete($type) {
        $database = $this->wire('database');
        $table = self::TABLE_NAME;
        
        $id = is_object($type) ? $type->id : (int) $type;
        if(!$id) return false;
        
        try {
            $query = $database->prepare("DELETE FROM `$table` WHERE `id` = :id");
            $query->execute([':id' => $id]);
            
            $this->types = null; // Clear cache
            $this->message("Type deleted");
            return true;
            
        } catch(\Exception $e) {
            $this->error("Error deleting type: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Auto-discover types from components directory
     * 
     * @param string $componentsPath
     * @return int Number of types discovered
     */
    public function autoDiscover($componentsPath) {
        $config = $this->wire('config');
        $path = $config->paths->templates . $componentsPath;
        
        // If path doesn't exist, silently return - not an error
        if(!is_dir($path)) {
            return 0;
        }
        
        $files = glob($path . '*.php');
        $count = 0;
        
        foreach($files as $file) {
            $name = basename($file, '.php');
            
            // Skip if already exists
            if($this->get($name)) continue;
            
            // Create new type
            $type = $this->wire(new EmbedrType());
            $type->name = $name;
            $type->title = ucfirst(str_replace(['-', '_'], ' ', $name));
            $type->template = basename($file);
            $type->sort = $count * 10;
            
            if($this->save($type)) {
                $count++;
            }
        }
        
        if($count > 0) {
            $this->message("Discovered $count new types");
        }
        
        return $count;
    }
}
