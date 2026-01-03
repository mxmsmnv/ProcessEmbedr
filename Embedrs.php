<?php namespace ProcessWire;

require_once(__DIR__ . '/Embedr.php');
require_once(__DIR__ . '/EmbedrTypes.php');

/**
 * Embedrs Collection
 * 
 * Manages embeds in database
 */
class Embedrs extends Wire {
    
    /**
     * Database table name
     */
    const TABLE_NAME = 'embedr';
    
    /**
     * Cache of all embeds
     * 
     * @var WireArray|null
     */
    protected $embeds = null;
    
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
        $typesTable = EmbedrTypes::TABLE_NAME;
        
        $sql = "CREATE TABLE IF NOT EXISTS `$table` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(128) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `type_id` INT UNSIGNED NOT NULL,
            `selector` TEXT NOT NULL,
            `created` INT UNSIGNED NOT NULL,
            `modified` INT UNSIGNED NOT NULL,
            UNIQUE KEY `name` (`name`),
            KEY `type_id` (`type_id`),
            FOREIGN KEY (`type_id`) REFERENCES `$typesTable`(`id`) ON DELETE CASCADE
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
     * Get all embeds
     * 
     * @param bool $refresh Force refresh from database
     * @return WireArray
     */
    public function getAll($refresh = false) {
        if($this->embeds !== null && !$refresh) {
            return $this->embeds;
        }
        
        $database = $this->wire('database');
        $table = self::TABLE_NAME;
        $embeds = $this->wire(new WireArray());
        $types = $this->wire(new EmbedrTypes());
        
        try {
            $query = $database->prepare("SELECT * FROM `$table` ORDER BY `name`");
            $query->execute();
            
            while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
                $embed = $this->wire(new Embedr());
                $embed->setArray($row);
                
                // Load type
                $type = $types->getById($row['type_id']);
                if($type) {
                    $embed->set('type', $type);
                }
                
                $embeds->add($embed);
            }
            
            $this->embeds = $embeds;
            
        } catch(\Exception $e) {
            $this->error("Error loading embeds: " . $e->getMessage());
        }
        
