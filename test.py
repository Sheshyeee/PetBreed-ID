from google import genai
import os

# 1. Setup your API Key
# PASTE YOUR NEW KEY BELOW
API_KEY = "AIzaSyCBvbC0_wNN_IrDmFFPNgP7ly2T4S2bwoI" 

client = genai.Client(api_key=API_KEY)

try:
    # 2. Use the NEW model name (gemini-2.0-flash)
    # The new SDK prefers "gemini-2.0-flash" over the old 1.5 versions
    response = client.models.generate_content(
        model="gemini-2.0-flash-lite", 
        contents="Explain how AI works in one sentence."
    )
    
    print("\n✅ SUCCESS! The API Key is valid.\n")
    print(response.text)

except Exception as e:
    print(f"\n❌ ERROR: {e}")
    
    # If 2.0 fails, let's list what models YOUR key can actually see
    print("\nAttempting to list available models for your key...")
    try:
        for m in client.models.list():
            print(f"- {m.name}")
    except:
        print("Could not list models.")