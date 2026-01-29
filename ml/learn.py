#!/usr/bin/env python3
import sys
import json
import torch
import torch.nn as nn
from torchvision import transforms, models
from PIL import Image
import os
import numpy as np
from datetime import datetime

# CONFIGURATION
DUPLICATE_THRESHOLD = 5.0
SIMILAR_THRESHOLD = 15.0

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

def check_for_duplicates(embedding_array, ref_file, correct_label):
    if not os.path.exists(ref_file):
        return False, 'add', 'New reference file'
    
    try:
        with open(ref_file, 'r') as f:
            references = json.load(f)
    except:
        return False, 'add', 'New reference file'
    
    if not references:
        return False, 'add', 'First reference'
    
    current_emb = embedding_array.flatten()
    closest_distance = float('inf')
    closest_label = None
    
    for ref in references:
        ref_emb = np.array(ref['embedding'])
        dist = np.linalg.norm(current_emb - ref_emb)
        if dist < closest_distance:
            closest_distance = dist
            closest_label = ref['label']
    
    # 1. Exact Duplicate
    if closest_distance < DUPLICATE_THRESHOLD:
        if closest_label == correct_label:
            return True, 'skip', 'Duplicate detected.'
        else:
            return True, 'update', f'Updating label from {closest_label} to {correct_label}.'
    
    # 2. Similar Image
    elif closest_distance < SIMILAR_THRESHOLD:
        return False, 'add', f'Adding variation (dist: {closest_distance:.2f}).'
    
    # 3. Unique
    else:
        return False, 'add', 'New unique reference.'

def main():
    if len(sys.argv) < 4:
        print(json.dumps({"error": "Usage: learn.py <image> <label> <ref_file>"}))
        sys.exit(1)

    image_path = sys.argv[1]
    correct_label = sys.argv[2]
    ref_file = sys.argv[3]
    script_dir = os.path.dirname(os.path.abspath(__file__))
    model_path = os.path.join(script_dir, 'best_model.pth')
    mapping_path = os.path.join(script_dir, 'breed_mapping.json')

    try:
        device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')
        with open(mapping_path, 'r') as f:
            num_classes = len(json.load(f)['breeds'])
        
        model = EliteDogClassifier(num_classes=num_classes)
        state_dict = torch.load(model_path, map_location=device, weights_only=False)
        model.load_state_dict(state_dict)
        model.eval()
        model.to(device)

        transform = transforms.Compose([
            transforms.Resize((384, 384)),
            transforms.ToTensor(),
            transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225])
        ])
        img = Image.open(image_path).convert('RGB')
        img_tensor = transform(img).unsqueeze(0).to(device)

        with torch.no_grad():
            embedding_tensor = model.forward_features(img_tensor)
        
        embedding_array = embedding_tensor.cpu().numpy()
        embedding_list = embedding_array.flatten().tolist()

        is_dup, action, message = check_for_duplicates(embedding_array, ref_file, correct_label)

        data = []
        if os.path.exists(ref_file):
            with open(ref_file, 'r') as f:
                data = json.load(f)

        if action == 'skip':
            result = {"status": "skipped", "message": message}
        elif action == 'update':
            updated = False
            for ref in data:
                dist = np.linalg.norm(embedding_array.flatten() - np.array(ref['embedding']))
                if dist < DUPLICATE_THRESHOLD:
                    ref['label'] = correct_label
                    ref['updated_at'] = datetime.now().isoformat()
                    updated = True
                    break
            if updated:
                with open(ref_file, 'w') as f:
                    json.dump(data, f, indent=2)
                result = {"status": "updated", "message": message}
            else:
                result = {"status": "error", "message": "Update failed"}
        else:
            data.append({
                "label": correct_label,
                "embedding": embedding_list,
                "source_image": os.path.basename(image_path),
                "added_at": datetime.now().isoformat()
            })
            with open(ref_file, 'w') as f:
                json.dump(data, f, indent=2)
            result = {"status": "added", "message": message}

        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    main()