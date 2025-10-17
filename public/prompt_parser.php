<?php
// prompt_parser.php

require_once 'bootstrap.php'; // provides $pdo (PDO instance)

//var_dump("prompt_parser.php was loaded");

class PromptParser
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Expand a template string with placeholders into a final prompt.
     *
     * @param string $template Template string with placeholders
     * @return string Expanded prompt
     */
    public function parseTemplate(string $template): string
    {
        // Regex to match placeholders: <table attr=value [desc|abbr] [style=style]>
        $pattern = '/<([a-z_]+)\s+([^>]+)>/i';

        return preg_replace_callback($pattern, function ($matches) {
            $table = $matches[1]; // e.g., characters, locations
            $attrString = $matches[2];

            // Parse attributes into key => value
            preg_match_all('/(\w+)(?:=([^\s]+))?/', $attrString, $attrMatches, PREG_SET_ORDER);

            $lookupField = null;
            $lookupValue = null;
            $useDesc = false; // default is desc_abbr
            $style = 'normal'; // default

            foreach ($attrMatches as $attr) {
                $key = strtolower($attr[1]);
                $value = isset($attr[2]) ? $attr[2] : null;

                switch ($key) {
                    case 'id':
                    case 'name':
                        if ($lookupField !== null) {
                            throw new Exception("Placeholder cannot have both 'id' and 'name'");
                        }
                        $lookupField = $key;
                        $lookupValue = $value;
                        break;
                    case 'desc':
                        $useDesc = true;
                        break;
                    case 'abbr':
                        $useDesc = false;
                        break;
                    case 'style':
                        $style = strtolower($value);
                        break;
                }
            }

            if (!$lookupField) {
                throw new Exception("Placeholder must have either 'id' or 'name'");
            }

            // Lookup entity in DB
            if ($lookupField === 'id') {
                $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE id = :val LIMIT 1");
                $stmt->execute([':val' => $lookupValue]);
            } else {
                $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE name = :val LIMIT 1");
                $stmt->execute([':val' => $lookupValue]);
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new Exception("Entity not found in table $table with $lookupField=$lookupValue");
            }

	    // Get the description
	    $descText = $row['name'] . " - ";
            $descText .= $useDesc ? $row['description'] : $row['desc_abbr'];

            // Apply style using parentheses instead of square brackets
            switch ($style) {
                case 'light':
                    $wrapped = "($descText)";      // single parentheses
                    break;
                case 'strong':
                    $wrapped = "(($descText))";    // double parentheses
                    break;
                default:
                    $wrapped = "($descText)";      // default single parentheses
                    break;
            }

            return $wrapped;
        }, $template);
    }
}


