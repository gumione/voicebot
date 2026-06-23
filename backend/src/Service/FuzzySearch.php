<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Typo-tolerant matching for the sample search, used as a fallback when the
 * exact FULLTEXT query returns nothing. No external search engine: the catalog
 * is small enough to score in PHP on the rare empty-result query.
 *
 * Signals (the best of the two wins):
 *   - word coverage via multibyte Damerau-Levenshtein (OSA distance) — catches
 *     substitutions, insertions, deletions AND adjacent transpositions, the most
 *     common typo (e.g. "моер" → "море");
 *   - character-trigram Jaccard over the whole query — robust to word boundaries.
 *
 * Plain PHP levenshtein() is byte-based and so wrong for Cyrillic UTF-8 (2 bytes
 * per letter); everything here works on codepoint arrays instead.
 *
 * Also offers keyboard-layout correction (Latin typed in a RU layout, e.g.
 * "vjht" → "море"), the other classic cause of "nothing found".
 *
 * Ported from sahar.lc/backend (App\Service\FuzzySearch).
 */
class FuzzySearch
{
    /** QWERTY key → ЙЦУКЕН letter (Windows RU layout). Lowercase. */
    private const LAYOUT = [
        'q' => 'й', 'w' => 'ц', 'e' => 'у', 'r' => 'к', 't' => 'е', 'y' => 'н', 'u' => 'г', 'i' => 'ш', 'o' => 'щ', 'p' => 'з', '[' => 'х', ']' => 'ъ',
        'a' => 'ф', 's' => 'ы', 'd' => 'в', 'f' => 'а', 'g' => 'п', 'h' => 'р', 'j' => 'о', 'k' => 'л', 'l' => 'д', ';' => 'ж', "'" => 'э',
        'z' => 'я', 'x' => 'ч', 'c' => 'с', 'v' => 'м', 'b' => 'и', 'n' => 'т', 'm' => 'ь', ',' => 'б', '.' => 'ю', '/' => '.', '`' => 'ё',
    ];

    /**
     * Rank candidates by similarity to $query; return matching ids, best first.
     *
     * @param array<int, array{id:int, name:string|null}> $candidates
     * @return int[]
     */
    public function match(string $query, array $candidates, float $threshold = 0.66, int $limit = 60): array
    {
        $q = $this->normalize($query);
        if (mb_strlen($q, 'UTF-8') < 2) return [];
        $qWords = $this->words($q);
        $qTri = $this->trigrams($q);

        $best = []; // id => highest score
        foreach ($candidates as $c) {
            $name = $this->normalize((string) ($c['name'] ?? ''));
            if ($name === '') continue;
            $score = $this->similarity($q, $qWords, $qTri, $name);
            if ($score < $threshold) continue;
            $id = (int) $c['id'];
            if (!isset($best[$id]) || $score > $best[$id]) $best[$id] = $score;
        }
        arsort($best);
        return array_slice(array_keys($best), 0, $limit);
    }

    /** If $query is plain Latin, return it remapped to the RU layout; else null. */
    public function swapLayout(string $query): ?string
    {
        $q = mb_strtolower(trim($query), 'UTF-8');
        if ($q === '' || preg_match('/\p{Cyrillic}/u', $q) || !preg_match('/[a-z]/i', $q)) return null;
        $out = '';
        foreach ($this->chars($q) as $ch) $out .= self::LAYOUT[$ch] ?? $ch;
        return $out !== $q ? $out : null;
    }

    // ── scoring ───────────────────────────────────────────────

    /** @param string[] $qWords @param string[] $qTri */
    private function similarity(string $q, array $qWords, array $qTri, string $name): float
    {
        if (str_contains($name, $q)) return 1.0; // safety net (FULLTEXT normally catches this)
        $nameWords = $this->words($name);

        // Word coverage: every query word must find a close word in the name.
        $sum = 0.0;
        foreach ($qWords as $qw) {
            $bestW = 0.0;
            foreach ($nameWords as $nw) {
                $bestW = max($bestW, $this->wordSim($qw, $nw));
                if ($bestW === 1.0) break;
            }
            $sum += $bestW;
        }
        $wordScore = $qWords ? $sum / count($qWords) : 0.0;

        // Trigram overlap (slightly discounted) as an independent signal.
        $triScore = $this->jaccard($qTri, $this->trigrams($name)) * 0.9;

        return max($wordScore, $triScore);
    }

    private function wordSim(string $a, string $b): float
    {
        if ($a === $b) return 1.0;
        $la = mb_strlen($a, 'UTF-8');
        $lb = mb_strlen($b, 'UTF-8');
        $max = max($la, $lb);
        if ($max === 0) return 0.0;
        $allowed = $la <= 4 ? 1 : ($la <= 7 ? 2 : 3);
        $d = $this->osa($this->chars($a), $this->chars($b));
        return $d > $allowed ? 0.0 : 1.0 - $d / $max;
    }

    // ── multibyte primitives ──────────────────────────────────

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = str_replace('ё', 'е', $s);
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    /** @return string[] */
    private function words(string $s): array
    {
        return $s === '' ? [] : explode(' ', $s);
    }

    /** @return string[] individual codepoints */
    private function chars(string $s): array
    {
        return preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Optimal String Alignment distance (Damerau-Levenshtein restricted to
     * adjacent transpositions). Operates on codepoint arrays.
     *
     * @param string[] $a
     * @param string[] $b
     */
    private function osa(array $a, array $b): int
    {
        $la = count($a);
        $lb = count($b);
        if ($la === 0) return $lb;
        if ($lb === 0) return $la;
        $d = [];
        for ($i = 0; $i <= $la; $i++) $d[$i][0] = $i;
        for ($j = 0; $j <= $lb; $j++) $d[0][$j] = $j;
        for ($i = 1; $i <= $la; $i++) {
            for ($j = 1; $j <= $lb; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $cost);
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }
        return $d[$la][$lb];
    }

    /** @return string[] character trigrams of the spaceless string */
    private function trigrams(string $s): array
    {
        $chars = $this->chars(str_replace(' ', '', $s));
        $n = count($chars);
        if ($n < 3) return $n > 0 ? [implode('', $chars)] : [];
        $out = [];
        for ($i = 0; $i + 3 <= $n; $i++) $out[implode('', array_slice($chars, $i, 3))] = true;
        return array_keys($out);
    }

    /** @param string[] $a @param string[] $b */
    private function jaccard(array $a, array $b): float
    {
        if (!$a || !$b) return 0.0;
        $sa = array_flip($a);
        $inter = 0;
        foreach ($b as $t) if (isset($sa[$t])) $inter++;
        $union = count($a) + count($b) - $inter;
        return $union > 0 ? $inter / $union : 0.0;
    }
}
