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

const (
	wavSampleRate           = 8000
	wavChannels             = 2
	wavBitsPerSample        = 16
	recordingTrackCaller    = "caller"
	recordingTrackAssistant = "assistant"
)

type stereoFrame struct {
	left  int16
	right int16
}

type LocalRecorder struct {
	mu              sync.Mutex
	file            *os.File
	path            string
	publicURL       string
	closed          bool
	startedAt       time.Time
	frames          []stereoFrame
	callerCursor    int
	assistantCursor int
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
		startedAt: time.Now(),
	}

	if err := recorder.writeHeader(0); err != nil {
		file.Close()
		return nil, err
	}

	return recorder, nil
}

func (r *LocalRecorder) WriteMuLaw(payload []byte) error {
	return r.WriteMuLawTrack(recordingTrackCaller, payload)
}

func (r *LocalRecorder) WriteMuLawTrack(track string, payload []byte) error {
	return r.WriteMuLawTrackAt(track, payload, -1)
}

func (r *LocalRecorder) WriteMuLawTrackAt(track string, payload []byte, timestampMs int) error {
	if r == nil || len(payload) == 0 {
		return nil
	}

	r.mu.Lock()
	defer r.mu.Unlock()

	if r.closed {
		return nil
	}

	track = normalizeRecordingTrack(track)
	startFrame := r.startFrame(track, timestampMs)
	r.ensureFrames(startFrame + len(payload))

	for i, sample := range payload {
		frameIndex := startFrame + i
		linear := muLawToLinear(sample)
		frame := &r.frames[frameIndex]

		if track == recordingTrackAssistant {
			frame.right = mixInt16(frame.right, linear)
			continue
		}

		frame.left = mixInt16(frame.left, linear)
	}

	if track == recordingTrackAssistant {
		r.assistantCursor = max(r.assistantCursor, startFrame+len(payload))
	} else {
		r.callerCursor = max(r.callerCursor, startFrame+len(payload))
	}

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

	dataBytes := uint32(len(r.frames) * wavChannels * (wavBitsPerSample / 8))

	if err := r.file.Truncate(0); err != nil {
		_ = r.file.Close()
		return err
	}
	if _, err := r.file.Seek(0, 0); err != nil {
		_ = r.file.Close()
		return err
	}
	if err := r.writeHeader(dataBytes); err != nil {
		_ = r.file.Close()
		return err
	}
	if len(r.frames) > 0 {
		pcm := make([]byte, len(r.frames)*wavChannels*(wavBitsPerSample/8))
		for i, frame := range r.frames {
			offset := i * wavChannels * (wavBitsPerSample / 8)
			binary.LittleEndian.PutUint16(pcm[offset:], uint16(frame.left))
			binary.LittleEndian.PutUint16(pcm[offset+2:], uint16(frame.right))
		}
		if _, err := r.file.Write(pcm); err != nil {
			_ = r.file.Close()
			return err
		}
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
	byteRate := uint32(wavSampleRate * wavChannels * (wavBitsPerSample / 8))
	blockAlign := uint16(wavChannels * (wavBitsPerSample / 8))

	header := make([]byte, 44)
	copy(header[0:4], "RIFF")
	binary.LittleEndian.PutUint32(header[4:8], 36+dataBytes)
	copy(header[8:12], "WAVE")
	copy(header[12:16], "fmt ")
	binary.LittleEndian.PutUint32(header[16:20], 16)
	binary.LittleEndian.PutUint16(header[20:22], 1)
	binary.LittleEndian.PutUint16(header[22:24], wavChannels)
	binary.LittleEndian.PutUint32(header[24:28], wavSampleRate)
	binary.LittleEndian.PutUint32(header[28:32], byteRate)
	binary.LittleEndian.PutUint16(header[32:34], blockAlign)
	binary.LittleEndian.PutUint16(header[34:36], wavBitsPerSample)
	copy(header[36:40], "data")
	binary.LittleEndian.PutUint32(header[40:44], dataBytes)

	_, err := r.file.Write(header)

	return err
}

func (r *LocalRecorder) startFrame(track string, timestampMs int) int {
	if timestampMs >= 0 {
		return timestampMs * wavSampleRate / 1000
	}

	elapsedFrame := int(time.Since(r.startedAt) * wavSampleRate / time.Second)
	cursor := r.callerCursor
	if track == recordingTrackAssistant {
		cursor = r.assistantCursor
	}
	if elapsedFrame > cursor {
		return elapsedFrame
	}

	return cursor
}

func (r *LocalRecorder) ensureFrames(count int) {
	if count <= len(r.frames) {
		return
	}

	r.frames = append(r.frames, make([]stereoFrame, count-len(r.frames))...)
}

func normalizeRecordingTrack(track string) string {
	switch strings.ToLower(strings.TrimSpace(track)) {
	case recordingTrackAssistant, "outbound", "agent", "julie", "assistant_audio":
		return recordingTrackAssistant
	default:
		return recordingTrackCaller
	}
}

func mixInt16(existing, next int16) int16 {
	sum := int(existing) + int(next)
	if sum > 32767 {
		return 32767
	}
	if sum < -32768 {
		return -32768
	}

	return int16(sum)
}

func muLawToLinear(value byte) int16 {
	value = ^value
	sign := value & 0x80
	exponent := int((value >> 4) & 0x07)
	mantissa := int(value & 0x0F)
	sample := ((mantissa << 3) + 0x84) << exponent
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