        return $embeds;
    }
    
    /**
     * Get embed by ID
     * 
     * @param int $id
     * @return Embedr|null
     */
    public function getById($id) {
        $id = (int) $id;
        if(!$id) return null;
        
        $embeds = $this->getAll();
        return $embeds->get("id=$id");
    }
    
    /**
     * Get embed by name
     * 
     * @param string $name
     * @return Embedr|null
     */
    public function get($name) {
        if(is_numeric($name)) {
            return $this->getById((int) $name);
        }
        
        $name = $this->wire('sanitizer')->name($name);
        if(!$name) return null;
        
        $embeds = $this->getAll();
        return $embeds->get("name=$name");
    }
    
    /**
     * Save embed
     * 
     * @param Embedr $embed
     * @return bool|int Returns ID on success, false on failure
     */
    public function save(Embedr $embed) {
        $database = $this->wire('database');
        $table = self::TABLE_NAME;
        
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
                '[Embedrs::save] Starting | ID=%s, Name=%s',
                $embed->id ?: 'new',
                $embed->name
            ));
        }
        
        // Validate
        $valid = $embed->validate();
        if($valid !== true) {
            if($debugMode) {
                $this->wire('log')->save('embedr-debug', '[Embedrs::save] Validation failed: ' . $valid);
            }
            $this->error($valid);
            return false;
        }
        
        // Sanitize
        $name = $this->wire('sanitizer')->name($embed->name);
        $title = $this->wire('sanitizer')->text($embed->title);
        $type_id = (int) $embed->type_id;
        $selector = $this->wire('sanitizer')->text($embed->selector);
        
        if($debugMode) {
            $this->wire('log')->save('embedr-debug', sprintf(
                '[Embedrs::save] After sanitize | Name=%s, TypeID=%s',
                $name,
                $type_id
            ));
        }
        
        try {
            if($embed->id) {
                if($debugMode) {
                    $this->wire('log')->save('embedr-debug', '[Embedrs::save] UPDATE mode - ID exists: ' . $embed->id);
                }
                
                // Update existing - check if name changed
                $query = $database->prepare("SELECT name FROM `$table` WHERE id = :id");
                $query->execute([':id' => $embed->id]);
                $oldName = $query->fetchColumn();
                
                if($debugMode) {
                    $this->wire('log')->save('embedr-debug', sprintf(
                        '[Embedrs::save] Old name from DB: %s, New name: %s',
                        $oldName,
                        $name
                    ));
                }
                
                // Only check duplicates if name actually changed
                if($oldName !== $name) {
                    $query = $database->prepare("SELECT COUNT(*) FROM `$table` WHERE name = :name AND id != :id");
                    $query->execute([':name' => $name, ':id' => $embed->id]);
                    if($query->fetchColumn() > 0) {
                        if($debugMode) {
                            $this->wire('log')->save('embedr-debug', '[Embedrs::save] Duplicate name detected');
                        }
                        $this->error("An embed with the name '{$name}' already exists");
                        return false;
                    }
                }
                
                // Update existing
                $sql = "UPDATE `$table` SET 
                    `name` = :name,
                    `title` = :title,
                    `type_id` = :type_id,
                    `selector` = :selector,
                    `modified` = :modified
                    WHERE `id` = :id";
                
                $query = $database->prepare($sql);
                $query->execute([
                    ':id' => $embed->id,
                    ':name' => $name,
                    ':title' => $title,
                    ':type_id' => $type_id,
                    ':selector' => $selector,
                    ':modified' => time(),
                ]);
                
                if($debugMode) {
                    $this->wire('log')->save('embedr-debug', sprintf(
                        '[Embedrs::save] UPDATE completed | ID=%s, Title=%s',
                        $embed->id,
                        $title
                    ));
                }
                
                $this->embeds = null; // Clear cache
                $this->message("Embed '{$title}' updated");
                return $embed->id;
                
            } else {
                if($debugMode) {
                    $this->wire('log')->save('embedr-debug', '[Embedrs::save] INSERT mode - creating new embed');
                }
                
                // Insert new
                $sql = "INSERT INTO `$table` 
                    (`name`, `title`, `type_id`, `selector`, `created`, `modified`) 
                    VALUES (:name, :title, :type_id, :selector, :created, :modified)";
                
                $now = time();
                $query = $database->prepare($sql);
                $query->execute([
                    ':name' => $name,
                    ':title' => $title,
                    ':type_id' => $type_id,
                    ':selector' => $selector,
                    ':created' => $now,
                    ':modified' => $now,
                ]);
                
                $embed->id = (int) $database->lastInsertId();
                $embed->created = $now;
                $embed->modified = $now;
                
                if($debugMode) {
                    $this->wire('log')->save('embedr-debug', sprintf(
                        '[Embedrs::save] INSERT completed | New ID=%s, Title=%s',
                        $embed->id,
                        $title
                    ));
                }
                
                $this->embeds = null; // Clear cache
                $this->message("Embed '{$title}' created");
                return $embed->id;
            }
            
        } catch(\Exception $e) {
            if($debugMode) {
                $this->wire('log')->save('embedr-debug', '[Embedrs::save] EXCEPTION: ' . $e->getMessage());
            }
            $this->error("Error saving embed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete embed
     * 
     * @param int|Embedr $embed
     * @return bool
     */
    public function delete($embed) {
        $database = $this->wire('database');
        $table = self::TABLE_NAME;
        
        $id = is_object($embed) ? $embed->id : (int) $embed;
        if(!$id) return false;
        
        try {
            $query = $database->prepare("DELETE FROM `$table` WHERE `id` = :id");
            $query->execute([':id' => $id]);
            
            $this->embeds = null; // Clear cache
            $this->message("Embed deleted");
            return true;
            
        } catch(\Exception $e) {
            $this->error("Error deleting embed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get embeds by type
     * 
     * @param int|EmbedrType $type
     * @return WireArray
     */
    public function findByType($type) {
        $type_id = is_object($type) ? $type->id : (int) $type;
        $embeds = $this->getAll();
        
        return $embeds->find("type_id=$type_id");
    }
}
