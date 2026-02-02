#!/usr/bin/env python3
import sys
import json
import torch
import torch.nn as nn
from torchvision import transforms, models
from PIL import Image
import os
import numpy as np
from collections import defaultdict

# ============================================================================
# ENHANCED CONFIGURATION
# ============================================================================

# Distance thresholds (Euclidean)
# Note: Euclidean distance scale for 512-dimensional embeddings:
#   - 0.0 - 0.5:  Virtually identical (same image, maybe resized/cropped)
#   - 0.5 - 2.0:  Same dog, same photo session (different angles)
#   - 2.0 - 5.0:  Same dog, different photos (lighting/background changes)
#   - 5.0 - 12.0: Very similar dogs (could be same breed & similar appearance)
#   - 12.0 - 20.0: Similar dogs (same breed, different features)
#   - 20.0+:      Different dogs or different breeds

EXACT_DUPLICATE_THRESHOLD = 5.0      # Effectively the same photo or same dog
VERY_SIMILAR_THRESHOLD = 12.0        # Very similar, likely same breed
SIMILAR_THRESHOLD = 20.0             # Somewhat similar
WEAK_SIMILARITY_THRESHOLD = 30.0     # Weak similarity

# Cosine similarity thresholds (0-1 scale, higher = more similar)
COSINE_EXACT_THRESHOLD = 0.98        # Near identical
COSINE_VERY_SIMILAR_THRESHOLD = 0.92 # Very similar
COSINE_SIMILAR_THRESHOLD = 0.85      # Similar
COSINE_WEAK_THRESHOLD = 0.75         # Weakly similar

# Model confidence thresholds
MODEL_VERY_HIGH_CONF = 0.90          # Model is extremely confident
MODEL_HIGH_CONF = 0.85               # Model is very confident
MODEL_MEDIUM_CONF = 0.70             # Model is moderately confident

# Minimum memory examples needed for statistical confidence
MIN_EXAMPLES_FOR_STATS = 3

# Breed-specific thresholds for visually similar breeds
BREED_SPECIFIC_THRESHOLDS = {
    # Similar golden/yellow breeds
    "Golden Retriever": {
        "euclidean": 10.0,
        "cosine": 0.94,
        "similar_breeds": ["Labrador Retriever", "Flat-Coated Retriever"]
    },
    "Labrador Retriever": {
        "euclidean": 10.0,
        "cosine": 0.94,
        "similar_breeds": ["Golden Retriever", "Chesapeake Bay Retriever"]
    },
    # Similar small breeds
    "Chihuahua": {
        "euclidean": 8.0,
        "cosine": 0.95,
        "similar_breeds": ["Toy Fox Terrier", "Miniature Pinscher"]
    },
    "Pomeranian": {
        "euclidean": 9.0,
        "cosine": 0.94,
        "similar_breeds": ["Spitz", "Keeshond"]
    },
    # Similar shepherd breeds
    "German Shepherd": {
        "euclidean": 10.0,
        "cosine": 0.93,
        "similar_breeds": ["Belgian Malinois", "Dutch Shepherd"]
    },
    # Similar bulldog types
    "French Bulldog": {
        "euclidean": 9.0,
        "cosine": 0.94,
        "similar_breeds": ["Boston Terrier", "English Bulldog"]
    },
    "English Bulldog": {
        "euclidean": 9.0,
        "cosine": 0.94,
        "similar_breeds": ["French Bulldog", "American Bulldog"]
    },
    # Similar terrier breeds
    "Yorkshire Terrier": {
        "euclidean": 8.0,
        "cosine": 0.95,
        "similar_breeds": ["Silky Terrier", "Australian Terrier"]
    },
}

# ============================================================================
# MODEL DEFINITION
# ============================================================================

