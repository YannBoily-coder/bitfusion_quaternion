#include <cuda.h>
#include <cuda_runtime.h>
#include <math.h>

// Alignement strict à 16 octets (4 floats d'un coup dans les registres)
struct alignas(16) Quaternion4f {
    float w, x, y, z;
};

// Utilisation des fonctions intrinsèques CUDA câblées en hardware pour la trigo
__device__ __forceinline__ Quaternion4f fastEncodeQuaternion(int codepoint) {
    float factor = (codepoint <= 255) ? 255.0f : 1114112.0f;
    float t = (float)codepoint / factor;
    
    float half = M_PI * t;
    float phi = 2.0f * M_PI * t;
    float theta = M_PI * t;

    // __sinf et __cosf sont des fonctions intrinsèques directes du silicium (vitesse maximale)
    float sin_theta = __sinf(theta);
    float ux = sin_theta * __cosf(phi);
    float uy = sin_theta * __sinf(phi);
    float uz = __cosf(theta);
    float s  = __sinf(half);

    return Quaternion4f{__cosf(half), s * ux, s * uy, s * uz};
}

__global__ void bitfusion_god_mode_kernel(
    const int* __restrict__ str1_codes,
    const int* __restrict__ str2_matrices,
    float* __restrict__ output_scores,
    int L1, int L2, int N_strings, float penalty_gap) 
{
    // ALLOCATION EN MÉMOIRE PARTAGÉE (SRAM - Latence quasi nulle)
    // On y stocke la chaîne principale pour que tous les threads du bloc y accèdent instantanément
    extern __shared__ int shared_main_str[];
    
    // Chargement coopératif de la chaîne principale dans la SRAM du bloc
    for (int i = threadIdx.x; i < L1; i += blockDim.x) {
        shared_main_str[i] = str1_codes[i];
    }
    __syncthreads(); // Barrière de synchronisation : on attend que la SRAM soit remplie

    int string_idx = blockIdx.x * blockDim.x + threadIdx.x;
    if (string_idx >= N_strings) return;

    float total_score = 0.0f;
    int max_steps = (L1 > L2) ? L1 : L2;

    // Déroulage de boucle partiel (Loop Unrolling) pour maximiser l'occupation des pipelines de calcul
    #pragma unroll 4
    for (int pos = 0; pos < max_steps; pos++) {
        int cp1 = (pos < L1) ? shared_main_str[pos] : 0; // Lecture de la SRAM ultra-rapide !
        int cp2 = (pos < L2) ? str2_matrices[string_idx * L2 + pos] : 0;

        if (cp1 == 0 && cp2 == 0) continue;

        if (pos >= L1 || pos >= L2) {
            float base = (cp1 != 0 && cp2 != 0) ? 0.40f : 0.10f; // Logique simplifiée pour le pipeline
            total_score += fmaxf(0.0f, base - penalty_gap);
            continue;
        }

        if (cp1 == cp2) {
            total_score += 1.0f;
            continue;
        }

        Quaternion4f q1 = fastEncodeQuaternion(cp1);
        Quaternion4f q2 = fastEncodeQuaternion(cp2);

        // FMA (Fused Multiply-Add) : calcul du produit scalaire en une seule instruction CPU/GPU
        float dot = fabsf(fmaf(q1.w, q2.w, fmaf(q1.x, q2.x, fmaf(q1.y, q2.y, q1.z * q2.z))));
        if (dot > 1.0f) dot = 1.0f;
        
        float distance = acosf(dot);
        float diffRatio = fabsf((float)cp1 - (float)cp2) / 1114112.0f;
        float structuralIndex = fmaxf(0.0f, 1.0f - (distance / (float)M_PI) - (diffRatio * 5.0f));

        // Remplacement des embranchements if/else par une sélection ternaire optimisée en assembleur PTX
        int cat1 = (cp1 <= 255) ? 1 : 2; 
        int cat2 = (cp2 <= 255) ? 1 : 2;
        
        float char_sim = (cat1 == cat2) ? (0.40f + 0.60f * structuralIndex) : (0.10f + 0.15f * structuralIndex);
        total_score += char_sim;
    }

    output_scores[string_idx] = total_score / (float)max_steps;
}
