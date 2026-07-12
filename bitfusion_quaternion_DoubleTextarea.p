<?php
// ==================== BITFUSION AI - DREAM EDITION V2.2 (Many-to-Many) ====================

class Quaternion {
    public $w, $x, $y, $z;
    public function __construct($w, $x = 0.0, $y = 0.0, $z = 0.0) {
        $this->w = $w; $this->x = $x; $this->y = $y; $this->z = $z;
    }
}

function encodeToVaryingQBitFusion($codepoint) {
    static $cache = [];
    if (isset($cache[$codepoint])) return $cache[$codepoint];

    $factor = ($codepoint <= 255) ? 255 : 0x110000;

    $t = $codepoint / $factor;
    $half = M_PI * $t;
    $phi = 2 * M_PI * $t;
    $theta = M_PI * $t;

    $ux = sin($theta) * cos($phi);
    $uy = sin($theta) * sin($phi);
    $uz = cos($theta);
    $s = sin($half);

    return $cache[$codepoint] = new Quaternion(cos($half), $s*$ux, $s*$uy, $s*$uz);
}

function getSemanticCategory(int $cp): string {
    if ($cp === 0) return 'Null';
    if (in_array($cp, [32, 9, 10, 13, 0x00A0, 0x2000, 0x2001, 0x2002, 0x2003, 0x2009])) return 'Whitespace';
    if ($cp >= 48 && $cp <= 57) return 'Digit';
    if ($cp >= 65 && $cp <= 90) return 'LatinUpper';
    if ($cp >= 97 && $cp <= 122) return 'LatinLower';
    if (($cp >= 33 && $cp <= 47) || ($cp >= 58 && $cp <= 64) || ($cp >= 91 && $cp <= 96) || ($cp >= 123 && $cp <= 126)) return 'Punctuation';

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
    $structuralIndex = 1.0 - ($distance / M_PI) - ($diffRatio * 5.0);

    return max(0.0, $structuralIndex);
}

function getGlobalMlBoost(string $word1, string $word2): float {
    $knownSimilar = [
        'bonjour'       => ['salut', 'hello', 'bonsoir'],
        'chat'          => ['chien', 'animal', 'félin'],
        'programmation' => ['code', 'développement', 'coding'],
        'hello'         => ['bonjour', 'salut'],
    ];

    $w1 = strtolower(trim($word1));
    $w2 = strtolower(trim($word2));

    if ((isset($knownSimilar[$w1]) && in_array($w2, $knownSimilar[$w1])) ||
        (isset($knownSimilar[$w2]) && in_array($w1, $knownSimilar[$w2]))) {
        return 0.20;
    }
    return 0.0;
}

function getCharacterSimilarity(int $cp1, int $cp2): float {
    $base = baseStructuralSimilarity($cp1, $cp2);
    $cat1 = getSemanticCategory($cp1);
    $cat2 = getSemanticCategory($cp2);

    return ($cat1 === $cat2) ? 0.40 + 0.60 * $base : 0.10 + 0.15 * $base;
}

