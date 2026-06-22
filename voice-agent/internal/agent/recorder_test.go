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

	if err := recorder.WriteMuLaw([]byte{0xff, 0x7f, 0x00}); err != nil {
		t.Fatalf("WriteMuLaw returned error: %v", err)
	}
	if err := recorder.Close(); err != nil {
		t.Fatalf("Close returned error: %v", err)
	}

	body, err := os.ReadFile(recorder.Path())
	if err != nil {
		t.Fatalf("read recording: %v", err)
	}

	if len(body) != 44+6 {
		t.Fatalf("expected 50 bytes, got %d", len(body))
	}
	if string(body[0:4]) != "RIFF" || string(body[8:12]) != "WAVE" || string(body[36:40]) != "data" {
		t.Fatalf("expected WAV header, got %q %q %q", body[0:4], body[8:12], body[36:40])
	}
	if got := binary.LittleEndian.Uint32(body[40:44]); got != 6 {
		t.Fatalf("expected data chunk size 6, got %d", got)
	}
	if filepath.Base(recorder.Path()) == "" {
		t.Fatal("expected recording path to include a filename")
	}
	if recorder.PublicURL() == "" {
		t.Fatal("expected public URL")
	}
}
