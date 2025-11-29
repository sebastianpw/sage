<?php
namespace App\Posts;

class PostManager {
    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getAllPosts() {
        // CHANGED: The ORDER BY clause now prioritizes sort_order, then creation date.
        $stmt = $this->pdo->query("SELECT * FROM posts ORDER BY sort_order DESC, created_at DESC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getPostById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    public function getPostBySlug($slug) {
        $stmt = $this->pdo->prepare("SELECT * FROM posts WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function createPost($data) {
        if (empty($data['slug'])) {
            $data['slug'] = $this->slugify($data['title']);
        }

        $sanitized_media_items = $this->convertJsObjectStringToJson($data['media_items']);

        // CHANGED: Added sort_order to the INSERT statement.
        $sql = "INSERT INTO posts (title, slug, post_type, preview_image_url, content, media_items, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $this->pdo->prepare($sql);
        
        $params = [
            $data['title'],
            $data['slug'],
            $data['post_type'],
            $data['preview_image_url'],
            $data['content'] ?? null,
            $sanitized_media_items,
            (int)($data['sort_order'] ?? 0) // CHANGED: Added sort_order parameter
        ];

        if ($stmt->execute($params)) {
            return $this->pdo->lastInsertId();
        }
        return false;
    }

    public function updatePost($id, $data) {
        if (empty($data['slug'])) {
            $data['slug'] = $this->slugify($data['title']);
        }
      
        $sanitized_media_items = $this->convertJsObjectStringToJson($data['media_items']);
      
        // CHANGED: Added sort_order to the UPDATE statement.
        $sql = "UPDATE posts SET title = ?, slug = ?, post_type = ?, preview_image_url = ?, content = ?, media_items = ?, sort_order = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['post_type'],
            $data['preview_image_url'],
            $data['content'] ?? null,
            $sanitized_media_items,
            (int)($data['sort_order'] ?? 0), // CHANGED: Added sort_order parameter
            $id
        ]);
    }

    public function deletePost($id) {
        $stmt = $this->pdo->prepare("DELETE FROM posts WHERE id = ?");
        return $stmt->execute([$id]);
    }

    private function convertJsObjectStringToJson($jsString) {
        $jsString = trim($jsString);
        if (substr($jsString, -1) === ';') {
            $jsString = substr($jsString, 0, -1);
        }
        $jsonString = preg_replace('/([{,]\s*)(\w+)(\s*:)/', '$1"$2"$3', $jsString);
        $jsonString = preg_replace("/'/", '"', $jsonString);
        return $jsonString;
    }

    private function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        if (empty($text)) {
            return 'n-a-' . time();
        }
        return $text;
    }
}

