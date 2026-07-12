<?php

class Quaternion {
    public $w, $x, $y, $z;
    public function __construct($w, $x = 0.0, $y = 0.0, $z = 0.0) {
        $this->w = $w; $this->x = $x; $this->y = $y; $this->z = $z;
    }
}

class SemanticEngine {
    private array $cache = [];
    private array $knownSimilar = [
        'bonjour'       => ['salut', 'hello', 'bonsoir'],
        'chat'          => ['chien', 'animal', 'félin'],
        'programmation' => ['code', 'développement', 'coding'],
    ];

    private function encodeToQuaternion(int $codepoint): Quaternion {
        if (isset($this->cache[$codepoint])) return $this->cache[$codepoint];

        $factor = ($codepoint <= 255) ? 255 : 0x110000;
        $t = $codepoint / $factor;
        $half = M_PI * $t;
        $phi = 2 * M_PI * $t;
        $theta = M_PI * $t;

        $ux = sin($theta) * cos($phi);
        $uy = sin($theta) * sin($phi);
        $uz = cos($theta);
        $s = sin($half);

        return $this->cache[$codepoint] = new Quaternion(cos($half), $s*$ux, $s*$uy, $s*$uz);
    }

    private function getSemanticCategory(int $cp): string {
        if ($cp === 0) return 'Null';
        if (in_array($cp, [32, 9, 10, 13, 0x00A0, 0x2000, 0x2001, 0x2002, 0x2003, 0x2009])) return 'Whitespace';
        if ($cp >= 48 && $cp <= 57) return 'Digit';
        if ($cp >= 65 && $cp <= 90) return 'LatinUpper';
        if ($cp >= 97 && $cp <= 122) return 'LatinLower';
        if (($cp >= 33 && $cp <= 47) || ($cp >= 58 && $cp <= 64) || ($cp >= 91 && $cp <= 96) || ($cp >= 123 && $cp <= 126)) return 'Punctuation';
        return 'Other';
    }

    private function quaternionDistance(Quaternion $a, Quaternion $b): float {
        $dot = abs($a->w*$b->w + $a->x*$b->x + $a->y*$b->y + $a->z*$b->z);
        return acos(min(1.0, $dot));
    }

    private function baseStructuralSimilarity(int $cp1, int $cp2): float {
        if ($cp1 === $cp2) return 1.0;
        if ($cp1 === 0 || $cp2 === 0) return 0.0;

        $q1 = $this->encodeToQuaternion($cp1);
        $q2 = $this->encodeToQuaternion($cp2);

        $distance = $this->quaternionDistance($q1, $q2);
        $diffRatio = abs($cp1 - $cp2) / 0x110000;

        return max(0.0, 1.0 - ($distance / M_PI) - ($diffRatio * 40.0));
    }

    private function getCharacterSimilarity(int $cp1, int $cp2): float {
        $base = $this->baseStructuralSimilarity($cp1, $cp2);
        $cat1 = $this->getSemanticCategory($cp1);
        $cat2 = $this->getSemanticCategory($cp2);

        return ($cat1 === $cat2) ? 0.40 + 0.60 * $base : 0.10 + 0.15 * $base;
    }

    private function getGlobalMlBoost(string $word1, string $word2): float {
        $w1 = strtolower(trim($word1));
        $w2 = strtolower(trim($word2));

        if ((isset($this->knownSimilar[$w1]) && in_array($w2, $this->knownSimilar[$w1])) ||
            (isset($this->knownSimilar[$w2]) && in_array($w1, $this->knownSimilar[$w2]))) {
            return 0.20;
        }
        return 0.0;
    }

    public function analyze(string $str1, string $str2): array {
        $chars1 = mb_str_split($str1, 1, 'UTF-8');
        $chars2 = mb_str_split($str2, 1, 'UTF-8');
        $len1 = count($chars1); $len2 = count($chars2);

        $details = []; $totalScore = 0.0;
        $i = 0; $j = 0; $pos = 0;
        $maxLookahead = 2; $penaltyPerGap = 0.15;

        while ($i < $len1 || $j < $len2) {
            $c1 = $chars1[$i] ?? ''; $c2 = $chars2[$j] ?? '';
            $cp1 = $c1 ? mb_ord($c1, 'UTF-8') : 0;
            $cp2 = $c2 ? mb_ord($c2, 'UTF-8') : 0;

            if ($i >= $len1 || $j >= $len2) {
                $sim = max(0.0, $this->getCharacterSimilarity($cp1, $cp2) - $penaltyPerGap);
                $totalScore += $sim;
                $details[] = ['pos' => $pos++, 'char1' => $c1 ?: '∅', 'char2' => $c2 ?: '∅', 'similarity' => $sim];
                $i++; $j++; continue;
            }

            $immediateSim = $this->getCharacterSimilarity($cp1, $cp2);
            if ($immediateSim > 0.85) {
                $totalScore += $immediateSim;
                $details[] = ['pos' => $pos++, 'char1' => $c1, 'char2' => $c2, 'similarity' => $immediateSim];
                $i++; $j++; continue;
            }

            $bestSim = $immediateSim;
            $bestMatch = ['type' => 'advance_both', 'offset' => 0];

            for ($offset = 1; $offset <= $maxLookahead; $offset++) {
                if ($i + $offset < $len1) {
                    $nextCp1 = mb_ord($chars1[$i + $offset], 'UTF-8');
                    $simA = $this->getCharacterSimilarity($nextCp1, $cp2) - ($offset * $penaltyPerGap);
                    if ($simA > $bestSim) { $bestSim = $simA; $bestMatch = ['type' => 'skip_str1', 'offset' => $offset]; }
                }
                if ($j + $offset < $len2) {
                    $nextCp2 = mb_ord($chars2[$j + $offset], 'UTF-8');
                    $simB = $this->getCharacterSimilarity($cp1, $nextCp2) - ($offset * $penaltyPerGap);
                    if ($simB > $bestSim) { $bestSim = $simB; $bestMatch = ['type' => 'skip_str2', 'offset' => $offset]; }
                }
            }

            $bestSim = max(0.0, $bestSim);
            if ($bestMatch['type'] === 'skip_str1') {
                for ($o = 0; $o < $bestMatch['offset']; $o++) {
                    $details[] = ['pos' => $pos++, 'char1' => $chars1[$i + $o], 'char2' => '∅', 'similarity' => 0.0];
                }
                $i += $bestMatch['offset']; continue;
            } elseif ($bestMatch['type'] === 'skip_str2') {
                for ($o = 0; $o < $bestMatch['offset']; $o++) {
                    $details[] = ['pos' => $pos++, 'char1' => '∅', 'char2' => $chars2[$j + $o], 'similarity' => 0.0];
                }
                $j += $bestMatch['offset']; continue;
            } else {
                $totalScore += $bestSim;
                $details[] = ['pos' => $pos++, 'char1' => $c1, 'char2' => $c2, 'similarity' => $bestSim];
                $i++; $j++;
            }
        }

        $maxSteps = count($details);
        $baseSimilarity = $maxSteps > 0 ? $totalScore / $maxSteps : 0.0;
        $mlBoost = $this->getGlobalMlBoost($str1, $str2);
        $finalSimilarity = ($mlBoost > 0) ? $baseSimilarity + ((1.0 - $baseSimilarity) * 0.50) : $baseSimilarity;

        return [
            'similarity' => $finalSimilarity,
            'details'    => $details
        ];
    }
}
