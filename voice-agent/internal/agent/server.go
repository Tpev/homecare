package agent

import (
	"encoding/xml"
	"fmt"
	"log"
	"net/http"
	"net/url"
	"strings"

	"github.com/gorilla/websocket"

	"voice-agent/internal/config"
	"voice-agent/internal/laravel"
	"voice-agent/internal/twilio"
)

type Server struct {
	cfg           config.Config
	logger        *log.Logger
	promptBuilder *PromptBuilder
	laravel       *laravel.Client
	upgrader      websocket.Upgrader
}

type voiceResponse struct {
	XMLName xml.Name `xml:"Response"`
	Say     say      `xml:"Say"`
	Connect connect  `xml:"Connect"`
}

type say struct {
	Language string `xml:"language,attr,omitempty"`
	Text     string `xml:",chardata"`
}

type connect struct {
	Stream stream `xml:"Stream"`
}

type stream struct {
	URL       string      `xml:"url,attr"`
	Parameter []parameter `xml:"Parameter"`
}

type parameter struct {
	Name  string `xml:"name,attr"`
	Value string `xml:"value,attr"`
}

func NewServer(cfg config.Config, logger *log.Logger, promptBuilder *PromptBuilder, laravelClient *laravel.Client) *Server {
	return &Server{
		cfg:           cfg,
		logger:        logger,
		promptBuilder: promptBuilder,
		laravel:       laravelClient,
		upgrader: websocket.Upgrader{
			CheckOrigin: func(_ *http.Request) bool { return true },
		},
	}
}

func (s *Server) Routes() http.Handler {
	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", s.healthz)
	mux.HandleFunc(s.cfg.TwilioVoiceWebhookPath, s.handleTwilioVoice)
	mux.HandleFunc(s.cfg.TwilioStreamPath, s.handleTwilioStream)

	return mux
}

func (s *Server) healthz(w http.ResponseWriter, _ *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write([]byte(`{"ok":true}`))
}

func (s *Server) handleTwilioVoice(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		http.Error(w, "invalid form body", http.StatusBadRequest)
		return
	}

	if !twilio.ValidateSignature(
		s.cfg.TwilioAuthToken,
		s.twilioWebhookURL(r),
		r.PostForm,
		r.Header.Get("X-Twilio-Signature"),
	) {
		http.Error(w, "invalid twilio signature", http.StatusUnauthorized)
		return
	}

	callSID := r.PostForm.Get("CallSid")
	from := r.PostForm.Get("From")

	payload := voiceResponse{
		Say: say{
			Language: "en-US",
			Text:     "This call may be monitored or recorded.",
		},
		Connect: connect{
			Stream: stream{
				URL: s.streamURL(),
				Parameter: []parameter{
					{Name: "bridge_token", Value: s.cfg.StreamAuthToken},
					{Name: "call_sid", Value: callSID},
					{Name: "from", Value: from},
				},
			},
		},
	}

	body, err := xml.MarshalIndent(payload, "", "  ")
	if err != nil {
		http.Error(w, "failed to build twiml", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/xml")
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write([]byte(xml.Header))
	_, _ = w.Write(body)
}

func (s *Server) handleTwilioStream(w http.ResponseWriter, r *http.Request) {
	conn, err := s.upgrader.Upgrade(w, r, nil)
	if err != nil {
		s.logger.Printf("twilio websocket upgrade failed: %v", err)
		return
	}
	defer conn.Close()

	session := NewSession(s.cfg, s.logger, s.laravel, s.promptBuilder, conn)
	if err := session.Run(r.Context()); err != nil {
		s.logger.Printf("session ended with error: %v", err)
	}
}

func (s *Server) twilioWebhookURL(r *http.Request) string {
	if s.cfg.PublicBaseURL != "" {
		return s.cfg.PublicBaseURL + s.cfg.TwilioVoiceWebhookPath
	}

	scheme := "https"
	if r.TLS == nil {
		scheme = "http"
	}

	return (&url.URL{
		Scheme: scheme,
		Host:   r.Host,
		Path:   r.URL.Path,
	}).String()
}

func (s *Server) streamURL() string {
	base := s.cfg.PublicBaseURL
	if base == "" {
		base = fmt.Sprintf("http://localhost:%s", s.cfg.Port)
	}

	if strings.HasPrefix(base, "https://") {
		base = "wss://" + strings.TrimPrefix(base, "https://")
	} else if strings.HasPrefix(base, "http://") {
		base = "ws://" + strings.TrimPrefix(base, "http://")
	}

	return strings.TrimRight(base, "/") + s.cfg.TwilioStreamPath
}
