package agent

import (
	"encoding/binary"
	"os"
	"path/filepath"
	"testing"
)

func TestLocalRecorderWritesPlayableWavHeaderAndData(t *testing.T) {
	dir := t.TempDir()

	recorder, err := NewLocalRecorder(dir, "/storage/voice-agent-recordings", "CA test/123")
	if err != nil {
		t.Fatalf("NewLocalRecorder returned error: %v", err)
	}

	if err := recorder.WriteMuLawTrackAt(recordingTrackCaller, []byte{0x00}, 0); err != nil {
		t.Fatalf("WriteMuLawTrackAt caller returned error: %v", err)
	}
	if err := recorder.WriteMuLawTrackAt(recordingTrackAssistant, []byte{0x80}, 0); err != nil {
		t.Fatalf("WriteMuLawTrackAt assistant returned error: %v", err)
	}
	if err := recorder.Close(); err != nil {
		t.Fatalf("Close returned error: %v", err)
	}

	body, err := os.ReadFile(recorder.Path())
	if err != nil {
		t.Fatalf("read recording: %v", err)
	}

	if len(body) != 44+4 {
		t.Fatalf("expected 48 bytes, got %d", len(body))
	}
	if string(body[0:4]) != "RIFF" || string(body[8:12]) != "WAVE" || string(body[36:40]) != "data" {
		t.Fatalf("expected WAV header, got %q %q %q", body[0:4], body[8:12], body[36:40])
	}
	if got := binary.LittleEndian.Uint16(body[22:24]); got != wavChannels {
		t.Fatalf("expected %d channels, got %d", wavChannels, got)
	}
	if got := binary.LittleEndian.Uint32(body[24:28]); got != wavSampleRate {
		t.Fatalf("expected sample rate %d, got %d", wavSampleRate, got)
	}
	if got := binary.LittleEndian.Uint32(body[28:32]); got != 32000 {
		t.Fatalf("expected byte rate 32000, got %d", got)
	}
	if got := binary.LittleEndian.Uint16(body[32:34]); got != 4 {
		t.Fatalf("expected block align 4, got %d", got)
	}
	if got := binary.LittleEndian.Uint16(body[34:36]); got != wavBitsPerSample {
		t.Fatalf("expected %d bits per sample, got %d", wavBitsPerSample, got)
	}
	if got := binary.LittleEndian.Uint32(body[40:44]); got != 4 {
		t.Fatalf("expected data chunk size 4, got %d", got)
	}
	left := int16(binary.LittleEndian.Uint16(body[44:46]))
	right := int16(binary.LittleEndian.Uint16(body[46:48]))
	if left >= 0 {
		t.Fatalf("expected caller audio on left channel, got %d", left)
	}
	if right <= 0 {
		t.Fatalf("expected assistant audio on right channel, got %d", right)
	}
	if filepath.Base(recorder.Path()) == "" {
		t.Fatal("expected recording path to include a filename")
	}
	if recorder.PublicURL() == "" {
		t.Fatal("expected public URL")
	}
}

func TestLocalRecorderPadsTimestampedCallerAudio(t *testing.T) {
	dir := t.TempDir()

	recorder, err := NewLocalRecorder(dir, "/storage/voice-agent-recordings", "CA test/456")
	if err != nil {
		t.Fatalf("NewLocalRecorder returned error: %v", err)
	}

	if err := recorder.WriteMuLawTrackAt(recordingTrackCaller, []byte{0x00}, 20); err != nil {
		t.Fatalf("WriteMuLawTrackAt returned error: %v", err)
	}
	if err := recorder.Close(); err != nil {
		t.Fatalf("Close returned error: %v", err)
	}

	body, err := os.ReadFile(recorder.Path())
	if err != nil {
		t.Fatalf("read recording: %v", err)
	}

	expectedFrames := 20*wavSampleRate/1000 + 1
	expectedDataBytes := uint32(expectedFrames * wavChannels * (wavBitsPerSample / 8))
	if got := binary.LittleEndian.Uint32(body[40:44]); got != expectedDataBytes {
		t.Fatalf("expected data chunk size %d, got %d", expectedDataBytes, got)
	}
	if left := int16(binary.LittleEndian.Uint16(body[44:46])); left != 0 {
		t.Fatalf("expected silence before timestamped caller audio, got %d", left)
	}

	lastFrameOffset := 44 + (expectedFrames-1)*wavChannels*(wavBitsPerSample/8)
	left := int16(binary.LittleEndian.Uint16(body[lastFrameOffset : lastFrameOffset+2]))
	right := int16(binary.LittleEndian.Uint16(body[lastFrameOffset+2 : lastFrameOffset+4]))
	if left >= 0 {
		t.Fatalf("expected timestamped caller audio on left channel, got %d", left)
	}
	if right != 0 {
		t.Fatalf("expected no assistant audio on right channel, got %d", right)
	}
}
