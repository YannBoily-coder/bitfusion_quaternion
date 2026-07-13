# bitfusion_quaternion
compare two str and add a score to check is same or % with bitfusion_quaternion.php
Same with SemanticEngine.php and SemanticEngine_test.php but return array
Idea helped with Google Gemini and Grock, Vibe coded
Credit not @me
You can use it for all project, you can test it: https://www.chezyann.net/bitfusion_quaternion_DoubleTextarea.php
https://share.gemini.google/DlnepVfb6w9v
https://x.com/i/grok/share/c6c518124dd34cd2bb8963526fb5bcb3
# BitFusion Quaternion

Un moteur léger et expérimental de **similarité entre chaînes de caractères** basé sur une représentation géométrique avec des **quaternions**.

Au lieu d'utiliser des embeddings ou des modèles lourds, chaque caractère est projeté dans un espace quaternionique. La similarité est ensuite calculée via la distance angulaire entre ces représentations.

---

## 🎮 Démo en ligne

Tu peux tester directement ici :

→ **[BitFusion Quaternion - Double Textarea](https://www.chezyann.net/bitfusion_quaternion_DoubleTextarea.php)**

La démo permet de comparer deux textes et d'afficher les meilleurs alignements caractère par caractère avec leur score.

---

## 🔍 Comment ça marche ?

L’idée est d’encoder chaque caractère (point de code Unicode) sous forme de **quaternion** (un vecteur 4D sur une sphère unitaire) :

- On utilise une projection trigonométrique (`sin` / `cos`) pour transformer le codepoint en quaternion.
- La similarité entre deux caractères repose sur :
  - La **distance angulaire** entre leurs quaternions (produit scalaire)
  - Leur **catégorie sémantique** (lettre majuscule, minuscule, chiffre, ponctuation, espace…)
- L’algorithme gère aussi les petits décalages entre les chaînes grâce à une fenêtre de lookahead.

Le résultat est un score global entre **0 et 1** (ou en pourcentage).

C’est une approche **structurelle et géométrique**, pas une similarité sémantique profonde comme avec des transformers.

---

## 📁 Fichiers principaux

| Fichier                              | Description                                      |
|--------------------------------------|--------------------------------------------------|
| `bitfusion_quaternion.php`           | Noyau principal (comparaison de 2 chaînes)       |
| `bitfusion_quaternion_DoubleTextarea.php` | Démo interactive avec deux zones de texte     |
| `SemanticEngine.php`                 | Version qui retourne un tableau de résultats     |

Une version **CUDA C++** (God Mode) existe également pour traiter de gros volumes rapidement.

---

## 🚀 Utilisation simple

```php
require 'bitfusion_quaternion.php';

$result = compareStringsWithQuaternion("Bonjour le monde", "Bonjoure le mond");
echo $result['score']; // Score de similarité
    $uz = cos($theta);
    $s = sin($half);

    return $cache[$codepoint] = new Quaternion(cos($half), $s*$ux, $s*$uy, $s*$uz);
}
