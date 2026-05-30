<?php

/**
 * Handles Sequence CRUD
 * Update: Supports complex item structures in JSON (Arrays/Objects)
 */
class SequenceManager {
    private $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function save($name, $desc, $items, $docId, $seqId = null) {
        // Sanitize: remove empty items, support both IDs (scalar) and Objects
        $cleanItems = array_values(array_filter($items, function($v) {
            if (is_array($v)) return !empty($v);
            return !empty($v);
        }));
        
        $jsonData = json_encode($cleanItems);

        if ($seqId) {
            $stmt = $this->pdo->prepare("UPDATE narrative_sequences SET name=?, description=?, sequence_data=?, linked_doc_id=? WHERE id=?");
            $stmt->execute([$name, $desc, $jsonData, $docId, $seqId]);
            return $seqId;
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO narrative_sequences (name, description, sequence_data, linked_doc_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $desc, $jsonData, $docId]);
            return $this->pdo->lastInsertId();
        }
    }

    public function getAll() {
        return $this->pdo->query("SELECT * FROM narrative_sequences ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
}
