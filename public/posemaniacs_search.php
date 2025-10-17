<?php 
require_once __DIR__ . '/bootstrap.php'; 
require __DIR__ . '/env_locals.php';

// posemaniacs_search.php
// Fetch a Posemaniacs category page (or crawl pages) and return pose items as JSON.
// Usage:
//   /posemaniacs_search.php?tag=sitting            -> crawl pages until up to maxEntries (default 100)
//   /posemaniacs_search.php?tag=sexy&page=2       -> fetch only page 2
//   /posemaniacs_search.php?tag=sitting&per_page=50 -> limit returned items to 50

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function curl_get(string $url, int $timeout = 15): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => 'pose-proxy/1.0 (+yourdomain.example)',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false || $code >= 400) {
        throw new RuntimeException("Failed to fetch {$url} (HTTP {$code}) {$err}");
    }
    return $body;
}

/**
 * Resolve a relative URL against a base URL.
 * Basic but practical for the target site.
 */
function resolve_relative(string $base, string $rel): string {
    if (!$rel) return $rel;
    // Already absolute
    if (parse_url($rel, PHP_URL_SCHEME) !== null) return $rel;
    // Protocol-relative
    if (strpos($rel, '//') === 0) {
        $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
        return $scheme . ':' . $rel;
    }
    $baseParts = parse_url($base);
    $scheme = $baseParts['scheme'] ?? 'https';
    $host = $baseParts['host'] ?? '';
    $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
    $origin = $scheme . '://' . $host . $port;

    // If rel starts with /
    if (strpos($rel, '/') === 0) {
        return $origin . $rel;
    }

    // Remove query/fragment from base path
    $path = $baseParts['path'] ?? '/';
    // remove filename portion
    $dir = preg_replace('#/[^/]*$#', '/', $path);
    $candidate = rtrim($origin . $dir, '/') . '/' . $rel;

    // Normalize ../ and ./
    while (strpos($candidate, '/./') !== false) {
        $candidate = str_replace('/./', '/', $candidate);
    }
    // resolve ../
    while (preg_match('#/(?!\.\.)[^/]+/\.\./#', $candidate)) {
        $candidate = preg_replace('#/(?!\.\.)[^/]+/\.\./#', '/', $candidate);
    }
    // final tidy
    return $candidate;
}

try {
    $tag = isset($_GET['tag']) ? trim((string)$_GET['tag']) : '';
    if ($tag === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'missing tag parameter']);
        exit;
    }

    // Optional page param: if provided we fetch that page only.
    $pageParam = isset($_GET['page']) ? (int)$_GET['page'] : 0;
    $singlePageMode = ($pageParam >= 1);

    // Optional per_page param: number of results to return (cap to avoid abuse).
    $perPageParam = isset($_GET['per_page']) ? max(1, min(500, (int)$_GET['per_page'])) : 100;

    // Set limits
    $maxEntries = $perPageParam;               // how many results to return total
    $results = [];
    $seen = [];

    // Build initial category URL (pattern)
    $baseHost = 'https://www.posemaniacs.com';
    $firstUrl = $baseHost . '/poses/' . rawurlencode($tag);

    // If a page was requested, append ?page=N (or &page=N if existing query)
    if ($singlePageMode) {
        $firstUrl .= '?page=' . $pageParam;
    }

    // Crawl pages starting from firstUrl. If singlePageMode, only fetch that page (maxPages = 1)
    $nextUrl = $firstUrl;
    $fetchedPages = 0;
    $maxPages = $singlePageMode ? 1 : 10; // safety cap (when crawling multiple pages)
    libxml_use_internal_errors(true);

    while ($nextUrl && count($results) < $maxEntries && $fetchedPages < $maxPages) {
        $fetchedPages++;
        $html = curl_get($nextUrl);

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new DOMXPath($dom);

        // Collect <img> tags that look like pose thumbnails.
        $imgNodes = $xpath->query('//img');

        foreach ($imgNodes as $img) {
            /** @var DOMElement $img */
            $src = $img->getAttribute('src') ?: $img->getAttribute('data-src') ?: $img->getAttribute('data-original');
            if (!$src) continue;

            // skip data: URIs and likely UI sprites
            if (strpos($src, 'data:') === 0) continue;
            if (preg_match('#(logo|sprite|icon|spinner|loader|thumb-blank)#i', $src)) continue;

            // Heuristic: require likely path content or numeric id as a sign it's a pose thumbnail
            if (!preg_match('#/poses/|/uploads/|/thumb|/pose#i', $src) && !preg_match('#\d{3,}#', $src)) {
                $lower = strtolower($src);
                if (strpos($lower, '.svg') !== false || strpos($lower, 'badge') !== false) continue;
            }

            // Find enclosing <a> ancestor to get the pose page link when available
            $link = null;
            $node = $img;
            while ($node && $node->nodeType === XML_ELEMENT_NODE) {
                if ($node->nodeName === 'a') {
                    $link = $node->getAttribute('href');
                    break;
                }
                $node = $node->parentNode;
            }

            $thumb = resolve_relative($nextUrl, $src);
            $linkResolved = $link ? resolve_relative($nextUrl, $link) : null;

            // Attempt to extract numeric id if possible
            $id = null;
            if ($linkResolved && preg_match('#/poses/([^/]+)#i', $linkResolved, $m)) {
                if (preg_match('#(\d{3,})#', $linkResolved, $m2)) $id = $m2[1];
                else $id = trim($m[1], '/');
            }
            if (!$id && preg_match('#(\d{3,})#', $thumb, $m3)) $id = $m3[1];

            // Title from alt or title attribute
            $title = trim($img->getAttribute('alt') ?: $img->getAttribute('title') ?: '');

            // De-dupe by thumb URL
            $key = $thumb;
            if ($key === '') continue;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $results[] = [
                'id' => $id,
                'title' => $title ?: null,
                'thumb' => $thumb,
                'link' => $linkResolved ?: null
            ];

            if (count($results) >= $maxEntries) break;
        } // end foreach imgNodes

        // If single page mode, do not follow next links
        if ($singlePageMode) {
            break;
        }

        // Find Next link on the page (common patterns)
        $nextLink = null;
        $candidateNodes = $xpath->query('//a[contains(translate(normalize-space(.),"NEXT","next"),"next") or contains(@class,"next") or normalize-space(.)=">"]');
        if ($candidateNodes->length > 0) {
            foreach ($candidateNodes as $cn) {
                $txt = trim($cn->textContent);
                if ($txt === '' && $cn->hasAttribute('rel') && strtolower($cn->getAttribute('rel')) === 'next') {
                    $nextLink = $cn->getAttribute('href');
                    break;
                }
                if ($txt === '>' || stripos($txt, 'next') !== false) {
                    $nextLink = $cn->getAttribute('href');
                    break;
                }
            }
        } else {
            // fallback: <link rel="next">
            $relNext = $xpath->query('//link[@rel="next"]')->item(0);
            if ($relNext) $nextLink = $relNext->getAttribute('href');
        }

        if ($nextLink) {
            $nextUrl = resolve_relative($nextUrl, $nextLink);
        } else {
            $nextUrl = null;
        }
    } // end while pages

    echo json_encode([
        'success' => true,
        'source' => $firstUrl,
        'requested_page' => $singlePageMode ? $pageParam : null,
        'fetched_pages' => $fetchedPages,
        'count' => count($results),
        'results' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
