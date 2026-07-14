<?php
// Codé avec https://share.gemini.google/8yGMKq7L53Br
// Démo: https://www.chezyann.net/hybride_levenshtein_quaternion-v2.php
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
 ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Symphonie Alpine • Visualisation 9D Avancée</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        body { background: #0a0f1e; color: #e0e7ff; font-family: system-ui, sans-serif; padding: 30px 20px; display: flex; flex-direction: column; align-items: center; }
        .panel { background: #1e293b; padding: 35px; border-radius: 20px; width: 1100px; box-shadow: 0 15px 40px rgba(0,0,0,0.5); }
        .result-item { background: #111827; margin: 25px 0; padding: 25px; border-radius: 16px; border: 1px solid #334155; display: flex; align-items: center; gap: 20px; }
        .data-content { flex-grow: 1; }
        .pair { font-size: 1.25rem; margin-bottom: 12px; }
        .overall-score { font-size: 2rem; font-weight: 800; margin: 10px 0 20px; }
        .viz-container { display: flex; gap: 20px; align-items: center; }
        canvas { background: #0f172a; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.4); }
        .radar { width: 220px; height: 220px; }
        .wave { width: 400px; height: 180px; }
        .sphere-box { width: 180px; height: 180px; border-radius: 12px; overflow: hidden; background: #0f172a; border: 1px solid #334155; }
        .breakdown { margin-top: 15px; font-size: 0.95rem; color: #94a3b8; }
        .breakdown span { color: #e0e7ff; font-weight: 600; }
    </style>
</head>
<body>

    <h1 style="margin-bottom: 8px;">Symphonie Alpine</h1>
    <p style="color:#64748b; margin-bottom: 35px;">Moteur de Résonance • Visualisation 9D Avancée</p>

    <div class="panel">
        <h2 style="margin-top:0;">Tests de Résonance Multi-Dimensionnelle</h2>
        <div id="resultsList"></div>
    </div>

<script>
function levenshtein(s1, s2) {
    const m = s1.length, n = s2.length;
    if (!m) return n; if (!n) return m;
    let prev = Array.from({length: n + 1}, (_, i) => i);
    let curr = Array(n + 1);
    for (let i = 1; i <= m; i++) {
        curr[0] = i;
        for (let j = 1; j <= n; j++) {
            const cost = s1[i-1] === s2[j-1] ? 0 : 1;
            curr[j] = Math.min(curr[j-1] + 1, prev[j] + 1, prev[j-1] + cost);
        }
        [prev, curr] = [curr, prev];
    }
    return prev[n];
}

function getMultiDimensionalScores(str1, str2) {
    const s1 = str1.toLowerCase(), s2 = str2.toLowerCase();
    const maxLen = Math.max(s1.length, s2.length);
    const minLen = Math.min(s1.length, s2.length);
    const levDist = levenshtein(s1, s2);
    const levScore = 1 - (levDist / maxLen);
    let prefixScore = 0;
    for (let i = 0; i < minLen; i++) prefixScore += (s1[i] === s2[i]) ? 1 : 0.18;
    prefixScore /= minLen;
    const lenScore = 1 - Math.abs(s1.length - s2.length) / maxLen;
    const vowels = 'aeiouyàâäéèêëîïôöùûü';
    let vowelMatches = 0, vowelCount = 0;
    for (let i = 0; i < minLen; i++) {
        if (vowels.includes(s1[i])) { vowelCount++; if (s1[i] === s2[i]) vowelMatches++; }
    }
    const vowelScore = vowelCount > 0 ? vowelMatches / vowelCount : 0.5;
    const consScore = (prefixScore * 0.7) + (lenScore * 0.3);
    const firstLetterScore = (s1[0] === s2[0]) ? 1 : 0.3;
    let lcs = 0;
    for (let i = 0; i < minLen; i++) {
        for (let j = i; j < minLen; j++) {
            const sub = s1.substring(i, j + 1);
            if (s2.includes(sub) && sub.length > lcs) lcs = sub.length;
        }
    }
    return { levenshtein: Math.max(0, levScore), prefix: Math.max(0, prefixScore), length: Math.max(0, lenScore), vowel: Math.max(0, vowelScore), consonant: Math.max(0, consScore), firstLetter: firstLetterScore, lcs: lcs / maxLen };
}

function calculateOverallScore(dims) {
    const weights = { levenshtein: 0.25, prefix: 0.20, length: 0.12, vowel: 0.12, consonant: 0.12, firstLetter: 0.09, lcs: 0.10 };
    let total = 0;
    for (let key in dims) total += dims[key] * (weights[key] || 0.1);
    return Math.min(1, total);
}

function initSphere(containerId, score) {
    const container = document.getElementById(containerId);
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, 1, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(180, 180);
    container.appendChild(renderer.domElement);
    const geometry = new THREE.SphereGeometry(0.8, 32, 32);
    const material = new THREE.MeshBasicMaterial({ color: score > 0.7 ? 0x22c55e : 0x60a5fa, wireframe: true });
    const sphere = new THREE.Mesh(geometry, material);
    scene.add(sphere);
    camera.position.z = 2;
    function animate() { requestAnimationFrame(animate); sphere.rotation.y += 0.02; sphere.rotation.x += 0.01; renderer.render(scene, camera); }
    animate();
}

function drawRadarChart(dimensions, canvasId) {
    const canvas = document.getElementById(canvasId);
    const ctx = canvas.getContext('2d');
    const cx = 110, cy = 110, r = 85;
    const labels = Object.keys(dimensions), values = Object.values(dimensions);

    ctx.clearRect(0,0,220,220);
    ctx.strokeStyle = '#334155';
    ctx.fillStyle = '#94a3b8';
    ctx.font = '10px sans-serif';
    ctx.textAlign = 'center';

    for(let i=0; i<labels.length; i++) {
        const angle = -Math.PI/2 + (Math.PI*2/labels.length)*i;
        ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(cx + Math.cos(angle)*r, cy + Math.sin(angle)*r); ctx.stroke();
        ctx.fillText(labels[i], cx + Math.cos(angle)*(r+15), cy + Math.sin(angle)*(r+15));
    }

    ctx.fillStyle = 'rgba(34, 197, 94, 0.4)';
    ctx.strokeStyle = '#22c55e';
    ctx.beginPath();
    for(let i=0; i<labels.length; i++) {
        const angle = -Math.PI/2 + (Math.PI*2/labels.length)*i;
        const x = cx + Math.cos(angle) * (r * values[i]);
        const y = cy + Math.sin(angle) * (r * values[i]);
        i===0 ? ctx.moveTo(x,y) : ctx.lineTo(x,y);
    }
    ctx.closePath();
    ctx.fill();
    ctx.stroke();
}

function drawAdvancedWave(score, canvasId) {
    const canvas = document.getElementById(canvasId), ctx = canvas.getContext('2d');
    let f = 0;
    function anim() {
        ctx.clearRect(0, 0, 400, 180);

        // 1. Ligne de base pointillée orange
        ctx.beginPath();
        ctx.setLineDash([5, 5]);
        ctx.strokeStyle = '#f59e0b';
        ctx.moveTo(0, 90); ctx.lineTo(400, 90); ctx.stroke();
        ctx.setLineDash([]);

        // 2. Lignes limites haute (bleu) et basse (rouge)
        ctx.lineWidth = 1;
        ctx.strokeStyle = '#3b82f6';
        ctx.beginPath(); ctx.moveTo(0, 30); ctx.lineTo(400, 30); ctx.stroke();
        ctx.strokeStyle = '#ef4444';
        ctx.beginPath(); ctx.moveTo(0, 150); ctx.lineTo(400, 150); ctx.stroke();

        // 3. Rendu segmenté dynamique de l'onde
        ctx.lineWidth = 3;
        for(let x=0; x<399; x++) {
            const y1 = 90 + Math.sin(x*0.04 + f*0.1)*50*score;
            const y2 = 90 + Math.sin((x+1)*0.04 + f*0.1)*50*score;
            ctx.beginPath();
            ctx.moveTo(x, y1);
            ctx.lineTo(x+1, y2);
            ctx.strokeStyle = y1 < 90 ? '#3b82f6' : '#ef4444';
            ctx.stroke();
        }
        f++; requestAnimationFrame(anim);
    }
    anim();
}

const tests = [
                ["bonjour", "bonjoure"],
                ["aboucher", "abouchai"],
                ["chat", "chien"],
                ["Marseillais", "Parisien"],
                ["Marseillais", "Marseille"],
                ["Marseille", "Paris"],
                ["hello world!", "hello word !"],
                ["echo('hello world')", "print: hello word"],
                ["HPI", "BPI"],
                ["Je suis allé au marché", "Je suis allé au marché hier"],
              ];
const container = document.getElementById('resultsList');

tests.forEach((pair, index) => {
    const dims = getMultiDimensionalScores(pair[0], pair[1]);
    const overall = calculateOverallScore(dims);
    const item = document.createElement('div');
    item.className = 'result-item';
    item.innerHTML = `
        <div class="data-content">
            <div class="pair"><strong>${pair[0]}</strong> ↔ <strong>${pair[1]}</strong></div>
            <div class="overall-score" style="color: ${overall > 0.7 ? '#22c55e' : '#60a5fa'}">${(overall*100).toFixed(1)}%</div>
            <div class="breakdown">Levenshtein: <span>${(dims.levenshtein*100).toFixed(0)}%</span> • Préfixe: <span>${(dims.prefix*100).toFixed(0)}%</span></div>
        </div>
        <div class="viz-container">
            <canvas id="radar_${index}" class="radar" width="220" height="220"></canvas>
            <canvas id="wave_${index}" class="wave" width="400" height="180"></canvas>
            <div id="sphere_${index}" class="sphere-box"></div>
        </div>
    `;
    container.appendChild(item);
    setTimeout(() => {
        drawRadarChart(dims, `radar_${index}`);
        drawAdvancedWave(overall, `wave_${index}`);
        initSphere(`sphere_${index}`, overall);
    }, 100);
});
</script>
</body>
</html>