def load_breed_mapping():
    """Load breed index to name mapping from JSON file."""
    script_dir = os.path.dirname(os.path.abspath(__file__))
    mapping_path = os.path.join(script_dir, 'breed_mapping.json')
    try:
        with open(mapping_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
            breeds = data['breeds']
            breed_mapping = {}
            for idx, breed_id in enumerate(breeds):
                breed_name = breed_id.split('-', 1)[1].replace('_', ' ')
                breed_mapping[str(idx)] = breed_name
            return breed_mapping
    except Exception as e:
        print(json.dumps({"error": f"Failed to load breed mapping: {str(e)}"}), file=sys.stderr)
        sys.exit(1)

class EliteDogClassifier(nn.Module):
    """Enhanced ConvNeXt-Large based dog breed classifier."""
    def __init__(self, num_classes=120):
        super().__init__()
        self.backbone = models.convnext_large(weights=None)
        in_features = self.backbone.classifier[2].in_features
        self.backbone.classifier = nn.Sequential(
            self.backbone.classifier[0], 
            self.backbone.classifier[1],
            nn.Dropout(0.3), 
            nn.Linear(in_features, 512),
            nn.GELU(), 
            nn.Dropout(0.2), 
            nn.Linear(512, num_classes)
        )

    def forward_features(self, x):
        """Extract feature embeddings (512-dim) before final classification."""
        x = self.backbone.features(x)
        x = self.backbone.avgpool(x)
        x = self.backbone.classifier[0](x)  # LayerNorm
        x = self.backbone.classifier[1](x)  # Flatten
        x = self.backbone.classifier[2](x)  # Dropout
        x = self.backbone.classifier[3](x)  # Linear to 512
        x = self.backbone.classifier[4](x)  # GELU
        return x

    def forward(self, x):
        """Full forward pass including classification."""
        x = self.forward_features(x)
        x = self.backbone.classifier[5](x)  # Dropout
        x = self.backbone.classifier[6](x)  # Final Linear
        return x

def load_model():
    """Load the trained model and prepare for inference."""
    script_dir = os.path.dirname(os.path.abspath(__file__))
    model_path = os.path.join(script_dir, 'best_model.pth')
    mapping_path = os.path.join(script_dir, 'breed_mapping.json')
    
    try:
        device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')
        
        with open(mapping_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
            num_classes = len(data['breeds'])
        
        model = EliteDogClassifier(num_classes=num_classes)
        state_dict = torch.load(model_path, map_location=device, weights_only=False)
        model.load_state_dict(state_dict)
        model.eval()
        model.to(device)
        
        return model, device
    except Exception as e:
        print(json.dumps({"error": f"Failed to load model: {str(e)}"}), file=sys.stderr)
        sys.exit(1)

def preprocess_image(image_path):
    """Preprocess image for model input."""
    try:
        transform = transforms.Compose([
            transforms.Resize((384, 384)),
            transforms.ToTensor(),
            transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225])
        ])
        image = Image.open(image_path).convert('RGB')
        return transform(image).unsqueeze(0)
    except Exception as e:
        print(json.dumps({"error": f"Failed to preprocess image: {str(e)}"}), file=sys.stderr)
        sys.exit(1)

# ============================================================================
# SIMILARITY METRICS
# ============================================================================

def euclidean_distance(emb1, emb2):
    """Calculate Euclidean distance between two embeddings."""
    return float(np.linalg.norm(emb1 - emb2))

def cosine_similarity(emb1, emb2):
    """Calculate cosine similarity between two embeddings (0-1 scale)."""
    dot_product = np.dot(emb1, emb2)
    norm_product = np.linalg.norm(emb1) * np.linalg.norm(emb2)
    if norm_product == 0:
        return 0.0
    return float(dot_product / norm_product)

def calculate_combined_similarity(emb1, emb2):
    """
    Calculate both similarity metrics and return a combined score.
    Returns: (euclidean_dist, cosine_sim, combined_score)
    """
    euclidean_dist = euclidean_distance(emb1, emb2)
    cosine_sim = cosine_similarity(emb1, emb2)
    
    # Normalize Euclidean distance to 0-1 scale (inverted, higher = more similar)
    # Using sigmoid-like function for smooth transition
    euclidean_score = 1.0 / (1.0 + euclidean_dist / 10.0)
    
    # Combined score (weighted average, slightly favor cosine similarity)
    combined_score = (euclidean_score * 0.4) + (cosine_sim * 0.6)
    
    return euclidean_dist, cosine_sim, combined_score

# ============================================================================
# MEMORY ANALYSIS
# ============================================================================

def get_breed_threshold(breed_name, metric='euclidean'):
    """Get breed-specific threshold, or default if not specified."""
    if breed_name in BREED_SPECIFIC_THRESHOLDS:
        return BREED_SPECIFIC_THRESHOLDS[breed_name].get(metric, 
            VERY_SIMILAR_THRESHOLD if metric == 'euclidean' else COSINE_VERY_SIMILAR_THRESHOLD)
    return VERY_SIMILAR_THRESHOLD if metric == 'euclidean' else COSINE_VERY_SIMILAR_THRESHOLD

