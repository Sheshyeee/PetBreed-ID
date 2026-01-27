"""
🐕 DOG BREED CLASSIFIER - INFERENCE SCRIPT
Run this in VSCode to test your trained model

Requirements:
- best_model.pth (trained model weights)
- breed_mapping.json (breed labels)
- test image(s)
"""

import torch
import torch.nn as nn
from torchvision import transforms, models
from PIL import Image
import json
import os

# ============================================================================
# MODEL DEFINITION (must match training)
# ============================================================================
class EliteDogClassifier(nn.Module):
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

# ============================================================================
# INFERENCE CLASS
# ============================================================================
class DogBreedPredictor:
    def __init__(self, model_path='best_model.pth', mapping_path='breed_mapping.json'):
        """
        Initialize the predictor
        
        Args:
            model_path: Path to best_model.pth
            mapping_path: Path to breed_mapping.json
        """
        self.device = 'cuda' if torch.cuda.is_available() else 'cpu'
        print(f"🔧 Using device: {self.device}")
        
        # Load breed mapping
        print(f"📂 Loading breed mapping from: {mapping_path}")
        with open(mapping_path, 'r') as f:
            mapping = json.load(f)
            self.breeds = mapping['breeds']
            self.breed_to_idx = mapping['breed_to_idx']
            self.idx_to_breed = {v: k for k, v in self.breed_to_idx.items()}
        
        print(f"✓ Loaded {len(self.breeds)} breeds")
        
        # Load model
        print(f"🤖 Loading model from: {model_path}")
        self.model = EliteDogClassifier(num_classes=len(self.breeds))
        self.model.load_state_dict(torch.load(model_path, map_location=self.device, weights_only=False))
        self.model.to(self.device)
        self.model.eval()
        print("✓ Model loaded successfully")
        
        # Image preprocessing
        self.transform = transforms.Compose([
            transforms.Resize((384, 384)),
            transforms.ToTensor(),
            transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225])
        ])
    
    def predict(self, image_path, top_k=5):
        """
        Predict dog breed from image
        
        Args:
            image_path: Path to image file
            top_k: Number of top predictions to return
            
        Returns:
            List of (breed_name, confidence) tuples
        """
        # Load and preprocess image
        image = Image.open(image_path).convert('RGB')
        image_tensor = self.transform(image).unsqueeze(0).to(self.device)
        
        # Predict
        with torch.no_grad():
            outputs = self.model(image_tensor)
            probabilities = torch.nn.functional.softmax(outputs, dim=1)
        
        # Get top K predictions
        top_probs, top_indices = torch.topk(probabilities[0], top_k)
        
        results = []
        for prob, idx in zip(top_probs, top_indices):
            breed_name = self.idx_to_breed[idx.item()]
            # Clean up breed name (remove prefix like "n02085620-")
            clean_name = breed_name.split('-', 1)[1] if '-' in breed_name else breed_name
            clean_name = clean_name.replace('_', ' ').title()
            results.append((clean_name, prob.item() * 100))
        
        return results
    
    def predict_with_tta(self, image_path, top_k=5, num_augmentations=5):
        """
        Predict with Test-Time Augmentation for better accuracy
        
        Args:
            image_path: Path to image file
            top_k: Number of top predictions to return
            num_augmentations: Number of augmented versions to test
            
        Returns:
            List of (breed_name, confidence) tuples
        """
        image = Image.open(image_path).convert('RGB')
        
        # TTA transforms
        tta_transforms = [
            transforms.Compose([
                transforms.Resize((384, 384)),
                transforms.ToTensor(),
                transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225])
            ]),
            transforms.Compose([
                transforms.Resize((384, 384)),
                transforms.RandomHorizontalFlip(p=1.0),
                transforms.ToTensor(),
                transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225])
            ]),
            transforms.Compose([
                transforms.Resize((416, 416)),
                transforms.CenterCrop(384),
                transforms.ToTensor(),
                transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225])
            ]),
            transforms.Compose([
                transforms.Resize((416, 416)),
                transforms.RandomCrop(384),
                transforms.ToTensor(),
                transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225])
            ]),
            transforms.Compose([
                transforms.Resize((384, 384)),
                transforms.ColorJitter(brightness=0.1, contrast=0.1),
                transforms.ToTensor(),
                transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225])
            ]),
        ]
        
        all_outputs = []
        
        with torch.no_grad():
            for transform in tta_transforms[:num_augmentations]:
                image_tensor = transform(image).unsqueeze(0).to(self.device)
                outputs = self.model(image_tensor)
                all_outputs.append(outputs)
        
        # Average predictions
        avg_outputs = torch.stack(all_outputs).mean(0)
        probabilities = torch.nn.functional.softmax(avg_outputs, dim=1)
        
        # Get top K predictions
        top_probs, top_indices = torch.topk(probabilities[0], top_k)
        
        results = []
        for prob, idx in zip(top_probs, top_indices):
            breed_name = self.idx_to_breed[idx.item()]
            clean_name = breed_name.split('-', 1)[1] if '-' in breed_name else breed_name
            clean_name = clean_name.replace('_', ' ').title()
            results.append((clean_name, prob.item() * 100))
        
        return results

# ============================================================================
# EXAMPLE USAGE
# ============================================================================
if __name__ == '__main__':
    # Initialize predictor
    predictor = DogBreedPredictor(
        model_path='best_model.pth',
        mapping_path='breed_mapping.json'
    )
    
    # Example 1: Single prediction
    print("\n" + "="*70)
    print("🔍 TESTING PREDICTION")
    print("="*70)
    
    # Replace with your test image path
    test_image = 'golden.jpg'
    
    if os.path.exists(test_image):
        # Regular prediction
        print(f"\n📸 Analyzing: {test_image}")
        results = predictor.predict(test_image, top_k=5)
        
        print("\n🏆 Top 5 Predictions:")
        for i, (breed, confidence) in enumerate(results, 1):
            print(f"  {i}. {breed:40s} {confidence:6.2f}%")
        
        # Prediction with TTA (more accurate but slower)
        print("\n🎯 Prediction with TTA (more accurate):")
        results_tta = predictor.predict_with_tta(test_image, top_k=5)
        
        print("\n🏆 Top 5 Predictions (TTA):")
        for i, (breed, confidence) in enumerate(results_tta, 1):
            print(f"  {i}. {breed:40s} {confidence:6.2f}%")
    else:
        print(f"\n⚠️  Test image not found: {test_image}")
        print("Please provide a test image and update the 'test_image' variable")
    
    # Example 2: Batch prediction
    print("\n" + "="*70)
    print("📁 BATCH PREDICTION")
    print("="*70)
    
    # Process all images in a folder
    test_folder = 'test_images'
    if os.path.exists(test_folder):
        image_files = [f for f in os.listdir(test_folder) 
                      if f.lower().endswith(('.jpg', '.jpeg', '.png'))]
        
        print(f"\nFound {len(image_files)} images in {test_folder}/")
        
        for img_file in image_files:
            img_path = os.path.join(test_folder, img_file)
            results = predictor.predict(img_path, top_k=3)
            
            print(f"\n📸 {img_file}")
            for i, (breed, confidence) in enumerate(results, 1):
                print(f"   {i}. {breed:35s} {confidence:6.2f}%")
    else:
        print(f"\n⚠️  Test folder not found: {test_folder}")
        print("Create a 'test_images' folder and add dog images for batch testing")
    
    print("\n" + "="*70)
    print("✅ INFERENCE COMPLETE")
    print("="*70)