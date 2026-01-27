#!/usr/bin/env python3
import sys
import json
import torch
import torch.nn as nn
from torchvision import transforms, models
from PIL import Image
import os

def load_breed_mapping():
    """Load breed mapping from JSON file"""
    script_dir = os.path.dirname(os.path.abspath(__file__))
    mapping_path = os.path.join(script_dir, 'breed_mapping.json')

    try:
        with open(mapping_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
            breeds = data['breeds']

            # Create mapping: index -> clean breed name
            breed_mapping = {}
            for idx, breed_id in enumerate(breeds):
                # Remove 'nXXXXXXXX-' prefix and replace underscores with spaces
                breed_name = breed_id.split('-', 1)[1].replace('_', ' ')
                breed_mapping[str(idx)] = breed_name

            return breed_mapping
    except FileNotFoundError:
        print(json.dumps({"error": f"breed_mapping.json not found at {mapping_path}"}))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({"error": f"Error loading breed mapping: {str(e)}"}))
        sys.exit(1)

class EliteDogClassifier(nn.Module):
    """Dog breed classifier using ConvNeXt backbone"""
    def __init__(self, num_classes=120):
        super().__init__()
        self.backbone = models.convnext_large(weights=None)  # No pretrained weights needed

        in_features = self.backbone.classifier[2].in_features

        self.backbone.classifier = nn.Sequential(
            self.backbone.classifier[0],  # LayerNorm
            self.backbone.classifier[1],  # Flatten
            nn.Dropout(0.3),
            nn.Linear(in_features, 512),
            nn.GELU(),
            nn.Dropout(0.2),
            nn.Linear(512, num_classes)
        )

    def forward(self, x):
        return self.backbone(x)

def load_model():
    """Load the trained model"""
    script_dir = os.path.dirname(os.path.abspath(__file__))
    model_path = os.path.join(script_dir, 'best_model.pth')
    mapping_path = os.path.join(script_dir, 'breed_mapping.json')

    try:
        device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')

        # Load breed mapping to get number of classes
        with open(mapping_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
            num_classes = len(data['breeds'])

        # Create model architecture
        model = EliteDogClassifier(num_classes=num_classes)

        # Load state dict
        state_dict = torch.load(model_path, map_location=device, weights_only=False)
        model.load_state_dict(state_dict)

        model.eval()
        model.to(device)

        return model, device

    except FileNotFoundError:
        print(json.dumps({"error": f"Model file not found at {model_path}"}))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({"error": f"Error loading model: {str(e)}"}))
        sys.exit(1)

def preprocess_image(image_path):
    """Preprocess the image for prediction"""
    try:
        transform = transforms.Compose([
            transforms.Resize((384, 384)),
            transforms.ToTensor(),
            transforms.Normalize(
                mean=[0.485, 0.456, 0.406],
                std=[0.229, 0.224, 0.225]
            )
        ])

        image = Image.open(image_path).convert('RGB')
        image_tensor = transform(image).unsqueeze(0)

        return image_tensor
    except Exception as e:
        print(json.dumps({"error": f"Error processing image: {str(e)}"}))
        sys.exit(1)

def predict(model, image_tensor, breed_mapping, device):
    """Make prediction on the image"""
    try:
        image_tensor = image_tensor.to(device)
        
        with torch.no_grad():
            outputs = model(image_tensor)
            probabilities = torch.nn.functional.softmax(outputs, dim=1)
            top_probs, top_indices = torch.topk(probabilities, min(5, len(breed_mapping)))
        
        top_breed_idx = str(top_indices[0][0].item())
        top_breed_name = breed_mapping.get(top_breed_idx, f'Unknown breed (index {top_breed_idx})')
        
        results = {
            'breed': top_breed_name,
            'confidence': float(top_probs[0][0].item()),
            'top_5': []
        }
        
        for i in range(len(top_probs[0])):
            breed_idx = str(top_indices[0][i].item())
            breed_name = breed_mapping.get(breed_idx, f'Unknown breed (index {breed_idx})')
            confidence = float(top_probs[0][i].item())
            
            results['top_5'].append({
                'breed': breed_name,
                'confidence': confidence
            })
        
        return results
    except Exception as e:
        print(json.dumps({"error": f"Error during prediction: {str(e)}"}))
        sys.exit(1)

def main():
    """Main function"""
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No image path provided"}))
        sys.exit(1)
    
    image_path = sys.argv[1]
    
    if not os.path.exists(image_path):
        print(json.dumps({"error": f"Image file not found: {image_path}"}))
        sys.exit(1)
    
    try:
        breed_mapping = load_breed_mapping()
        model, device = load_model()
        image_tensor = preprocess_image(image_path)
        results = predict(model, image_tensor, breed_mapping, device)
        print(json.dumps(results))
    except Exception as e:
        print(json.dumps({"error": f"Unexpected error: {str(e)}"}))
        sys.exit(1)

if __name__ == "__main__":
    main()