def is_visually_similar_breed(breed1, breed2):
    """Check if two breeds are known to be visually similar."""
    if breed1 in BREED_SPECIFIC_THRESHOLDS:
        similar_breeds = BREED_SPECIFIC_THRESHOLDS[breed1].get('similar_breeds', [])
        if breed2 in similar_breeds:
            return True
    if breed2 in BREED_SPECIFIC_THRESHOLDS:
        similar_breeds = BREED_SPECIFIC_THRESHOLDS[breed2].get('similar_breeds', [])
        if breed1 in similar_breeds:
            return True
    return False

def analyze_memory_matches(current_emb, references):
    """
    Analyze all memory matches and group by breed with statistics.
    Returns: dict mapping breed -> list of match info
    """
    breed_matches = defaultdict(list)
    
    for ref in references:
        ref_emb = np.array(ref['embedding'])
        ref_label = ref['label']
        
        euclidean_dist, cosine_sim, combined_score = calculate_combined_similarity(
            current_emb, ref_emb
        )
        
        match_info = {
            'euclidean_distance': euclidean_dist,
            'cosine_similarity': cosine_sim,
            'combined_score': combined_score,
            'source_image': ref.get('source_image', 'unknown'),
            'added_at': ref.get('added_at', 'unknown')
        }
        
        breed_matches[ref_label].append(match_info)
    
    # Calculate statistics for each breed
    breed_stats = {}
    for breed, matches in breed_matches.items():
        if len(matches) == 0:
            continue
            
        euclidean_dists = [m['euclidean_distance'] for m in matches]
        cosine_sims = [m['cosine_similarity'] for m in matches]
        combined_scores = [m['combined_score'] for m in matches]
        
        breed_stats[breed] = {
            'num_examples': len(matches),
            'best_match': min(matches, key=lambda x: x['euclidean_distance']),
            'avg_euclidean': float(np.mean(euclidean_dists)),
            'min_euclidean': float(np.min(euclidean_dists)),
            'std_euclidean': float(np.std(euclidean_dists)) if len(matches) > 1 else 0.0,
            'avg_cosine': float(np.mean(cosine_sims)),
            'max_cosine': float(np.max(cosine_sims)),
            'avg_combined_score': float(np.mean(combined_scores)),
            'max_combined_score': float(np.max(combined_scores)),
            'all_matches': matches
        }
    
    return breed_stats

def calculate_memory_confidence(breed_stats, breed_name):
    """
    FIXED: Calculate confidence score for exact matches properly.
    Exact duplicates (distance < 5.0) should get 99-100% confidence.
    """
    stats = breed_stats.get(breed_name, {})
    if not stats:
        return 0.5
    
    num_examples = stats['num_examples']
    min_euclidean = stats['min_euclidean']
    max_cosine = stats['max_cosine']
    std_euclidean = stats['std_euclidean']
    max_combined = stats['max_combined_score']
    
    # CRITICAL FIX: Exact duplicate detection with proper confidence scoring
    if min_euclidean < EXACT_DUPLICATE_THRESHOLD:
        # This is an exact duplicate or the same dog
        if min_euclidean < 0.1:
            # Essentially the EXACT SAME IMAGE (distance < 0.1)
            base_conf = 1.00  # 100% confidence
        elif min_euclidean < 0.5:
            # Nearly identical image (0.1-0.5 distance)
            base_conf = 0.995  # 99.5% confidence
        elif min_euclidean < 1.0:
            # Almost identical (0.5-1.0) - same image, slight variation
            base_conf = 0.99  # 99% confidence
        elif min_euclidean < 2.0:
            # Very close (1.0-2.0) - same dog, same session
            base_conf = 0.985  # 98.5% confidence
        elif min_euclidean < 3.5:
            # Close (2.0-3.5) - same dog, similar conditions
            base_conf = 0.97  # 97% confidence
        else:
            # Same dog, different conditions (3.5-5.0)
            base_conf = 0.96 - ((min_euclidean - 3.5) / 1.5) * 0.01
    elif max_cosine > COSINE_EXACT_THRESHOLD:
        base_conf = 0.94
    elif max_combined > 0.90:
        base_conf = 0.88
    elif min_euclidean < VERY_SIMILAR_THRESHOLD and max_cosine > COSINE_VERY_SIMILAR_THRESHOLD:
        base_conf = 0.82
    elif max_combined > 0.80:
        base_conf = 0.75
    else:
        base_conf = 0.65
    
    # Boost for multiple examples (but NOT for exact duplicates - they're already at 100%)
    if base_conf < 0.98:
        if num_examples >= 5:
            example_boost = 0.08
        elif num_examples >= 3:
            example_boost = 0.05
        elif num_examples >= 2:
            example_boost = 0.03
        else:
            example_boost = 0.0
    else:
        # Already near-perfect, no boost needed
        example_boost = 0.0
    
    # Penalty for high variance (but NOT for exact duplicates)
    if num_examples >= MIN_EXAMPLES_FOR_STATS and min_euclidean >= EXACT_DUPLICATE_THRESHOLD:
        if std_euclidean > 5.0:
            variance_penalty = -0.05
        elif std_euclidean > 3.0:
            variance_penalty = -0.03
        else:
            variance_penalty = 0.0
    else:
        variance_penalty = 0.0
    
    final_conf = base_conf + example_boost + variance_penalty
    return min(1.00, max(0.55, final_conf))

