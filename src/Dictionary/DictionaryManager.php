<?php
namespace App\Dictionary;

class DictionaryManager {
    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ========== DICTIONARY CRUD ==========
    
    public function getAllDictionaries() {
        $stmt = $this->pdo->query("
            SELECT d.*, 
                   COUNT(DISTINCT l2d.lemma_id) as actual_lemma_count
            FROM dict_dictionaries d
            LEFT JOIN dict_lemma_2_dictionary l2d ON d.id = l2d.dictionary_id
            GROUP BY d.id
            ORDER BY d.sort_order DESC, d.created_at DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getDictionaryById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM dict_dictionaries WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function createDictionary($data) {
        if (empty($data['slug'])) {
            $data['slug'] = $this->slugify($data['title']);
        }

        $sql = "INSERT INTO dict_dictionaries 
                (title, slug, description, source_author, source_title, language_code, sort_order, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        
        $params = [
            $data['title'],
            $data['slug'],
            $data['description'] ?? null,
            $data['source_author'] ?? null,
            $data['source_title'] ?? null,
            $data['language_code'] ?? 'en',
            (int)($data['sort_order'] ?? 0)
        ];

        if ($stmt->execute($params)) {
            return $this->pdo->lastInsertId();
        }
        return false;
    }

    public function updateDictionary($id, $data) {
        if (empty($data['slug'])) {
            $data['slug'] = $this->slugify($data['title']);
        }

        $sql = "UPDATE dict_dictionaries 
                SET title = ?, slug = ?, description = ?, source_author = ?, 
                    source_title = ?, language_code = ?, sort_order = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['description'] ?? null,
            $data['source_author'] ?? null,
            $data['source_title'] ?? null,
            $data['language_code'] ?? 'en',
            (int)($data['sort_order'] ?? 0),
            $id
        ]);
    }

    public function deleteDictionary($id) {
        $stmt = $this->pdo->prepare("DELETE FROM dict_dictionaries WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // ========== LEMMA OPERATIONS ==========

    public function getLemmaById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM dict_lemmas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    
    
    
    
    

    public function searchLemmas($search = '', $dictId = null, $limit = 50, $offset = 0) {
        $where = [];
        $params = [];

        if (!empty($search)) {
            $where[] = "l.lemma LIKE ?";
            $params[] = "%{$search}%";
        }

        if ($dictId) {
            $where[] = "l2d.dictionary_id = ?";
            $params[] = $dictId;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT l.*, 
                       GROUP_CONCAT(DISTINCT l2d.dictionary_id) as dictionary_ids,
                       SUM(l2d.frequency_in_dict) as total_frequency
                FROM dict_lemmas l
                LEFT JOIN dict_lemma_2_dictionary l2d ON l.id = l2d.lemma_id
                {$whereClause}
                GROUP BY l.id
                ORDER BY l.lemma ASC
                LIMIT ? OFFSET ?";

        // ===== START OF CHANGES =====

        $stmt = $this->pdo->prepare($sql);

        // Bind the WHERE clause parameters first. Placeholders are 1-indexed.
        $paramIndex = 1;
        foreach ($params as $param) {
            $stmt->bindValue($paramIndex, $param);
            $paramIndex++;
        }

        // Now, explicitly bind LIMIT and OFFSET as integers.
        $stmt->bindValue($paramIndex, (int)$limit, \PDO::PARAM_INT);
        $stmt->bindValue($paramIndex + 1, (int)$offset, \PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // ===== END OF CHANGES =====
    }


    
    
    
    
    
    
    

    public function countLemmas($search = '', $dictId = null) {
        $where = [];
        $params = [];

        if (!empty($search)) {
            $where[] = "l.lemma LIKE ?";
            $params[] = "%{$search}%";
        }

        if ($dictId) {
            $where[] = "l2d.dictionary_id = ?";
            $params[] = $dictId;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT COUNT(DISTINCT l.id) as total
                FROM dict_lemmas l
                LEFT JOIN dict_lemma_2_dictionary l2d ON l.id = l2d.lemma_id
                {$whereClause}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetch(\PDO::FETCH_ASSOC)['total'];
    }

    public function updateLemmaDictionaries($lemmaId, $dictionaryIds) {
        // Start transaction
        $this->pdo->beginTransaction();

        try {
            // Remove all existing mappings for this lemma
            $stmt = $this->pdo->prepare("DELETE FROM dict_lemma_2_dictionary WHERE lemma_id = ?");
            $stmt->execute([$lemmaId]);

            // Add new mappings
            if (!empty($dictionaryIds)) {
                $insertStmt = $this->pdo->prepare(
                    "INSERT INTO dict_lemma_2_dictionary (dictionary_id, lemma_id, frequency_in_dict) 
                     VALUES (?, ?, 1)
                     ON DUPLICATE KEY UPDATE frequency_in_dict = frequency_in_dict"
                );

                foreach ($dictionaryIds as $dictId) {
                    $insertStmt->execute([$dictId, $lemmaId]);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function deleteLemma($id) {
        // Cascading delete will handle dict_lemma_2_dictionary entries
        $stmt = $this->pdo->prepare("DELETE FROM dict_lemmas WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // ========== SOURCE FILE OPERATIONS ==========

    public function getSourceFilesByDictId($dictId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM dict_source_files 
            WHERE dictionary_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$dictId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function addSourceFile($dictId, $filename, $originalName, $filePath, $fileType, $fileSize) {
        $sql = "INSERT INTO dict_source_files 
                (dictionary_id, filename, original_filename, file_path, file_type, file_size, parse_status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt = $this->pdo->prepare($sql);
        
        if ($stmt->execute([$dictId, $filename, $originalName, $filePath, $fileType, $fileSize])) {
            return $this->pdo->lastInsertId();
        }
        return false;
    }

    public function updateFileParseStatus($fileId, $status, $lemmasExtracted = null, $errorMsg = null) {
        $updates = ["parse_status = ?"];
        $params = [$status];

        if ($status === 'processing') {
            $updates[] = "parse_started_at = NOW()";
        } elseif ($status === 'completed' || $status === 'failed') {
            $updates[] = "parse_completed_at = NOW()";
        }

        if ($lemmasExtracted !== null) {
            $updates[] = "lemmas_extracted = ?";
            $params[] = $lemmasExtracted;
        }

        if ($errorMsg !== null) {
            $updates[] = "error_message = ?";
            $params[] = $errorMsg;
        }

        $params[] = $fileId;

        $sql = "UPDATE dict_source_files SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    // ========== HELPER METHODS ==========

    private function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        if (empty($text)) {
            return 'dict-' . time();
        }
        return $text;
    }

    public function updateDictionaryLemmaCount($dictId) {
        $stmt = $this->pdo->prepare("
            UPDATE dict_dictionaries 
            SET total_lemmas = (
                SELECT COUNT(*) FROM dict_lemma_2_dictionary WHERE dictionary_id = ?
            )
            WHERE id = ?
        ");
        return $stmt->execute([$dictId, $dictId]);
    }
}
