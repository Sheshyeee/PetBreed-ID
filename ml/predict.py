#!/usr/bin/env python3
import sys
import json
import torch
import torch.nn as nn
from torchvision import transforms, models
from PIL import Image
import os
import numpy as np

# --- STRICT CONFIGURATION ---

# 1. DUPLICATE_THRESHOLD (5.0):
# If distance is lower than this, it is the EXACT same image/dog.
# We ALWAYS trust the memory here, even if the model disagrees.
DUPLICATE_THRESHOLD = 5.0

# 2. OVERRIDE_THRESHOLD (12.0):
# This is the "Danger Zone". We lowered this from 18.0 to 12.0.
# The image must be VERY similar to trigger a correction.
OVERRIDE_THRESHOLD = 12.0

# 3. MODEL_CONFIDENCE_VETO (0.85):
# THIS IS THE FIX.
# If the AI is >85% sure that it is a "Husky", we will IGNORE the "Samoyed" memory
# even if the distance is close. We assume the AI knows better for clear images.
MODEL_CONFIDENCE_VETO = 0.85

def load_breed_mapping():
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
        sys.exit(1)

class EliteDogClassifier(nn.Module):
    def __init__(self, num_classes=120):
        super().__init__()
        self.backbone = models.convnext_large(weights=None)
        in_features = self.backbone.classifier[2].in_features
        self.backbone.classifier = nn.Sequential(
            self.backbone.classifier[0], self.backbone.classifier[1],
            nn.Dropout(0.3), nn.Linear(in_features, 512),
            nn.GELU(), nn.Dropout(0.2), nn.Linear(512, num_classes)
        )

    def forward_features(self, x):
        x = self.backbone.features(x)
        x = self.backbone.avgpool(x)
        x = self.backbone.classifier[0](x)
        x = self.backbone.classifier[1](x)
        x = self.backbone.classifier[2](x)
        x = self.backbone.classifier[3](x) 
        x = self.backbone.classifier[4](x)
        return x

    def forward(self, x):
        x = self.forward_features(x)
        x = self.backbone.classifier[5](x)
        x = self.backbone.classifier[6](x)
        return x

def load_model():
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
        sys.exit(1)

def preprocess_image(image_path):
    try:
        transform = transforms.Compose([
            transforms.Resize((384, 384)),
            transforms.ToTensor(),
            transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225])
        ])
        image = Image.open(image_path).convert('RGB')
        return transform(image).unsqueeze(0)
    except Exception as e:
        sys.exit(1)

def check_memory(embedding, ref_file, model_breed, model_conf):
    if not ref_file or not os.path.exists(ref_file):
        return None, {}
        
    try:
        with open(ref_file, 'r') as f:
            references = json.load(f)
        if not references: return None, {}
            
        current_emb = embedding.cpu().detach().numpy().flatten()
        
        # Find Closest Match
        closest_dist = float('inf')
        closest_ref = None
        
        for ref in references:
            ref_emb = np.array(ref['embedding'])
            dist = np.linalg.norm(current_emb - ref_emb)
            if dist < closest_dist:
                closest_dist = dist
                closest_ref = ref

        memory_info = {
            'closest_breed': closest_ref['label'],
            'closest_distance': closest_dist,
            'source_image': closest_ref.get('source_image', 'unknown')
        }

        # --- LOGIC GATE ---

        # 1. EXACT MATCH (Distance < 5.0)
        # It is effectively the same photo. Trust Memory ALWAYS.
        if closest_dist < DUPLICATE_THRESHOLD:
            memory_info['decision'] = 'exact_duplicate_trust_memory'
            return closest_ref['label'], memory_info

        # 2. CLOSE MATCH (Distance 5.0 - 12.0)
        # It looks very similar. But we must check if the Model disagrees strongly.
        elif closest_dist < OVERRIDE_THRESHOLD:
            
            # THE FIX: If the model is > 85% confident, Model WINS.
            # This prevents a clear "Husky" from being labeled "Samoyed" 
            # just because it looks fluffy.
            if model_conf > MODEL_CONFIDENCE_VETO:
                memory_info['decision'] = f'vetoed_by_high_conf_model_({model_conf:.2f})'
                return None, memory_info
            
            # If Model is unsure (< 85%), we trust the manual correction.
            memory_info['decision'] = 'close_match_accepted_model_unsure'
            return closest_ref['label'], memory_info

        # 3. WEAK MATCH (Distance > 12.0)
        # Not close enough. Trust Model.
        else:
            memory_info['decision'] = 'too_distant_trust_model'
            return None, memory_info
            
    except Exception as e:
        return None, {'error': str(e)}

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No image path provided"}))
        sys.exit(1)
    
    image_path = sys.argv[1]
    ref_file = sys.argv[2] if len(sys.argv) > 2 else None 

    try:
        breed_mapping = load_breed_mapping()
        model, device = load_model()
        image_tensor = preprocess_image(image_path).to(device)

        with torch.no_grad():
            embedding = model.forward_features(image_tensor)

        with torch.no_grad():
            x = model.backbone.classifier[5](embedding)
            outputs = model.backbone.classifier[6](x)
            probabilities = torch.nn.functional.softmax(outputs, dim=1)
            top_probs, top_indices = torch.topk(probabilities, 5)
        
        model_breed_idx = str(top_indices[0][0].item())
        model_breed_name = breed_mapping.get(model_breed_idx, 'Unknown')
        model_confidence = float(top_probs[0][0].item())

        # Check Memory with Strict Veto
        memory_match, memory_info = check_memory(
            embedding, ref_file, model_breed_name, model_confidence
        )

        results = {
            'is_memory_match': False,
            'memory_info': memory_info,
            'top_5': []
        }

        if memory_match:
            results['breed'] = memory_match
            results['is_memory_match'] = True
            # Calculate strict confidence for memory match
            dist = memory_info.get('closest_distance', 20.0)
            calc_conf = max(0.85, 1.0 - (dist / 60.0))
            results['confidence'] = calc_conf
            results['top_5'].append({'breed': memory_match, 'confidence': calc_conf})
        else:
            results['breed'] = model_breed_name
            results['confidence'] = model_confidence
            for i in range(len(top_probs[0])):
                breed_idx = str(top_indices[0][i].item())
                breed_name = breed_mapping.get(breed_idx, 'Unknown')
                results['top_5'].append({
                    'breed': breed_name,
                    'confidence': float(top_probs[0][i].item())
                })
        
        print(json.dumps(results))

    except Exception as e:
        print(json.dumps({"error": f"Execution error: {str(e)}"}))
        sys.exit(1)

if __name__ == "__main__":
    main()