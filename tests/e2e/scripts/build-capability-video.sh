#!/usr/bin/env bash
# Builds a narrated capability MP4 for docs/02_Capabilities:
#   1. records the walkthrough (Playwright tour → webm)   — run separately: npx playwright test --project=tour
#   2. voices the narration script with macOS `say`        — this script
#   3. muxes voice over the recording with ffmpeg          — this script
#
# Usage: scripts/build-capability-video.sh <slug> <narration.txt>
# Requires: macOS `say`, ffmpeg. The webm is taken from tour-recordings/**/video.webm.
set -euo pipefail
cd "$(dirname "$0")/.."   # tests/e2e

SLUG="${1:-comparison-capability}"
NARR="${2:-scripts/narration/comparison-capability.txt}"
VOICE="${VOICE:-Samantha}"
RATE="${RATE:-178}"
OUTDIR="../../docs/02_Capabilities"
TMP="tour-recordings/_build"
mkdir -p "$OUTDIR" "$TMP"

WEBM="$(find tour-recordings -name 'video.webm' | head -1)"
[ -n "$WEBM" ] || { echo "No recording found — run: npx playwright test --project=tour --grep 'capability-walkthrough render'"; exit 1; }

echo "voice: $VOICE @${RATE}wpm  ·  narration: $NARR  ·  recording: $WEBM"
say -v "$VOICE" -r "$RATE" -o "$TMP/voice.aiff" -f "$NARR"
ffmpeg -y -v error -i "$TMP/voice.aiff" "$TMP/voice.m4a"

AUD=$(ffprobe -v error -show_entries format=duration -of default=nw=1:nk=1 "$TMP/voice.m4a")
echo "narration duration: ${AUD}s"

# Hold the last video frame for the whole narration length so the picture never ends before the voice;
# -shortest then trims the (over-padded) video back to the narration end. Mux AAC; standardize to mp4.
ffmpeg -y -v error -i "$WEBM" -i "$TMP/voice.m4a" \
  -filter_complex "[0:v]tpad=stop_mode=clone:stop_duration=${AUD},fps=30,format=yuv420p[v]" \
  -map "[v]" -map "1:a" -c:v libx264 -c:a aac -shortest "$OUTDIR/$SLUG.mp4"

echo "wrote $OUTDIR/$SLUG.mp4"
ffprobe -v error -show_entries format=duration -of default=nw=1:nk=1 "$OUTDIR/$SLUG.mp4" | xargs echo "final duration(s):"
