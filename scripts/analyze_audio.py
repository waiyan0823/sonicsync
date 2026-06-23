#!/usr/bin/env python3
import json
import math
import os
import sys
import tempfile
import urllib.parse
import urllib.request


def fail(message):
    print(json.dumps({"ok": False, "error": message}))
    return 1


def classify(features):
    tempo = features["tempo_bpm"]
    rms = features["rms_energy"]
    centroid = features["spectral_centroid"]
    zcr = features["zero_crossing_rate"]

    if tempo < 90:
        tempo_category = "Slow"
    elif tempo <= 130:
        tempo_category = "Medium"
    else:
        tempo_category = "Fast"

    if rms < 0.035:
        energy_level = "Low"
    elif rms < 0.09:
        energy_level = "Medium"
    else:
        energy_level = "High"

    if energy_level == "High" and tempo_category == "Fast":
        mood = "Energetic"
    elif energy_level == "Low" and tempo_category == "Slow":
        mood = "Calm"
    elif centroid < 1800 and zcr < 0.07:
        mood = "Reflective"
    elif centroid > 3000 and energy_level != "Low":
        mood = "Happy"
    else:
        mood = "Balanced"

    if centroid < 1500 and energy_level == "Low":
        genre = "Acoustic"
    elif centroid < 2200 and tempo_category != "Fast":
        genre = "Lofi"
    elif zcr > 0.12 and tempo_category == "Fast":
        genre = "Rock"
    elif energy_level == "High" and tempo_category == "Fast":
        genre = "EDM"
    elif centroid < 1800:
        genre = "Instrumental"
    else:
        genre = "Pop"

    if energy_level == "Low" or mood in ("Calm", "Reflective") or tempo_category == "Slow":
        tendency = "Reflective Introvert"
    else:
        tendency = "Expressive Extrovert"

    return {
        "estimated_genre": genre,
        "estimated_mood": mood,
        "tempo_category": tempo_category,
        "energy_level": energy_level,
        "personality_tendency": tendency,
    }


def source_to_path(source):
    if source.startswith(("http://", "https://")):
        parsed = urllib.parse.urlparse(source)
        safe_path = urllib.parse.quote(parsed.path, safe="/%")
        safe_query = urllib.parse.quote_plus(parsed.query, safe="=&%")
        source = urllib.parse.urlunparse(
            (parsed.scheme, parsed.netloc, safe_path, parsed.params, safe_query, parsed.fragment)
        )
        suffix = os.path.splitext(parsed.path)[1] or ".audio"
        handle = tempfile.NamedTemporaryFile(delete=False, suffix=suffix)
        handle.close()
        request = urllib.request.Request(
            source,
            headers={
                "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
                "(KHTML, like Gecko) Chrome/125.0 Safari/537.36 SonicSync/1.0",
                "Accept": "audio/*,*/*;q=0.8",
                "Referer": f"{parsed.scheme}://{parsed.netloc}/",
            },
        )
        with urllib.request.urlopen(request, timeout=60) as response:
            with open(handle.name, "wb") as output:
                output.write(response.read())
        return handle.name, True

    return source, False


def main():
    if len(sys.argv) < 2:
        return fail("No audio file path or URL was provided.")

    try:
        import librosa
        import numpy as np
    except Exception as exc:
        return fail(
            "Python audio packages are missing. Install librosa and numpy for content-based audio analysis. "
            + str(exc)
        )

    source = sys.argv[1]
    temp_file = False
    audio_path = source

    try:
        audio_path, temp_file = source_to_path(source)
        if not os.path.isfile(audio_path):
            return fail("Audio file was not found: " + source)

        y, sr = librosa.load(audio_path, sr=None, mono=True)
        if y.size == 0:
            return fail("Audio file contains no readable samples.")

        tempo_result = librosa.beat.beat_track(y=y, sr=sr)
        tempo = tempo_result[0]
        if isinstance(tempo, np.ndarray):
            tempo = float(tempo.flatten()[0]) if tempo.size else 0.0
        tempo = float(tempo)

        rms = float(np.mean(librosa.feature.rms(y=y)))
        centroid = float(np.mean(librosa.feature.spectral_centroid(y=y, sr=sr)))
        zcr = float(np.mean(librosa.feature.zero_crossing_rate(y)))
        duration = float(librosa.get_duration(y=y, sr=sr))

        if not math.isfinite(tempo) or tempo <= 0:
            tempo = 0.0

        features = {
            "tempo_bpm": round(tempo, 2),
            "rms_energy": round(rms, 6),
            "spectral_centroid": round(centroid, 2),
            "zero_crossing_rate": round(zcr, 6),
            "duration_seconds": round(duration, 2),
        }
        features.update(classify(features))
        features["ok"] = True
        print(json.dumps(features))
        return 0
    except Exception as exc:
        return fail("Audio analysis failed. " + str(exc))
    finally:
        if temp_file and os.path.exists(audio_path):
            os.unlink(audio_path)


if __name__ == "__main__":
    sys.exit(main())
