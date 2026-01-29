#!/usr/bin/env python3
"""
Model Inspector - Detect what model architecture is in the checkpoint
"""

import torch
import os

def inspect_model(model_path):
    """Inspect model checkpoint to determine architecture"""
    
    if not os.path.exists(model_path):
        print(f"❌ Model not found: {model_path}")
        return
    
    print("="*70)
    print("MODEL INSPECTOR")
    print("="*70)
    print(f"\nLoading: {model_path}")
    print(f"Size: {os.path.getsize(model_path):,} bytes\n")
    
    try:
        checkpoint = torch.load(model_path, map_location=torch.device('cpu'))
        
        # Determine if checkpoint has state_dict wrapper
        if isinstance(checkpoint, dict) and 'state_dict' in checkpoint:
            state_dict = checkpoint['state_dict']
            print("✓ Checkpoint contains 'state_dict' key")
            
            # Check for other keys
            if 'epoch' in checkpoint:
                print(f"  Epoch: {checkpoint['epoch']}")
            if 'optimizer' in checkpoint:
                print(f"  Has optimizer state: Yes")
        else:
            state_dict = checkpoint
            print("✓ Checkpoint is direct state_dict")
        
        # Get sample keys
        keys = list(state_dict.keys())
        print(f"\nTotal parameters: {len(keys)}")
        print("\nFirst 10 keys:")
        for key in keys[:10]:
            print(f"  - {key}")
        
        print("\nLast 10 keys:")
        for key in keys[-10:]:
            print(f"  - {key}")
        
        # Detect architecture
        print("\n" + "="*70)
        print("ARCHITECTURE DETECTION")
        print("="*70)
        
        if any('backbone.features' in k for k in keys):
            print("✓ Detected: ConvNeXt (timm-based model)")
            print("  Keys contain: backbone.features.*")
            print("  Keys contain: backbone.classifier.*")
            return "convnext"
        elif any('conv1.weight' in k for k in keys):
            print("✓ Detected: ResNet")
            print("  Keys contain: conv1, bn1, layer1-4, fc")
            return "resnet"
        elif any('features.' in k and not 'backbone' in k for k in keys):
            print("✓ Detected: DenseNet or VGG")
            return "densenet_or_vgg"
        else:
            print("❓ Unknown architecture")
            print("  Please check the keys above to identify the model")
            return "unknown"
            
    except Exception as e:
        print(f"❌ Error loading model: {e}")
        return None

if __name__ == "__main__":
    import sys
    
    if len(sys.argv) > 1:
        model_path = sys.argv[1]
    else:
        # Default path
        script_dir = os.path.dirname(os.path.abspath(__file__))
        model_path = os.path.join(script_dir, "best_model.pth")
    
    result = inspect_model(model_path)
    
    if result == "convnext":
        print("\n" + "="*70)
        print("NEXT STEPS")
        print("="*70)
        print("Your model uses ConvNeXt architecture.")
        print("I'll create updated predict.py and learn.py scripts for you.")