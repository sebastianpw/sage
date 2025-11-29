<?php
namespace App\Dictionary;

/**
 * TextParser - Extracts and lemmatizes words from text and PDF files
 * 
 * This class processes text files and PDFs to extract unique lemmas (base word forms).
 * For English, it uses a simple stemming approach. For better results, consider
 * integrating with external NLP services or libraries.
 */
class TextParser {
    private $pdo;
    private $stopWords = [];
    
    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
        $this->loadStopWords();
    }

    /**
     * Parse a file and extract lemmas
     * 
     * @param string $filePath Full path to the file
     * @param string $fileType 'txt' or 'pdf'
     * @param int $dictionaryId Dictionary to associate lemmas with
     * @param string $languageCode Language code for lemmatization
     * @return array ['success' => bool, 'lemmas_added' => int, 'error' => string|null]
     */
    public function parseFile($filePath, $fileType, $dictionaryId, $languageCode = 'en') {
        try {
            // Extract text based on file type
            if ($fileType === 'pdf') {
                $text = $this->extractTextFromPdf($filePath);
            } else {
                $text = file_get_contents($filePath);
            }

            if (empty($text)) {
                return ['success' => false, 'lemmas_added' => 0, 'error' => 'No text extracted from file'];
            }

            // Tokenize and lemmatize
            $lemmas = $this->extractLemmas($text, $languageCode);
            
            // Store lemmas in database
            $lemmasAdded = $this->storeLemmas($lemmas, $dictionaryId, $languageCode);

            return [
                'success' => true,
                'lemmas_added' => $lemmasAdded,
                'total_unique_lemmas' => count($lemmas),
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'lemmas_added' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract text from PDF using Smalot PDF Parser
     */
    private function extractTextFromPdf($filePath) {
        // Check if PDF parser is available
        if (!class_exists('\\Smalot\\PdfParser\\Parser')) {
            throw new \Exception('PDF Parser not available. Install: composer require smalot/pdfparser');
        }

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        return $pdf->getText();
    }

    /**
     * Extract and lemmatize words from text
     * 
     * @return array Associative array ['lemma' => frequency]
     */
    private function extractLemmas($text, $languageCode) {
        // Normalize text
        $text = mb_strtolower($text, 'UTF-8');
        
        // Remove punctuation but keep letters with accents
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        $lemmaFrequency = [];
        
        foreach ($words as $word) {
            // Skip very short words and stop words
            if (mb_strlen($word) < 3 || $this->isStopWord($word)) {
                continue;
            }
            
            // Lemmatize (simplified version)
            $lemma = $this->lemmatize($word, $languageCode);
            
            if (!isset($lemmaFrequency[$lemma])) {
                $lemmaFrequency[$lemma] = 0;
            }
            $lemmaFrequency[$lemma]++;
        }
        
        return $lemmaFrequency;
    }

    /**
     * Simple lemmatization (stemming) for English
     * For production use, consider external NLP services
     */
    private function lemmatize($word, $languageCode) {
        if ($languageCode !== 'en') {
            return $word; // For non-English, return as-is (extend as needed)
        }

        // Simple English suffix removal rules
        $patterns = [
            '/ies$/' => 'y',
            '/ves$/' => 'f',
            '/ses$/' => 's',
            '/sses$/' => 'ss',
            '/([^s])s$/' => '$1',
            '/ing$/' => '',
            '/ed$/' => '',
            '/tion$/' => 't',
            '/ment$/' => '',
            '/ness$/' => '',
            '/ful$/' => '',
            '/less$/' => '',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $lemma = preg_replace($pattern, $replacement, $word);
            if ($lemma !== $word) {
                return $lemma;
            }
        }

        return $word;
    }

    /**
     * Store lemmas in database with proper deduplication
     */
    private function storeLemmas($lemmas, $dictionaryId, $languageCode) {
        $this->pdo->beginTransaction();
        
        try {
            $lemmasAdded = 0;
            
            // Prepare statements
            $checkLemmaStmt = $this->pdo->prepare(
                "SELECT id FROM dict_lemmas WHERE lemma = ? AND language_code = ?"
            );
            
            $insertLemmaStmt = $this->pdo->prepare(
                "INSERT INTO dict_lemmas (lemma, language_code, frequency) 
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE frequency = frequency + ?"
            );
            
            $insertMappingStmt = $this->pdo->prepare(
                "INSERT INTO dict_lemma_2_dictionary (dictionary_id, lemma_id, frequency_in_dict) 
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE frequency_in_dict = frequency_in_dict + ?"
            );
            
            foreach ($lemmas as $lemma => $frequency) {
                // Check if lemma exists
                $checkLemmaStmt->execute([$lemma, $languageCode]);
                $existingLemma = $checkLemmaStmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($existingLemma) {
                    $lemmaId = $existingLemma['id'];
                    // Update global frequency
                    $updateStmt = $this->pdo->prepare(
                        "UPDATE dict_lemmas SET frequency = frequency + ? WHERE id = ?"
                    );
                    $updateStmt->execute([$frequency, $lemmaId]);
                } else {
                    // Insert new lemma
                    $insertLemmaStmt->execute([$lemma, $languageCode, $frequency, $frequency]);
                    $lemmaId = $this->pdo->lastInsertId();
                }
                
                // Create or update mapping to dictionary
                $insertMappingStmt->execute([$dictionaryId, $lemmaId, $frequency, $frequency]);
                $lemmasAdded++;
            }
            
            // Update dictionary lemma count
            $updateCountStmt = $this->pdo->prepare(
                "UPDATE dict_dictionaries 
                 SET total_lemmas = (
                     SELECT COUNT(*) FROM dict_lemma_2_dictionary WHERE dictionary_id = ?
                 )
                 WHERE id = ?"
            );
            $updateCountStmt->execute([$dictionaryId, $dictionaryId]);
            
            $this->pdo->commit();
            return $lemmasAdded;
            
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Load common stop words to exclude
     */
    private function loadStopWords() {
        // Common English stop words
        $this->stopWords = [
            'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'her',
            'was', 'one', 'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how',
            'man', 'new', 'now', 'old', 'see', 'two', 'way', 'who', 'boy', 'did',
            'its', 'let', 'put', 'say', 'she', 'too', 'use', 'dad', 'mom', 'the',
            'this', 'that', 'with', 'have', 'from', 'they', 'been', 'were', 'said',
            'each', 'which', 'their', 'there', 'would', 'could', 'about', 'into',
            'than', 'them', 'these', 'some', 'what', 'when', 'your', 'more', 'will',
            'just', 'very', 'such', 'because', 'through', 'should', 'before', 'after'
        ];
    }

    private function isStopWord($word) {
        return in_array($word, $this->stopWords);
    }
}