function compareStringsWithQuaternion(string $str1, string $str2): array {
    $chars1 = mb_str_split($str1, 1, 'UTF-8');
    $chars2 = mb_str_split($str2, 1, 'UTF-8');

    $len1 = count($chars1);
    $len2 = count($chars2);

    $details = [];
    $totalScore = 0.0;

    $i = 0; $j = 0; $pos = 0;
    $maxLookahead = 2;
    $penaltyPerGap = 0.15;

    while ($i < $len1 || $j < $len2) {
        $c1 = $chars1[$i] ?? '';
        $c2 = $chars2[$j] ?? '';

        $cp1 = $c1 ? mb_ord($c1, 'UTF-8') : 0;
        $cp2 = $c2 ? mb_ord($c2, 'UTF-8') : 0;

        if ($i >= $len1 || $j >= $len2) {
            $sim = max(0.0, getCharacterSimilarity($cp1, $cp2) - $penaltyPerGap);
            $totalScore += $sim;

            $details[] = [
                'pos' => $pos++, 'char1' => $c1 ?: '∅', 'char2' => $c2 ?: '∅',
                'cat1' => getSemanticCategory($cp1), 'cat2' => getSemanticCategory($cp2),
                'similarity' => $sim
            ];
            $i++; $j++; continue;
        }

        $immediateSim = getCharacterSimilarity($cp1, $cp2);
        if ($immediateSim > 0.85) {
            $totalScore += $immediateSim;
            $details[] = [
                'pos' => $pos++, 'char1' => $c1, 'char2' => $c2,
                'cat1' => getSemanticCategory($cp1), 'cat2' => getSemanticCategory($cp2),
                'similarity' => $immediateSim
            ];
            $i++; $j++; continue;
        }

        $bestSim = $immediateSim;
        $bestMatch = ['type' => 'advance_both', 'offset' => 0];

        for ($offset = 1; $offset <= $maxLookahead; $offset++) {
            if ($i + $offset < $len1) {
                $nextCp1 = mb_ord($chars1[$i + $offset], 'UTF-8');
                $simA = getCharacterSimilarity($nextCp1, $cp2) - ($offset * $penaltyPerGap);
                if ($simA > $bestSim) { $bestSim = $simA; $bestMatch = ['type' => 'skip_str1', 'offset' => $offset]; }
            }
            if ($j + $offset < $len2) {
                $nextCp2 = mb_ord($chars2[$j + $offset], 'UTF-8');
                $simB = getCharacterSimilarity($cp1, $nextCp2) - ($offset * $penaltyPerGap);
                if ($simB > $bestSim) { $bestSim = $simB; $bestMatch = ['type' => 'skip_str2', 'offset' => $offset]; }
            }
        }

        $bestSim = max(0.0, $bestSim);
        if ($bestMatch['type'] === 'skip_str1') {
            for ($o = 0; $o < $bestMatch['offset']; $o++) {
                $details[] = [
                    'pos' => $pos++, 'char1' => $chars1[$i + $o], 'char2' => '∅',
                    'cat1' => getSemanticCategory(mb_ord($chars1[$i + $o], 'UTF-8')), 'cat2' => 'Null',
                    'similarity' => 0.0
                ];
            }
            $i += $bestMatch['offset'];
            continue;
        } elseif ($bestMatch['type'] === 'skip_str2') {
            for ($o = 0; $o < $bestMatch['offset']; $o++) {
                $details[] = [
                    'pos' => $pos++, 'char1' => '∅', 'char2' => $chars2[$j + $o],
                    'cat1' => 'Null', 'cat2' => getSemanticCategory(mb_ord($chars2[$j + $o], 'UTF-8')),
                    'similarity' => 0.0
                ];
            }
            $j += $bestMatch['offset'];
            continue;
        } else {
            $totalScore += $bestSim;
            $details[] = [
                'pos' => $pos++, 'char1' => $c1, 'char2' => $c2,
                'cat1' => getSemanticCategory($cp1), 'cat2' => getSemanticCategory($cp2),
                'similarity' => $bestSim
            ];
            $i++; $j++;
        }
    }

    $maxSteps = count($details);
    $baseSimilarity = $maxSteps > 0 ? $totalScore / $maxSteps : 0.0;

    $mlBoost = getGlobalMlBoost($str1, $str2);
    $finalSimilarity = ($mlBoost > 0) ? $baseSimilarity + ((1.0 - $baseSimilarity) * 0.50) : $baseSimilarity;

    return [
        'str1' => $str1, 'str2' => $str2,
        'similarity' => $finalSimilarity,
        'details' => $details
    ];
}

// === Traitement croisé (ManyToMany) ===
function compareManyToMany(array $mains, array $others): array {
    $results = [];
    foreach ($mains as $main) {
        foreach ($others as $other) {
            $results[] = compareStringsWithQuaternion($main, $other);
        }
    }
    usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
    return $results;
}

// ==================== INTERFACE HTML ====================

$mainsInput = $_POST['mains'] ?? "Bonjour\nQi";
$othersInput = $_POST['others'] ?? "Bonjour\nBonsoir\nSalut\nHello\nChien\n106.5 FM";

$mains = array_filter(array_map('trim', explode("\n", $mainsInput)));
$others = array_filter(array_map('trim', explode("\n", $othersInput)));

