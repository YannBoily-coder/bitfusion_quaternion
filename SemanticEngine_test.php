<?php
require_once 'SemanticEngine.php';

$engine = new SemanticEngine();

$rechercheUser = "Bounjour"; // Avec une faute de frappe
$baseDeDonnees = ["Au revoir", "Bonsoir", "Hello", "Code source"];

$suggestions = [];
foreach ($baseDeDonnees as $item) {
    $analyse = $engine->analyze($rechercheUser, $item);

    // On ne garde que ce qui résonne fortement (ex: > 0.60)
    if ($analyse['similarity'] > 0.60) {
        $suggestions[] = [
            'texte' => $item,
            'score' => $analyse['similarity']
        ];
    }
}

// On trie à l'horizontale, du plus proche au plus éloigné
usort($suggestions, fn($a, $b) => $b['score'] <=> $a['score']);

echo '<pre>';
print_r($suggestions);
echo '</pre>';
