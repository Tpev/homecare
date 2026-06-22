package agent

import (
	"encoding/binary"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"sync"
	"time"
)

const wavSampleRate = 8000

type LocalRecorder struct {
	mu        sync.Mutex
	file      *os.File
	path      string
	publicURL string
	dataBytes uint32
	closed    bool
}

func NewLocalRecorder(dir, publicBaseURL, identifier string) (*LocalRecorder, error) {
	dir = strings.TrimSpace(dir)
	if dir == "" {
		return nil, fmt.Errorf("missing recording directory")
	}

	if err := os.MkdirAll(dir, 0o755); err != nil {
		return nil, err
	}

	filename := fmt.Sprintf(
		"%s-%s.wav",
		safeRecordingID(identifier),
		time.Now().UTC().Format("20060102T150405Z"),
	)
	path := filepath.Join(dir, filename)

	file, err := os.OpenFile(path, os.O_CREATE|os.O_EXCL|os.O_WRONLY, 0o644)
	if err != nil {
		return nil, err
	}

	recorder := &LocalRecorder{
		file:      file,
		path:      path,
		publicURL: joinPublicURL(publicBaseURL, filename),
	}

	if err := recorder.writeHeader(0); err != nil {
		file.Close()
		return nil, err
	}

	return recorder, nil
}

func (r *LocalRecorder) WriteMuLaw(payload []byte) error {
	if r == nil || len(payload) == 0 {
		return nil
	}

	r.mu.Lock()
	defer r.mu.Unlock()

	if r.closed {
		return nil
	}

	pcm := make([]byte, len(payload)*2)
	for i, sample := range payload {
		binary.LittleEndian.PutUint16(pcm[i*2:], uint16(muLawToLinear(sample)))
	}

	if _, err := r.file.Write(pcm); err != nil {
		return err
	}

	r.dataBytes += uint32(len(pcm))

	return nil
}

func (r *LocalRecorder) Close() error {
	if r == nil {
		return nil
	}

	r.mu.Lock()
	defer r.mu.Unlock()

	if r.closed {
		return nil
	}
	r.closed = true

	if _, err := r.file.Seek(0, 0); err != nil {
		_ = r.file.Close()
		return err
	}
	if err := r.writeHeader(r.dataBytes); err != nil {
		_ = r.file.Close()
		return err
	}

	return r.file.Close()
}

func (r *LocalRecorder) Path() string {
	if r == nil {
		return ""
	}

	return r.path
}

func (r *LocalRecorder) PublicURL() string {
	if r == nil {
		return ""
	}

	return r.publicURL
}

func (r *LocalRecorder) writeHeader(dataBytes uint32) error {
	byteRate := uint32(wavSampleRate * 2)
	blockAlign := uint16(2)

	header := make([]byte, 44)
	copy(header[0:4], "RIFF")
	binary.LittleEndian.PutUint32(header[4:8], 36+dataBytes)
	copy(header[8:12], "WAVE")
	copy(header[12:16], "fmt ")
	binary.LittleEndian.PutUint32(header[16:20], 16)
	binary.LittleEndian.PutUint16(header[20:22], 1)
	binary.LittleEndian.PutUint16(header[22:24], 1)
	binary.LittleEndian.PutUint32(header[24:28], wavSampleRate)
	binary.LittleEndian.PutUint32(header[28:32], byteRate)
	binary.LittleEndian.PutUint16(header[32:34], blockAlign)
	binary.LittleEndian.PutUint16(header[34:36], 16)
	copy(header[36:40], "data")
	binary.LittleEndian.PutUint32(header[40:44], dataBytes)

	_, err := r.file.Write(header)

	return err
}

func muLawToLinear(value byte) int16 {
	value = ^value
	sign := value & 0x80
	exponent := (value >> 4) & 0x07
	mantissa := value & 0x0F
	sample := int(((mantissa << 3) + 0x84) << exponent)
	sample -= 0x84

	if sign != 0 {
		return int16(-sample)
	}

	return int16(sample)
}

func safeRecordingID(identifier string) string {
	identifier = strings.TrimSpace(identifier)
	if identifier == "" {
		identifier = "voice-call"
	}

	re := regexp.MustCompile(`[^a-zA-Z0-9_-]+`)
	identifier = strings.Trim(re.ReplaceAllString(identifier, "-"), "-")
	if identifier == "" {
		return "voice-call"
	}

	return strings.ToLower(identifier)
}

func joinPublicURL(base, filename string) string {
	base = strings.TrimSpace(base)
	if base == "" {
		return ""
	}

	return strings.TrimRight(base, "/") + "/" + filename
}
