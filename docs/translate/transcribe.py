#!/usr/bin/env python3
from pathlib import Path
import mlx_whisper

HERE = Path(__file__).parent
MODEL = "mlx-community/whisper-large-v3-mlx"
OUTPUT = HERE / "transcripts_ca.txt"

ogg_files = sorted(HERE.glob("*.ogg"))
print(f"Found {len(ogg_files)} .ogg files. Using model: {MODEL}\n")

with OUTPUT.open("w") as f:
    for i, ogg in enumerate(ogg_files, 1):
        print(f"[{i}/{len(ogg_files)}] {ogg.name}")
        result = mlx_whisper.transcribe(
            str(ogg),
            path_or_hf_repo=MODEL,
            language="ca",
        )
        f.write(f"=== {ogg.name} ===\n")
        f.write(result["text"].strip() + "\n\n")
        f.flush()

print(f"\nDone → {OUTPUT}")
