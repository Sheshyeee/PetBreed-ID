import base64
import sys
from openai import OpenAI

# 🔑 PUT YOUR NEW API KEY HERE
API_KEY = ""

client = OpenAI(api_key=API_KEY)


def encode_image(path: str) -> str:
    """Convert image file to base64 data URL."""
    with open(path, "rb") as f:
        b64 = base64.b64encode(f.read()).decode("utf-8")
    return f"data:image/jpeg;base64,{b64}"


def extract_text(resp) -> str:
    """
    Safely extract text from OpenAI response.
    Prevents blank output issue.
    """
    # Normal shortcut
    if hasattr(resp, "output_text") and resp.output_text:
        return resp.output_text.strip()

    # Fallback manual extraction
    try:
        return resp.output[0].content[0].text.strip()
    except Exception:
        return "Unknown"


def identify_dog_breed(image_path: str) -> str:
    """Send image to GPT-5.2-Pro for breed identification."""
    image_data = encode_image(image_path)

    response = client.responses.create(
        model="gpt-5.2-pro",
        input=[{
            "role": "user",
            "content": [
                {
                    "type": "input_text",
                    "text": (
    "Identify the dog breed using professional canine morphology analysis "
    "(ears, skull shape, coat texture, markings, tail carriage, body proportion). "
    "Return ONLY one concise breed label. "
    "If mixed, return '<dominant breed> mix'. "
    "If uncertain, return the closest probable breed, not 'Unknown'. "
    "If no dog is present, return 'No dog detected'."
),

                },
                {
                    "type": "input_image",
                    "image_url": image_data,
                },
            ],
        }],
        max_output_tokens=50,
    )

    return extract_text(response)


if __name__ == "__main__":
    # Check command argument
    if len(sys.argv) < 2:
        print("Usage: python testgpt.py <image_path>")
        sys.exit(1)

    image_path = sys.argv[1]

    try:
        print("🔍 Identifying dog breed...")
        breed = identify_dog_breed(image_path)
        print(f"🐶 Breed: {breed}")
    except FileNotFoundError:
        print("❌ Image file not found.")
    except Exception as e:
        print("❌ Error:", e)
