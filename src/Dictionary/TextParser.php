<?php
namespace App\Dictionary;

/**
 * TextParser - Extracts and lemmatizes words from text and PDF files
 * 
 * Version 3.0: Aggressive whitespace/hyphen repair for PDF artifacts.
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
     */
    public function parseFile($filePath, $fileType, $dictionaryId, $languageCode = 'en') {
        try {
            // 1. Extract raw text
            if ($fileType === 'pdf') {
                $text = $this->extractTextFromPdf($filePath);
            } else {
                $text = file_get_contents($filePath);
            }

            if (empty($text)) {
                return ['success' => false, 'lemmas_added' => 0, 'error' => 'No text extracted from file'];
            }

            // 2. Pre-process text (Global cleanups and de-hyphenation)
            $text = $this->preprocessText($text);

            // 3. Tokenize and lemmatize
            $lemmas = $this->extractLemmas($text, $languageCode);
            
            // 4. Store in DB
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
        if (!class_exists('\\Smalot\\PdfParser\\Parser')) {
            throw new \Exception('PDF Parser not available. Install: composer require smalot/pdfparser');
        }

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        return $pdf->getText();
    }

    /**
     * Clean raw text before tokenization
     * Critical step for fixing broken words.
     */
    private function preprocessText($text) {
        // 1. Unicode Normalization (Fix ligatures like ﬁ -> fi)
        if (class_exists('Normalizer')) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_KC);
        }

        // 2. Remove invisible control characters (except newlines/tabs)
        // This removes weird PDF artifacts that might sit inside words.
        $text = preg_replace('/[^\P{C}\n\r\t]/u', '', $text);

        // 3. Delete Soft Hyphens (\u00AD) entirely.
        // If we convert these to dashes, we risk creating "anti-gravity".
        // In PDFs, they are usually just break hints. Deleting them joins "anti" + "gravity" -> "antigravity".
        $text = str_replace("\xC2\xAD", '', $text); // UTF-8 byte sequence for soft hyphen
        $text = preg_replace('/\x{00AD}/u', '', $text);

        // 4. Handle Clause Separators (Em-dash/En-dash)
        // These should SPLIT words (e.g., "word—word" -> "word word").
        // We do this BEFORE stitching hyphens to avoid merging unrelated words.
        $text = preg_replace('/[\x{2013}\x{2014}]/u', ' ', $text); // En-dash, Em-dash -> Space

        // 5. Aggressive Hyphen Stitching
        // This fixes:
        // "antig-ravity"
        // "antig - ravity" (Space before hyphen)
        // "antig-\nravity" (Newline after hyphen)
        // "antig - \n ravity" (Messy whitespace)
        //
        // Logic: Capture Letter ($1), optional space, hyphen-like char, optional whitespace, Letter ($2).
        // Replace with $1$2 (Direct Join).
        $text = preg_replace_callback(
            '/(\p{L})\s*[-\x{2010}-\x{2012}\x{2212}]\s*(\p{L})/u', 
            function($matches) {
                return $matches[1] . $matches[2];
            }, 
            $text
        );

        // 6. Convert remaining punctuation/symbols to Spaces
        // Since we already stitched the hyphens we wanted to keep, all remaining dashes
        // are likely separators or bullet points.
        $text = str_replace(
            ['_', '/', '\\', '|', '[', ']', '(', ')', '{', '}', '<', '>', '“', '”', '’', '‘', '"', '.', ',', ':', ';', '!', '?', '*', '-', '+', '=', '&', '%', '$', '#', '@'], 
            ' ', 
            $text
        );

        return $text;
    }

    /**
     * Extract and lemmatize words from text
     */
    private function extractLemmas($text, $languageCode) {
        $text = mb_strtolower($text, 'UTF-8');
        
        // Final cleanup: Remove anything not a letter or space
        $text = preg_replace('/[^\p{L}\s]/u', '', $text);
        
        // Split by whitespace
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        $lemmaFrequency = [];
        
        foreach ($words as $word) {
            $word = trim($word);

            // Skip short words
            if (mb_strlen($word) < 3) {
                continue;
            }

            // Skip stop words
            if ($this->isStopWord($word)) {
                continue;
            }
            
            // Lemmatize
            $lemma = $this->lemmatize($word, $languageCode);
            
            // Validate length again
            if (mb_strlen($lemma) < 3) { 
                continue; 
            }

            if (!isset($lemmaFrequency[$lemma])) {
                $lemmaFrequency[$lemma] = 0;
            }
            $lemmaFrequency[$lemma]++;
        }
        
        return $lemmaFrequency;
    }

    /**
     * Simple lemmatization (stemming) for English
     */
    private function lemmatize($word, $languageCode) {
        if ($languageCode !== 'en') {
            return $word;
        }

        // Conservative stemming rules to prevent over-truncation
        $patterns = [
            '/ies$/' => 'y',
            '/ves$/' => 'f',
            '/sses$/' => 'ss',
            '/([^s])s$/' => '$1', 
            '/ing$/' => '',
            '/ed$/' => '',
            '/tion$/' => 'te',
            '/ment$/' => '',
            '/ness$/' => '',
            '/ly$/' => '',
            '/ful$/' => '',
            '/less$/' => '',
            '/ity$/' => 'y', // e.g. gravity -> gravy? No, purity -> pure. Careful here.
        ];

        // Specific fix for "gravity" / "antigravity" vs "purity"
        // The rule /ity$/ -> y works for "gravity" (gravy) which is wrong.
        // Let's stick to the safe list.
        $safePatterns = [
            '/ies$/' => 'y',
            '/ves$/' => 'f',
            '/sses$/' => 'ss',
            '/([^s])s$/' => '$1', 
            '/ing$/' => '',
            '/ed$/' => '',
            '/ment$/' => '',
            '/ness$/' => '',
            '/ly$/' => '',
        ];

        foreach ($safePatterns as $pattern => $replacement) {
            $lemma = preg_replace($pattern, $replacement, $word);
            if ($lemma !== $word) {
                if (mb_strlen($lemma) < 3) return $word;
                return $lemma;
            }
        }

        return $word;
    }

    private function storeLemmas($lemmas, $dictionaryId, $languageCode) {
        $this->pdo->beginTransaction();
        
        try {
            $lemmasAdded = 0;
            
            // Prepare statements
            $checkLemmaStmt = $this->pdo->prepare("SELECT id FROM dict_lemmas WHERE lemma = ? AND language_code = ?");
            $insertLemmaStmt = $this->pdo->prepare("INSERT INTO dict_lemmas (lemma, language_code, frequency) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE frequency = frequency + ?");
            $updateGlobalFreqStmt = $this->pdo->prepare("UPDATE dict_lemmas SET frequency = frequency + ? WHERE id = ?");
            $insertMappingStmt = $this->pdo->prepare("INSERT INTO dict_lemma_2_dictionary (dictionary_id, lemma_id, frequency_in_dict) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE frequency_in_dict = frequency_in_dict + ?");
            
            foreach ($lemmas as $lemma => $frequency) {
                // Check/Insert Lemma
                $checkLemmaStmt->execute([$lemma, $languageCode]);
                $existingLemma = $checkLemmaStmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($existingLemma) {
                    $lemmaId = $existingLemma['id'];
                    $updateGlobalFreqStmt->execute([$frequency, $lemmaId]);
                } else {
                    $insertLemmaStmt->execute([$lemma, $languageCode, $frequency, $frequency]);
                    $lemmaId = $this->pdo->lastInsertId();
                }
                
                // Link to Dictionary
                $insertMappingStmt->execute([$dictionaryId, $lemmaId, $frequency, $frequency]);
                $lemmasAdded++;
            }
            
            // Update Stats
            $this->pdo->prepare("UPDATE dict_dictionaries SET total_lemmas = (SELECT COUNT(*) FROM dict_lemma_2_dictionary WHERE dictionary_id = ?) WHERE id = ?")->execute([$dictionaryId, $dictionaryId]);
            
            $this->pdo->commit();
            return $lemmasAdded;
            
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function loadStopWords() {
        $this->stopWords = [
            'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'her',
            'was', 'one', 'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how',
            'man', 'new', 'now', 'old', 'see', 'two', 'way', 'who', 'boy', 'did',
            'its', 'let', 'put', 'say', 'she', 'too', 'use', 'dad', 'mom', 
            'this', 'that', 'with', 'have', 'from', 'they', 'been', 'were', 'said',
            'each', 'which', 'their', 'there', 'would', 'could', 'about', 'into',
            'than', 'them', 'these', 'some', 'what', 'when', 'your', 'more', 'will',
            'just', 'very', 'such', 'because', 'through', 'should', 'before', 'after',
            'where', 'why', 'does', 'doing', 'off', 'over', 'own', 'down', 'up',
            'abc', 'iii', 'page', 'vol', 'chapter', 'ii', 'iv', 'vi', 'vii', 'viii',
            'http', 'https', 'www', 'com', 'org'
        ];
    }

    private function isStopWord($word) {
        return in_array($word, $this->stopWords);
    }
}
