<?php
// Codé avec @grok https://x.com/i/grok/share/9515efaaeeb140908ff8be96902a2743
// Démo: https://www.chezyann.net/Levenshtein-Quaternion.php
/**
 * MIT License
 *
 * Copyright (c) 2026
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

// ======================================================
// HYBRIDE AVANCÉ : Levenshtein + Quaternion + Catégories
// (basé sur ton code original)
// ======================================================

// ------------------ LEVENSHTEIN ------------------
function levenshteinDistance(string $s1, string $s2): int
{
    $len1 = mb_strlen($s1, 'UTF-8');
    $len2 = mb_strlen($s2, 'UTF-8');

    if ($len1 === 0) return $len2;
    if ($len2 === 0) return $len1;

    $matrix = array_fill(0, $len1 + 1, array_fill(0, $len2 + 1, 0));

    for ($i = 0; $i <= $len1; $i++) $matrix[$i][0] = $i;
    for ($j = 0; $j <= $len2; $j++) $matrix[0][$j] = $j;

    for ($i = 1; $i <= $len1; $i++) {
        $char1 = mb_substr($s1, $i - 1, 1, 'UTF-8');
        for ($j = 1; $j <= $len2; $j++) {
            $char2 = mb_substr($s2, $j - 1, 1, 'UTF-8');
            $cost = ($char1 === $char2) ? 0 : 1;

            $matrix[$i][$j] = min(
                $matrix[$i - 1][$j] + 1,
                $matrix[$i][$j - 1] + 1,
                $matrix[$i - 1][$j - 1] + $cost
            );
        }
    }

    return $matrix[$len1][$len2];
}

function normalizedLevenshtein(string $s1, string $s2): float
{
    $dist = levenshteinDistance($s1, $s2);
    $maxLen = max(mb_strlen($s1, 'UTF-8'), mb_strlen($s2, 'UTF-8'));
    return $maxLen > 0 ? 1 - ($dist / $maxLen) : 1.0;
}

// ------------------ QUATERNION + CATÉGORIES (fidèle à ton code) ------------------
class Quaternion {
    public float $w, $x, $y, $z;
    public function __construct(float $w, float $x = 0, float $y = 0, float $z = 0) {
        $this->w = $w; $this->x = $x; $this->y = $y; $this->z = $z;
    }
}

function encodeToVaryingQBitFusion(int $codepoint): Quaternion {
    static $cache = [];
    if (isset($cache[$codepoint])) return $cache[$codepoint];

    $factor = ($codepoint <= 255) ? 255 : 0x110000;
    $t = $codepoint / $factor;

    $half  = M_PI * $t;
    $phi   = 2 * M_PI * $t;
    $theta = M_PI * $t;

    $ux = sin($theta) * cos($phi);
    $uy = sin($theta) * sin($phi);
    $uz = cos($theta);
    $s  = sin($half);

    return $cache[$codepoint] = new Quaternion(
        cos($half),
        $s * $ux,
        $s * $uy,
        $s * $uz
    );
}

function getSemanticCategory(int $cp): string {
    if ($cp === 0) return 'Null';
    if (in_array($cp, [32, 9, 10, 13, 0x00A0])) return 'Whitespace';
    if ($cp >= 48 && $cp <= 57) return 'Digit';
    if ($cp >= 65 && $cp <= 90) return 'LatinUpper';
    if ($cp >= 97 && $cp <= 122) return 'LatinLower';
    if (($cp >= 33 && $cp <= 47) || ($cp >= 58 && $cp <= 64) ||
        ($cp >= 91 && $cp <= 96) || ($cp >= 123 && $cp <= 126)) {
        return 'Punctuation';
    }
    return 'Other';
}

function quaternionDistance(Quaternion $a, Quaternion $b): float {
    $dot = abs($a->w*$b->w + $a->x*$b->x + $a->y*$b->y + $a->z*$b->z);
    return acos(min(1.0, $dot));
}

function baseStructuralSimilarity(int $cp1, int $cp2): float {
    if ($cp1 === $cp2) return 1.0;
    if ($cp1 === 0 || $cp2 === 0) return 0.0;

    $q1 = encodeToVaryingQBitFusion($cp1);
    $q2 = encodeToVaryingQBitFusion($cp2);

    $distance = quaternionDistance($q1, $q2);
    $diffRatio = abs($cp1 - $cp2) / 0x110000;

    return max(0.0, 1.0 - ($distance / M_PI) - ($diffRatio * 5.0));
}

function getCharacterSimilarity(int $cp1, int $cp2): float {
    $base = baseStructuralSimilarity($cp1, $cp2);
    $cat1 = getSemanticCategory($cp1);
    $cat2 = getSemanticCategory($cp2);

    return ($cat1 === $cat2)
        ? 0.40 + 0.60 * $base
        : 0.10 + 0.15 * $base;
}

// ------------------ SIMILARITÉ QUATERNION AMÉLIORÉE ------------------
function quaternionSimilarity(string $s1, string $s2): float {
    $chars1 = mb_str_split($s1, 1, 'UTF-8');
    $chars2 = mb_str_split($s2, 1, 'UTF-8');
    $minLen = min(count($chars1), count($chars2));

    if ($minLen === 0) return 0.0;

    $total = 0.0;
    for ($i = 0; $i < $minLen; $i++) {
        $cp1 = mb_ord($chars1[$i], 'UTF-8');
        $cp2 = mb_ord($chars2[$i], 'UTF-8');
        $total += getCharacterSimilarity($cp1, $cp2);
    }

    return $total / $minLen;
}

// ------------------ FONCTION HYBRIDE FINALE ------------------
function hybridSimilarity(
    string $s1,
    string $s2,
    float $weightLevenshtein = 0.60,
    float $weightQuaternion = 0.40
): float {
    $levScore = normalizedLevenshtein($s1, $s2);
    $qScore   = quaternionSimilarity($s1, $s2);

    $final = ($weightLevenshtein * $levScore) + ($weightQuaternion * $qScore);
    return round(max(0, min(1, $final)), 4);
}

// ======================================================
// TESTS
// ======================================================

$tests = [
    ["bonjour", "bonjoure"],
    ["aboucher", "abouchai"],
    ["chat", "chien"],
    ["hello world", "hello word"],
    ["Je suis allé au marché", "Je suis allé au marché hier"]
    ["bonjour 🥰🥰🥰", "bonjoureux"],
];

echo "<pre>";
foreach ($tests as [$a, $b]) {
    $score = hybridSimilarity($a, $b);
    echo "'$a' vs '$b' → " . ($score * 100) . "%\n";
}
echo "</pre>";