# ============================================================================
# DECISION LOGIC
# ============================================================================

def make_weighted_decision(model_breed, model_conf, breed_stats, model_top5):
    """
    FIXED: Make final prediction with proper handling of exact matches.
    """
    decision_info = {
        'method': 'weighted_scoring',
        'model_breed': model_breed,
        'model_confidence': model_conf,
        'memory_breeds_considered': list(breed_stats.keys()),
        'scores': {}
    }
    
    # Calculate model score (0-100 scale)
    model_score = model_conf * 100
    decision_info['scores'][model_breed] = {
        'source': 'model',
        'score': model_score,
        'confidence': model_conf
    }
    
    # Calculate memory scores for each breed
    memory_candidates = []
    for breed, stats in breed_stats.items():
        min_euclidean = stats['min_euclidean']
        max_cosine = stats['max_cosine']
        max_combined = stats['max_combined_score']
        num_examples = stats['num_examples']
        
        # Get breed-specific threshold
        breed_threshold_euc = get_breed_threshold(breed, 'euclidean')
        breed_threshold_cos = get_breed_threshold(breed, 'cosine')
        
        # Only consider if reasonably similar
        if min_euclidean > WEAK_SIMILARITY_THRESHOLD and max_cosine < COSINE_WEAK_THRESHOLD:
            continue
        
        # Calculate memory confidence
        memory_conf = calculate_memory_confidence(breed_stats, breed)
        
        # CRITICAL FIX: Exact match scoring
        if min_euclidean < EXACT_DUPLICATE_THRESHOLD:
            # Exact duplicate - give it maximum priority
            if min_euclidean < 0.1:
                memory_score = 100  # Exact same image = 100 score
            elif min_euclidean < 0.5:
                memory_score = 99.5
            elif min_euclidean < 1.0:
                memory_score = 99
            elif min_euclidean < 2.0:
                memory_score = 98.5
            elif min_euclidean < 3.5:
                memory_score = 98
            else:
                memory_score = 97
        elif max_cosine > COSINE_EXACT_THRESHOLD:
            memory_score = 96
        elif min_euclidean < breed_threshold_euc:
            # Very similar, use adaptive scoring
            ratio = min_euclidean / breed_threshold_euc
            memory_score = 90 - (ratio * 15)
        elif max_cosine > breed_threshold_cos:
            ratio = (max_cosine - COSINE_WEAK_THRESHOLD) / (breed_threshold_cos - COSINE_WEAK_THRESHOLD)
            memory_score = 85 * ratio
        elif max_combined > 0.80:
            memory_score = 75 + (max_combined - 0.80) * 50
        else:
            memory_score = 60 + (max_combined - 0.70) * 50
        
        # Boost score if multiple examples (but not for exact matches - already at max)
        if num_examples >= 3 and memory_score < 99:
            memory_score += 5
        
        memory_score = min(100, memory_score)
        
        memory_candidates.append({
            'breed': breed,
            'score': memory_score,
            'confidence': memory_conf,
            'stats': stats
        })
        
        decision_info['scores'][breed] = {
            'source': 'memory',
            'score': memory_score,
            'confidence': memory_conf,
            'num_examples': num_examples,
            'min_euclidean': min_euclidean,
            'max_cosine': max_cosine
        }
    
    # Sort memory candidates by score
    memory_candidates.sort(key=lambda x: x['score'], reverse=True)
    
    # Decision logic
    if not memory_candidates:
        # No memory matches, use model
        decision_info['decision'] = 'no_memory_matches'
        decision_info['memory_used'] = False
        return model_breed, model_conf, decision_info
    
    best_memory = memory_candidates[0]
    best_memory_breed = best_memory['breed']
    best_memory_score = best_memory['score']
    best_memory_conf = best_memory['confidence']
    
    # CRITICAL FIX: If we have an exact duplicate, ALWAYS use it
    if best_memory['stats']['min_euclidean'] < EXACT_DUPLICATE_THRESHOLD:
        decision_info['decision'] = 'memory_exact_duplicate'
        decision_info['memory_used'] = True
        decision_info['agreement'] = (model_breed == best_memory_breed)
        return best_memory_breed, best_memory_conf, decision_info
    
    # Check if model and memory agree
    if model_breed == best_memory_breed:
        # Agreement - boost confidence
        agreement_boost = 0.08 if best_memory_score > 85 else 0.05
        final_conf = min(0.98, max(model_conf, best_memory_conf) + agreement_boost)
        decision_info['decision'] = 'agreement_confidence_boost'
        decision_info['memory_used'] = True
        decision_info['agreement'] = True
        return model_breed, final_conf, decision_info
    
    # Disagreement - use scoring
    breeds_similar = is_visually_similar_breed(model_breed, best_memory_breed)
    
    if breeds_similar:
        decision_info['visually_similar_breeds'] = True
        margin_required = 20
    else:
        margin_required = 15
    
    # Model very high confidence (>90%) and memory not extremely strong
    if model_conf > MODEL_VERY_HIGH_CONF and best_memory_score < 92:
        decision_info['decision'] = 'model_very_high_confidence_override'
        decision_info['memory_used'] = False
        decision_info['agreement'] = False
        return model_breed, model_conf, decision_info
    
    # Use score comparison with margin
    if best_memory_score > model_score + margin_required:
        decision_info['decision'] = 'memory_score_advantage'
        decision_info['memory_used'] = True
        decision_info['agreement'] = False
        decision_info['score_margin'] = best_memory_score - model_score
        return best_memory_breed, best_memory_conf, decision_info
    
    if model_score > best_memory_score + margin_required:
        decision_info['decision'] = 'model_score_advantage'
        decision_info['memory_used'] = False
        decision_info['agreement'] = False
        decision_info['score_margin'] = model_score - best_memory_score
        return model_breed, model_conf, decision_info
    
    # Scores close - use highest confidence
    if best_memory_conf > model_conf:
        decision_info['decision'] = 'memory_higher_confidence'
        decision_info['memory_used'] = True
        decision_info['agreement'] = False
        return best_memory_breed, best_memory_conf, decision_info
    else:
        decision_info['decision'] = 'model_higher_confidence'
        decision_info['memory_used'] = False
        decision_info['agreement'] = False
        return model_breed, model_conf, decision_info

