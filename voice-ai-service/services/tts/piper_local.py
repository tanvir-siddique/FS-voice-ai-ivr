"""
Piper Local TTS Provider.

Local TTS using Piper for zero-cost voice synthesis.
High quality neural voices running entirely offline.
"""

import os
import uuid
import subprocess
from pathlib import Path
from typing import Optional, List

from .base import BaseTTS, SynthesisResult, VoiceInfo
from config.settings import settings


class PiperLocalTTS(BaseTTS):
    """
    Piper local TTS provider.
    
    Config:
        model_path: Path to Piper ONNX model file
        config_path: Path to model config JSON (optional, auto-detected)
        speaker: Speaker ID for multi-speaker models (default: 0)
        length_scale: Speed factor (1.0 = normal, <1 faster, >1 slower)
        noise_scale: Noise/variability (default: 0.667)
        noise_w: Duration noise (default: 0.8)
        piper_binary: Path to piper binary (default: piper)
    """
    
    provider_name = "piper_local"
    
    def __init__(self, config: dict):
        super().__init__(config)
        self.model_path = config.get("model_path") or str(
            settings.PIPER_VOICE_DIR / "pt_BR-faber-medium.onnx"
        )
        self.config_path = config.get("config_path")
        self.speaker = config.get("speaker", 0)
        self.length_scale = config.get("length_scale", 1.0)
        self.noise_scale = config.get("noise_scale", 0.667)
        self.noise_w = config.get("noise_w", 0.8)
        self.piper_binary = config.get("piper_binary", "piper")
    
    async def synthesize(
        self,
        text: str,
        voice_id: Optional[str] = None,
        speed: float = 1.0,
        output_path: Optional[str] = None,
    ) -> SynthesisResult:
        """
        Synthesize text to speech using Piper.
        
        Args:
            text: Text to synthesize
            voice_id: Not used for Piper (uses model_path)
            speed: Speech speed (converted to length_scale)
            output_path: Path to save audio (optional)
            
        Returns:
            SynthesisResult with path to audio file
        """
        # Generate output path if not provided
        if not output_path:
            output_path = str(settings.TEMP_DIR / f"tts_{uuid.uuid4().hex}.wav")
        
        # Ensure output directory exists
        Path(output_path).parent.mkdir(parents=True, exist_ok=True)
        
        # Convert speed to length_scale (inverse relationship)
        # speed=2.0 -> length_scale=0.5 (faster)
        # speed=0.5 -> length_scale=2.0 (slower)
        length_scale = 1.0 / speed if speed > 0 else self.length_scale
        
        # Build piper command
        cmd = [
            self.piper_binary,
            "--model", self.model_path,
            "--output_file", output_path,
            "--length_scale", str(length_scale),
            "--noise_scale", str(self.noise_scale),
            "--noise_w", str(self.noise_w),
        ]
        
        # Add config path if specified
        if self.config_path:
            cmd.extend(["--config", self.config_path])
        
        # Add speaker for multi-speaker models
        if self.speaker:
            cmd.extend(["--speaker", str(self.speaker)])
        
        # Run piper with text input via stdin
        try:
            process = subprocess.run(
                cmd,
                input=text.encode("utf-8"),
                capture_output=True,
                timeout=30,
            )
            
            if process.returncode != 0:
                stderr = process.stderr.decode("utf-8", errors="ignore")
                raise RuntimeError(f"Piper failed: {stderr}")
                
        except subprocess.TimeoutExpired:
            raise RuntimeError("Piper timed out")
        except FileNotFoundError:
            raise RuntimeError(
                f"Piper binary not found: {self.piper_binary}. "
                "Install with: pip install piper-tts"
            )
        
        # Verify output exists
        if not os.path.exists(output_path):
            raise RuntimeError("Piper did not generate output file")
        
        # Get duration from WAV file
        duration_ms = self._get_wav_duration(output_path)
        
        return SynthesisResult(
            audio_file=output_path,
            duration_ms=duration_ms,
            format="wav",
        )
    
    def _get_wav_duration(self, wav_path: str) -> int:
        """Get WAV file duration in milliseconds."""
        try:
            import wave
            with wave.open(wav_path, 'rb') as wav:
                frames = wav.getnframes()
                rate = wav.getframerate()
                duration = frames / float(rate)
                return int(duration * 1000)
        except Exception:
            # Estimate from file size (16kHz, 16-bit mono = 32KB/sec)
            file_size = os.path.getsize(wav_path)
            return int((file_size / 32000) * 1000)
    
    async def list_voices(self, language: str = "pt-BR") -> List[VoiceInfo]:
        """
        List available Piper voices.
        
        Piper voices are model files - list available models in the voice directory.
        """
        voices = []
        
        # Scan voice directory for ONNX models
        voice_dir = settings.PIPER_VOICE_DIR
        if voice_dir.exists():
            for model_file in voice_dir.glob("*.onnx"):
                name = model_file.stem
                # Parse name pattern: lang_REGION-name-quality
                parts = name.split("-")
                lang = parts[0] if parts else "unknown"
                voice_name = parts[1] if len(parts) > 1 else name
                
                voices.append(VoiceInfo(
                    voice_id=str(model_file),
                    name=voice_name.capitalize(),
                    language=lang,
                    gender=None,
                ))
        
        # Add default if no voices found
        if not voices:
            voices.append(VoiceInfo(
                voice_id="pt_BR-faber-medium",
                name="Faber (Default PT-BR)",
                language="pt-BR",
                gender="male",
            ))
        
        return voices
    
    async def is_available(self) -> bool:
        """Check if Piper is available."""
        # Check if piper binary exists
        try:
            result = subprocess.run(
                [self.piper_binary, "--help"],
                capture_output=True,
                timeout=5,
            )
            binary_exists = result.returncode == 0
        except (FileNotFoundError, subprocess.TimeoutExpired):
            binary_exists = False
        
        # Check if model exists
        model_exists = os.path.exists(self.model_path)
        
        return binary_exists and model_exists
