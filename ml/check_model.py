import torch
import json

# Load the model file
model_path = 'best_model.pth'
checkpoint = torch.load(model_path, map_location='cpu')

print("=" * 60)
print("MODEL INSPECTION")
print("=" * 60)

# Check if it's a dictionary or model
if isinstance(checkpoint, dict):
    print("\n✅ Model is saved as STATE DICT")
    print("\nKeys in checkpoint:")
    for key in checkpoint.keys():
        print(f"  - {key}: {checkpoint[key].shape if hasattr(checkpoint[key], 'shape') else type(checkpoint[key])}")
    
    # Try to detect the architecture
    if 'fc.weight' in checkpoint:
        num_classes = checkpoint['fc.weight'].shape[0]
        print(f"\n🎯 Detected output classes: {num_classes}")
        print("   Architecture likely: ResNet")
    elif 'classifier.weight' in checkpoint:
        num_classes = checkpoint['classifier.weight'].shape[0]
        print(f"\n🎯 Detected output classes: {num_classes}")
        print("   Architecture likely: VGG or AlexNet")
    elif 'heads.head.weight' in checkpoint:
        num_classes = checkpoint['heads.head.weight'].shape[0]
        print(f"\n🎯 Detected output classes: {num_classes}")
        print("   Architecture likely: Vision Transformer (ViT)")
else:
    print("\n✅ Model is saved as COMPLETE MODEL")
    print(f"   Type: {type(checkpoint)}")
    print(f"\n   Model architecture:")
    print(checkpoint)

print("\n" + "=" * 60)