def check_memory(embedding, ref_file, model_breed, model_conf, model_top5):
    """
    Enhanced memory checking with multi-example support and weighted decisions.
    
    Returns: (breed_to_use_or_None, detailed_memory_info)
    """
    if not ref_file or not os.path.exists(ref_file):
        return None, {
            'memory_available': False,
            'memory_used': False,
            'decision': 'no_memory_file'
        }
    
    try:
        with open(ref_file, 'r') as f:
            references = json.load(f)
        
        if not references:
            return None, {
                'memory_available': True,
                'memory_used': False,
                'memory_size': 0,
                'decision': 'empty_memory'
            }
        
        current_emb = embedding.cpu().detach().numpy().flatten()
        
        # Analyze all memory matches
        breed_stats = analyze_memory_matches(current_emb, references)
        
        if not breed_stats:
            return None, {
                'memory_available': True,
                'memory_used': False,
                'memory_size': len(references),
                'decision': 'no_similar_matches'
            }
        
        # Make weighted decision
        final_breed, final_conf, decision_info = make_weighted_decision(
            model_breed, model_conf, breed_stats, model_top5
        )
        
        # Build comprehensive memory info
        memory_info = {
            'memory_available': True,
            'memory_size': len(references),
            'unique_breeds_in_memory': len(breed_stats),
            'memory_used': decision_info.get('memory_used', False),
            'decision': decision_info['decision'],
            'agreement': decision_info.get('agreement', None),
            'breed_statistics': {},
            'final_breed': final_breed,
            'final_confidence': final_conf,
            'decision_details': decision_info
        }
        
        # Add top 3 memory matches for transparency
        for breed, stats in sorted(breed_stats.items(), 
                                   key=lambda x: x[1]['min_euclidean'])[:3]:
            memory_info['breed_statistics'][breed] = {
                'num_examples': stats['num_examples'],
                'min_euclidean': stats['min_euclidean'],
                'max_cosine': stats['max_cosine'],
                'avg_combined_score': stats['avg_combined_score']
            }
        
        # Return breed if memory was used, None if model was used
        if decision_info.get('memory_used', False):
            return final_breed, memory_info
        else:
            return None, memory_info
        
    except Exception as e:
        return None, {
            'memory_available': False,
            'memory_used': False,
            'error': str(e)
        }