$results = (!empty($mains) && !empty($others)) ? compareManyToMany($mains, $others) : [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BITFUSION • AI v2.2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background: #0a0a12; }
        .glass { background: rgba(255,255,255,0.06); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); }
        .neon-cyan { text-shadow: 0 0 15px #67e8f9; }
    </style>
</head>
<body class="text-slate-200">
<div class="max-w-6xl mx-auto px-6 py-12">
    <div class="text-center mb-12">
        <h1 class="text-7xl font-bold tracking-tighter neon-cyan">BITFUSION<span class="text-cyan-400">.</span>AI</h1>
        <p class="text-xl text-cyan-300/70 mt-2">Quantum Semantic Engine v2.2 (Matrix Multi-Scan)</p>
    </div>

    <div class="glass rounded-3xl p-8 mb-10">
        <form method="POST" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-cyan-300 mb-2">Liste Source A (une par ligne)</label>
                    <textarea name="mains" rows="6"
                              class="w-full bg-slate-900 border border-slate-700 rounded-2xl px-6 py-4 font-mono text-sm focus:outline-none focus:border-cyan-400"><?= htmlspecialchars($mainsInput) ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-cyan-300 mb-2">Liste Cible B (une par ligne)</label>
                    <textarea name="others" rows="6"
                              class="w-full bg-slate-900 border border-slate-700 rounded-2xl px-6 py-4 font-mono text-sm focus:outline-none focus:border-cyan-400"><?= htmlspecialchars($othersInput) ?></textarea>
                </div>
            </div>
            <button type="submit"
                    class="w-full py-4 bg-gradient-to-r from-cyan-500 to-violet-600 hover:from-cyan-400 hover:to-violet-500 rounded-2xl font-bold text-xl flex items-center justify-center gap-3 transition">
                <i class="fa-solid fa-layer-group"></i>
                LANCER LE MATRICAGE MATRIX
            </button>
        </form>
    </div>

    <?php if (!empty($results)): ?>
    <div class="space-y-8">
        <h2 class="text-3xl font-semibold flex items-center gap-3">
            <i class="fa-solid fa-chart-line text-cyan-400"></i>
            Matrice des Alignements
        </h2>

        <?php foreach ($results as $res):
            $sim = $res['similarity'];
            if ($sim >= 0.85)      { $color = 'emerald'; $label = 'Très proche'; }
            elseif ($sim >= 0.65)  { $color = 'amber';   $label = 'Proche'; }
            elseif ($sim >= 0.40)  { $color = 'orange';  $label = 'Moyen'; }
            else                   { $color = 'rose';    $label = 'Éloigné'; }
        ?>
            <div class="glass rounded-3xl p-8">
                <div class="flex justify-between items-center mb-6">
                    <span class="text-2xl font-semibold">
                        <span class="text-cyan-400">«<?= htmlspecialchars($res['str1']) ?>»</span> 
                        <i class="fa-solid fa-right-left text-sm mx-3 text-slate-500"></i> 
                        <span class="text-violet-400">«<?= htmlspecialchars($res['str2']) ?>»</span>
                    </span>
                    <div class="text-right">
                        <div class="text-4xl font-bold text-<?= $color ?>-400"><?= number_format($sim, 4) ?></div>
                        <div class="text-sm text-<?= $color ?>-300"><?= $label ?></div>
                    </div>
                </div>

                <div class="h-3 bg-slate-800 rounded-full mb-8 overflow-hidden">
                    <div class="h-3 bg-gradient-to-r from-<?= $color ?>-400 to-<?= $color ?>-600 rounded-full"
                         style="width: <?= round($sim * 100) ?>%"></div>
                </div>

                <details>
                    <summary class="cursor-pointer text-cyan-300 hover:text-cyan-200 mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-chevron-down transition"></i>
                        Voir l'analyse atomique des caractères
                    </summary>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-700 text-left text-slate-400">
                                <th class="py-2 px-3">Pos</th>
                                <th class="py-2 px-3">Char 1</th>
                                <th class="py-2 px-3">Catégorie</th>
                                <th class="py-2 px-3">Char 2</th>
                                <th class="py-2 px-3">Catégorie</th>
                                <th class="py-2 px-3 text-right">Similarité</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <?php foreach ($res['details'] as $d):
                                $s = $d['similarity'];
                                $rowColor = $s >= 0.7 ? 'emerald' : ($s >= 0.4 ? 'amber' : 'rose');
                            ?>
                            <tr class="hover:bg-white/5">
                                <td class="py-3 px-3 font-mono text-slate-400"><?= $d['pos'] ?></td>
                                <td class="py-3 px-3 text-xl text-cyan-300"><?= htmlspecialchars($d['char1']) ?></td>
                                <td class="py-3 px-3"><span class="px-3 py-0.5 rounded-full bg-white/10 text-xs"><?= $d['cat1'] ?></span></td>
                                <td class="py-3 px-3 text-xl text-violet-300"><?= htmlspecialchars($d['char2']) ?></td>
                                <td class="py-3 px-3"><span class="px-3 py-0.5 rounded-full bg-white/10 text-xs"><?= $d['cat2'] ?></span></td>
                                <td class="py-3 px-3 text-right font-mono text-<?= $rowColor ?>-400"><?= number_format($s, 5) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
