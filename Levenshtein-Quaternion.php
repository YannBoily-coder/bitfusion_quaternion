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
// HYBRIDE : Levenshtein + Quaternion BitFusion
// ======================================================

// ------------------ LEVENSHTEIN (UTF-8 safe) ------------------
function levenshteinDistance(string $s1, string $s2): int
{
    $len1 = mb_strlen($s1, 'UTF-8');
    $len2 = mb_strlen($s2, 'UTF-8');

    if ($len1 === 0) return $len2;
    if ($len2 === 0) return $len1;

    $matrix = [];
    for ($i = 0; $i <= $len1; $i++) {
        $matrix[$i] = array_fill(0, $len2 + 1, 0);
    }

    for ($i = 0; $i <= $len1; $i++) $matrix[$i][0] = $i;
    for ($j = 0; $j <= $len2; $j++) $matrix[0][$j] = $j;

    for ($i = 1; $i <= $len1; $i++) {
        $char1 = mb_substr($s1, $i - 1, 1, 'UTF-8');
        for ($j = 1; $j <= $len2; $j++) {
            $char2 = mb_substr($s2, $j - 1, 1, 'UTF-8');
            $cost = ($char1 === $char2) ? 0 : 1;

            $matrix[$i][$j] = min(
                $matrix[$i - 1][$j] + 1,      // suppression
                $matrix[$i][$j - 1] + 1,      // insertion
                $matrix[$i - 1][$j - 1] + $cost // substitution
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

// ------------------ QUATERNION (basé sur ton code) ------------------
class Quaternion
{
    public float $w, $x, $y, $z;

    public function __construct(float $w, float $x = 0, float $y = 0, float $z = 0)
    {
        $this->w = $w;
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
    }
}

function encodeToVaryingQBitFusion(int $codepoint): Quaternion
{
    static $cache = [];

    if (isset($cache[$codepoint])) {
        return $cache[$codepoint];
    }

    $factor = ($codepoint <= 255) ? 255 : 0x110000;
    $t = $codepoint / $factor;

    $half  = M_PI * $t;
    $phi   = 2 * M_PI * $t;
    $theta = M_PI * $t;

    $ux = sin($theta) * cos($phi);
    $uy = sin($theta) * sin($phi);
    $uz = cos($theta);
    $s  = sin($half);

    $q = new Quaternion(
        cos($half),
        $s * $ux,
        $s * $uy,
        $s * $uz
    );

    $cache[$codepoint] = $q;
    return $q;
}

function quaternionSimilarity(string $s1, string $s2): float
{
    $chars1 = mb_str_split($s1, 1, 'UTF-8');
    $chars2 = mb_str_split($s2, 1, 'UTF-8');
    $minLen = min(count($chars1), count($chars2));

    if ($minLen === 0) return 0.0;

    $totalScore = 0.0;

    for ($i = 0; $i < $minLen; $i++) {
        $cp1 = mb_ord($chars1[$i], 'UTF-8');
        $cp2 = mb_ord($chars2[$i], 'UTF-8');

        $q1 = encodeToVaryingQBitFusion($cp1);
        $q2 = encodeToVaryingQBitFusion($cp2);

        // Produit scalaire (similarité)
        $dot = abs(
            $q1->w * $q2->w +
            $q1->x * $q2->x +
            $q1->y * $q2->y +
            $q1->z * $q2->z
        );

        $totalScore += $dot;
    }

    return $totalScore / $minLen;
}

// ------------------ FONCTION HYBRIDE ------------------
function hybridSimilarity(
    string $s1,
    string $s2,
    float $weightLevenshtein = 0.65,
    float $weightQuaternion = 0.35
): float {
    $levScore = normalizedLevenshtein($s1, $s2);
    $qScore   = quaternionSimilarity($s1, $s2);

    $finalScore = ($weightLevenshtein * $levScore) + ($weightQuaternion * $qScore);

    return round($finalScore, 4);
}

// ======================================================
// EXEMPLES DE TEST
// ======================================================

$tests = [
    ["bonjour", "bonjoure"],
    ["aboucher", "abouchai"],
    ["chat", "chien"],
    ["hello world", "hello word"],
    ["Je suis allé au marché", "Je suis allé au marché hier"],
];

echo "<pre>";
foreach ($tests as [$a, $b]) {
    $score = hybridSimilarity($a, $b);
    echo "'$a' vs '$b' → " . ($score * 100) . "%\n";
}
echo "</pre><br />";

$score = hybridSimilarity("bonjour 🥰🥰🥰", "bonjoureux");
echo $score;