# ============================================================================
# MAIN PREDICTION
# ============================================================================

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No image path provided"}))
        sys.exit(1)
    
    image_path = sys.argv[1]
    ref_file = sys.argv[2] if len(sys.argv) > 2 else None

    try:
        # Load resources
        breed_mapping = load_breed_mapping()
        model, device = load_model()
        image_tensor = preprocess_image(image_path).to(device)

        # Extract embedding
        with torch.no_grad():
            embedding = model.forward_features(image_tensor)

        # Get model predictions
        with torch.no_grad():
            x = model.backbone.classifier[5](embedding)
            outputs = model.backbone.classifier[6](x)
            probabilities = torch.nn.functional.softmax(outputs, dim=1)
            top_probs, top_indices = torch.topk(probabilities, 5)
        
        model_breed_idx = str(top_indices[0][0].item())
        model_breed_name = breed_mapping.get(model_breed_idx, 'Unknown')
        model_confidence = float(top_probs[0][0].item())
        
        # Build model top 5
        model_top5 = []
        for i in range(len(top_probs[0])):
            breed_idx = str(top_indices[0][i].item())
            breed_name = breed_mapping.get(breed_idx, 'Unknown')
            model_top5.append({
                'breed': breed_name,
                'confidence': float(top_probs[0][i].item())
            })

        # Check memory with enhanced logic
        memory_match, memory_info = check_memory(
            embedding, ref_file, model_breed_name, model_confidence, model_top5
        )

        # Build results
        results = {
            'is_memory_match': False,
            'memory_info': memory_info,
            'top_5': [],
            'learning_stats': {
                'memory_available': memory_info.get('memory_available', False),
                'memory_size': memory_info.get('memory_size', 0),
                'memory_used': memory_info.get('memory_used', False),
                'unique_breeds_in_memory': memory_info.get('unique_breeds_in_memory', 0),
            }
        }

        if memory_match:
            # Memory-based prediction
            results['breed'] = memory_match
            results['is_memory_match'] = True
            results['confidence'] = memory_info['final_confidence']
            
            # Top 5 with memory result first
            results['top_5'].append({
                'breed': memory_match,
                'confidence': memory_info['final_confidence'],
                'source': 'memory'
            })
            
            # Add model predictions that don't match
            for pred in model_top5:
                if pred['breed'] != memory_match:
                    results['top_5'].append({
                        'breed': pred['breed'],
                        'confidence': pred['confidence'],
                        'source': 'model'
                    })
                    if len(results['top_5']) >= 5:
                        break
        else:
            # Model-based prediction
            results['breed'] = model_breed_name
            results['confidence'] = model_confidence
            
            # Use model's top 5
            for pred in model_top5:
                results['top_5'].append({
                    'breed': pred['breed'],
                    'confidence': pred['confidence'],
                    'source': 'model'
                })
        
        print(json.dumps(results))

    except Exception as e:
        print(json.dumps({"error": f"Execution error: {str(e)}"}))
        sys.exit(1)

if __name__ == "__main__":
    main()