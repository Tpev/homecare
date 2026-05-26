package agent

import (
	"crypto/hmac"
	"crypto/sha1"
	"encoding/base64"
	"io"
	"log"
	"net/http"
	"net/http/httptest"
	"net/url"
	"sort"
	"strings"
	"testing"

	"voice-agent/internal/config"
)

func TestTwilioVoiceUsesForwardedHostWhenConfiguredBaseIsStale(t *testing.T) {
	server := testServer(config.Config{
		Port:                   "8088",
		PublicBaseURL:          "https://homecare.hub.healthcare",
		TwilioAuthToken:        "twilio-secret",
		TwilioVoiceWebhookPath: "/twilio/voice",
		TwilioStreamPath:       "/ws/twilio",
		StreamAuthToken:        "bridge-secret",
	})

	form := url.Values{
		"CallSid": {"CA123"},
		"From":    {"+15551234567"},
	}
	req := httptest.NewRequest(http.MethodPost, "http://127.0.0.1/twilio/voice", strings.NewReader(form.Encode()))
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	req.Header.Set("X-Forwarded-Proto", "https")
	req.Header.Set("X-Forwarded-Host", "carelolo.com")
	req.Header.Set("X-Twilio-Signature", twilioSignature("twilio-secret", "https://carelolo.com/twilio/voice", form))

	res := httptest.NewRecorder()
	server.handleTwilioVoice(res, req)

	if res.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d: %s", http.StatusOK, res.Code, res.Body.String())
	}

	body := res.Body.String()
	if !strings.Contains(body, `url="wss://carelolo.com/ws/twilio"`) {
		t.Fatalf("expected stream URL to use forwarded host, got:\n%s", body)
	}
	if strings.Contains(body, "homecare.hub.healthcare") {
		t.Fatalf("expected stale configured domain not to appear in TwiML, got:\n%s", body)
	}
}

func TestTwilioVoiceUsesForwardedProtoWithRequestHostWhenForwardedHostIsMissing(t *testing.T) {
	server := testServer(config.Config{
		Port:                   "8088",
		PublicBaseURL:          "https://homecare.hub.healthcare",
		TwilioAuthToken:        "twilio-secret",
		TwilioVoiceWebhookPath: "/twilio/voice",
		TwilioStreamPath:       "/ws/twilio",
		StreamAuthToken:        "bridge-secret",
	})

	form := url.Values{
		"CallSid": {"CA123"},
		"From":    {"+15551234567"},
	}
	req := httptest.NewRequest(http.MethodPost, "http://carelolo.com/twilio/voice", strings.NewReader(form.Encode()))
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	req.Header.Set("X-Forwarded-Proto", "https")
	req.Header.Set("X-Twilio-Signature", twilioSignature("twilio-secret", "https://carelolo.com/twilio/voice", form))

	res := httptest.NewRecorder()
	server.handleTwilioVoice(res, req)

	if res.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d: %s", http.StatusOK, res.Code, res.Body.String())
	}

	body := res.Body.String()
	if !strings.Contains(body, `url="wss://carelolo.com/ws/twilio"`) {
		t.Fatalf("expected stream URL to use request host with forwarded proto, got:\n%s", body)
	}
}

func TestTwilioVoiceStillAcceptsConfiguredPublicBaseURL(t *testing.T) {
	server := testServer(config.Config{
		Port:                   "8088",
		PublicBaseURL:          "https://voice.carelolo.com",
		TwilioAuthToken:        "twilio-secret",
		TwilioVoiceWebhookPath: "/twilio/voice",
		TwilioStreamPath:       "/ws/twilio",
		StreamAuthToken:        "bridge-secret",
	})

	form := url.Values{
		"CallSid": {"CA123"},
		"From":    {"+15551234567"},
	}
	req := httptest.NewRequest(http.MethodPost, "http://localhost:8088/twilio/voice", strings.NewReader(form.Encode()))
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	req.Header.Set("X-Twilio-Signature", twilioSignature("twilio-secret", "https://voice.carelolo.com/twilio/voice", form))

	res := httptest.NewRecorder()
	server.handleTwilioVoice(res, req)

	if res.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d: %s", http.StatusOK, res.Code, res.Body.String())
	}

	body := res.Body.String()
	if !strings.Contains(body, `url="wss://voice.carelolo.com/ws/twilio"`) {
		t.Fatalf("expected stream URL to use configured public base URL, got:\n%s", body)
	}
}

func testServer(cfg config.Config) *Server {
	return NewServer(cfg, log.New(io.Discard, "", 0), nil, nil)
}

func twilioSignature(authToken, requestURL string, form url.Values) string {
	base := requestURL
	keys := make([]string, 0, len(form))
	for key := range form {
		keys = append(keys, key)
	}
	sort.Strings(keys)

	for _, key := range keys {
		values := append([]string(nil), form[key]...)
		sort.Strings(values)
		for _, value := range values {
			base += key + value
		}
	}

	mac := hmac.New(sha1.New, []byte(authToken))
	mac.Write([]byte(base))

	return base64.StdEncoding.EncodeToString(mac.Sum(nil))
}
