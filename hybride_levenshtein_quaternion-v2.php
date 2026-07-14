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
    <title>Symphonie Alpine - Moteur Réel 9D Récursif</title>
    <style>
        body { background: #0f172a; color: #f8fafc; font-family: sans-serif; display: flex; flex-direction: column; align-items: center; padding: 20px; }
        .canvas-container { background: #1e293b; border-radius: 8px; margin-bottom: 20px; padding: 10px; text-align: center; }
        canvas { display: block; margin: 0 auto; }
        .panel { background: #1e293b; padding: 20px; border-radius: 8px; width: 800px; margin-top: 10px; }
        .result-item { border-bottom: 1px solid #475569; padding: 20px 0; }
    </style>
</head>
<body>

    <div id="container" class="panel">
        <h3>Moteur de Résonance (Logic Hybride 9D Récursif)</h3>
        <div id="resultsList"></div>
    </div>

<script>
    function levenshteinDistance(s1, s2) {
        let len1 = s1.length, len2 = s2.length;
        let matrix = Array.from({length: len1 + 1}, () => Array(len2 + 1).fill(0));
        for (let i = 0; i <= len1; i++) matrix[i][0] = i;
        for (let j = 0; j <= len2; j++) matrix[0][j] = j;
        for (let i = 1; i <= len1; i++) {
            for (let j = 1; j <= len2; j++) {
                let cost = (s1[i-1] === s2[j-1]) ? 0 : 1;
                matrix[i][j] = Math.min(matrix[i-1][j] + 1, matrix[i][j-1] + 1, matrix[i-1][j-1] + cost);
            }
        }
        return matrix[len1][len2];
    }

    function calculateHybridScore(s1, s2) {
        const levDist = levenshteinDistance(s1, s2);
        const levScore = 1 - (levDist / Math.max(s1.length, s2.length));
        const minLen = Math.min(s1.length, s2.length);
        let qTotal = 0;
        for(let i=0; i<minLen; i++) qTotal += (s1[i] === s2[i]) ? 1.0 : 0.2;
        const qScore = qTotal / minLen;
        return (0.65 * levScore) + (0.35 * qScore);
    }

    function drawWave(score, canvasId) {
        const canvas = document.getElementById(canvasId);
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.setLineDash([5, 5]); ctx.strokeStyle = '#f97316'; ctx.beginPath(); ctx.moveTo(0, 100); ctx.lineTo(800, 100); ctx.stroke();
        ctx.setLineDash([]);
        ctx.strokeStyle = '#22c55e'; ctx.beginPath(); ctx.moveTo(0, 50); ctx.lineTo(800, 50); ctx.stroke();
        ctx.strokeStyle = '#a855f7'; ctx.beginPath(); ctx.moveTo(0, 150); ctx.lineTo(800, 150); ctx.stroke();
        ctx.beginPath(); ctx.lineWidth = 2;
        const amplitude = Math.min(score * 80, 45);
        for (let x = 0; x < 800; x++) {
            const y = 100 - (Math.sin(x * 0.05) * amplitude);
            ctx.strokeStyle = (y < 100) ? '#3b82f6' : '#ef4444';
            if (x === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            ctx.stroke(); ctx.beginPath(); ctx.moveTo(x, y);
        }
    }

    const tests = [
        ["bonjour", "bonjoure"],
        ["aboucher", "abouchai"],
        ["chat", "chien"],
        ["Galibier", "Marseille"],
        ["Je suis allé au marché", "Je suis allé au marché hier"]
    ];

    const list = document.getElementById('resultsList');
    tests.forEach((t, index) => {
        const score = calculateHybridScore(t[0], t[1]);
        const container = document.createElement('div');
        container.className = 'result-item';
        container.innerHTML = `
            <p><strong>${t[0]}</strong> vs <strong>${t[1]}</strong> : ${(score * 100).toFixed(2)}%</p>
            <canvas id="waveCanvas_${index}" width="800" height="200"></canvas>
        `;
        list.appendChild(container);
        drawWave(score, `waveCanvas_${index}`);
    });
</script>
</body>
</html>
