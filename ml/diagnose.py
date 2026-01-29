#!/usr/bin/env python3
"""
Diagnostic script for Dog Breed Recognition System
Run this to check if everything is set up correctly
"""

import os
import sys
import json

def check_file(path, description, required=True):
    """Check if a file exists"""
    exists = os.path.exists(path)
    status = "✓" if exists else ("✗ REQUIRED" if required else "○ Optional")
    
    print(f"{status} {description}")
    print(f"   Path: {path}")
    
    if exists:
        size = os.path.getsize(path)
        print(f"   Size: {size:,} bytes")
    else:
        if required:
            print(f"   ERROR: File not found!")
    
    print()
    return exists

def check_python_packages():
    """Check if required Python packages are installed"""
    packages = {
        'torch': 'PyTorch',
        'torchvision': 'TorchVision',
        'PIL': 'Pillow',
        'numpy': 'NumPy'
    }
    
    print("=" * 60)
    print("PYTHON PACKAGES")
    print("=" * 60)
    
    all_installed = True
    for module, name in packages.items():
        try:
            __import__(module)
            print(f"✓ {name} is installed")
        except ImportError:
            print(f"✗ {name} is NOT installed")
            all_installed = False
    
    print()
    return all_installed

def test_model_loading():
    """Try to load the model"""
    print("=" * 60)
    print("MODEL LOADING TEST")
    print("=" * 60)
    
    try:
        import torch
        import torchvision.models as models
        import torch.nn as nn
        
        model_path = os.path.join(os.path.dirname(__file__), "best_model.pth")
        
        if not os.path.exists(model_path):
            print(f"✗ Model file not found: {model_path}")
            return False
        
        print(f"Attempting to load: {model_path}")
        
        # Load model architecture
        model = models.resnet50(weights=None)
        num_ftrs = model.fc.in_features
        model.fc = nn.Linear(num_ftrs, 120)
        
        # Load weights
        checkpoint = torch.load(model_path, map_location=torch.device('cpu'))
        
        if 'state_dict' in checkpoint:
            model.load_state_dict(checkpoint['state_dict'])
            print("✓ Model loaded successfully (checkpoint with state_dict)")
        else:
            model.load_state_dict(checkpoint)
            print("✓ Model loaded successfully (direct state_dict)")
        
        model.eval()
        print("✓ Model set to evaluation mode")
        return True
        
    except Exception as e:
        print(f"✗ Error loading model: {str(e)}")
        return False

def check_references_json(json_path):
    """Check references.json file"""
    print("=" * 60)
    print("REFERENCES FILE (Memory Library)")
    print("=" * 60)
    
    if os.path.exists(json_path):
        try:
            with open(json_path, 'r') as f:
                data = json.load(f)
            
            if isinstance(data, list):
                print(f"✓ References file exists and is valid")
                print(f"  Total references: {len(data)}")
                
                if len(data) > 0:
                    print(f"\n  Sample reference:")
                    sample = data[0]
                    print(f"    Label: {sample.get('label', 'N/A')}")
                    print(f"    Embedding length: {len(sample.get('embedding', []))}")
                    print(f"    Source: {sample.get('source_image', 'N/A')}")
            else:
                print(f"✗ References file exists but is not a list")
                
        except Exception as e:
            print(f"✗ Error reading references file: {str(e)}")
    else:
        print(f"○ References file does not exist yet (will be created)")
        print(f"  Path: {json_path}")
    
    print()

def main():
    print("\n" + "=" * 60)
    print("DOG BREED RECOGNITION - SYSTEM DIAGNOSTIC")
    print("=" * 60)
    print()
    
    # Determine script location
    script_dir = os.path.dirname(os.path.abspath(__file__))
    print(f"Script directory: {script_dir}")
    print()
    
    # Check required files
    print("=" * 60)
    print("REQUIRED FILES")
    print("=" * 60)
    
    model_path = os.path.join(script_dir, "best_model.pth")
    classes_path = os.path.join(script_dir, "classes.txt")
    predict_path = os.path.join(script_dir, "predict.py")
    learn_path = os.path.join(script_dir, "learn.py")
    
    files_ok = True
    files_ok &= check_file(model_path, "Model file (best_model.pth)", required=True)
    files_ok &= check_file(classes_path, "Classes file (classes.txt)", required=True)
    files_ok &= check_file(predict_path, "Prediction script (predict.py)", required=True)
    files_ok &= check_file(learn_path, "Learning script (learn.py)", required=True)
    
    # Check Python packages
    packages_ok = check_python_packages()
    
    # Test model loading
    if files_ok and packages_ok:
        model_ok = test_model_loading()
    else:
        print("\nSkipping model loading test due to missing dependencies\n")
        model_ok = False
    
    # Check references
    # Assume Laravel storage structure
    project_root = os.path.dirname(script_dir)  # Go up from ml/ to project root
    storage_path = os.path.join(project_root, "storage", "app", "references.json")
    check_references_json(storage_path)
    
    # Final summary
    print("=" * 60)
    print("DIAGNOSTIC SUMMARY")
    print("=" * 60)
    
    if files_ok and packages_ok and model_ok:
        print("✓ System appears to be configured correctly!")
        print("\nYou should be able to:")
        print("  1. Run predictions with predict.py")
        print("  2. Add corrections with learn.py")
        print("  3. Use the Laravel application")
    else:
        print("✗ Issues found - please fix the errors above")
        print("\nCommon fixes:")
        print("  1. Ensure best_model.pth is in the ml/ directory")
        print("  2. Install missing Python packages: pip install torch torchvision pillow numpy")
        print("  3. Verify classes.txt has 120 breed names")
    
    print()

if __name__ == "__main__":
    main()