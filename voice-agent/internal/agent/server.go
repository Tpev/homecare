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
	cfg            config.Config
	logger         *log.Logger
	promptBuilders map[string]*PromptBuilder
	laravel        *laravel.Client
	upgrader       websocket.Upgrader
}

const (
	ProfileInbound           = "inbound"
	ProfileCallbackDiscovery = "callback_discovery"
)

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

func NewServer(cfg config.Config, logger *log.Logger, promptBuilders map[string]*PromptBuilder, laravelClient *laravel.Client) *Server {
	return &Server{
		cfg:            cfg,
		logger:         logger,
		promptBuilders: promptBuilders,
		laravel:        laravelClient,
		upgrader: websocket.Upgrader{
			CheckOrigin: func(_ *http.Request) bool { return true },
		},
	}
}

func (s *Server) Routes() http.Handler {
	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", s.healthz)
	mux.HandleFunc(s.cfg.TwilioVoiceWebhookPath, s.handleTwilioVoice)
	if s.cfg.TwilioCallbackWebhookPath != "" && s.cfg.TwilioCallbackWebhookPath != s.cfg.TwilioVoiceWebhookPath {
		mux.HandleFunc(s.cfg.TwilioCallbackWebhookPath, s.handleTwilioCallbackDiscovery)
	}
	mux.HandleFunc(s.cfg.TwilioStreamPath, s.handleTwilioStream)

	return mux
}

func (s *Server) healthz(w http.ResponseWriter, _ *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write([]byte(`{"ok":true}`))
}

func (s *Server) handleTwilioVoice(w http.ResponseWriter, r *http.Request) {
	s.handleTwilioVoiceProfile(w, r, ProfileInbound)
}

func (s *Server) handleTwilioCallbackDiscovery(w http.ResponseWriter, r *http.Request) {
	s.handleTwilioVoiceProfile(w, r, ProfileCallbackDiscovery)
}

func (s *Server) handleTwilioVoiceProfile(w http.ResponseWriter, r *http.Request, profile string) {
	if err := r.ParseForm(); err != nil {
		s.logger.Printf("twilio voice webhook rejected: invalid form body: %v", err)
		http.Error(w, "invalid form body", http.StatusBadRequest)
		return
	}

	signatureURL, ok := s.validTwilioSignatureURL(r)
	if !ok {
		s.logger.Printf(
			"twilio voice webhook rejected: invalid signature host=%q forwarded_host=%q forwarded_proto=%q path=%q",
			r.Host,
			r.Header.Get("X-Forwarded-Host"),
			r.Header.Get("X-Forwarded-Proto"),
			r.URL.RequestURI(),
		)
		http.Error(w, "invalid twilio signature", http.StatusUnauthorized)
		return
	}

	callSID := r.PostForm.Get("CallSid")
	from := r.PostForm.Get("From")
	to := r.PostForm.Get("To")
	customerPhone := from
	if profile == ProfileCallbackDiscovery {
		customerPhone = firstNonEmpty(to, r.URL.Query().Get("to"), from)
	}

	payload := voiceResponse{
		Say: say{
			Language: "en-US",
			Text:     "This call may be monitored or recorded.",
		},
		Connect: connect{
			Stream: stream{
				URL: s.streamURL(r, signatureURL),
				Parameter: []parameter{
					{Name: "bridge_token", Value: s.cfg.StreamAuthToken},
					{Name: "call_sid", Value: callSID},
					{Name: "from", Value: from},
					{Name: "to", Value: to},
					{Name: "customer_phone", Value: customerPhone},
					{Name: "prompt_profile", Value: profile},
					{Name: "voice_ai_call_id", Value: r.URL.Query().Get("voice_ai_call_id")},
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

	session := NewSession(s.cfg, s.logger, s.laravel, s.promptBuilders, conn)
	if err := session.Run(r.Context()); err != nil {
		s.logger.Printf("session ended with error: %v", err)
	}
}

func (s *Server) validTwilioSignatureURL(r *http.Request) (string, bool) {
	provided := r.Header.Get("X-Twilio-Signature")
	for _, requestURL := range s.twilioWebhookURLs(r) {
		if twilio.ValidateSignature(s.cfg.TwilioAuthToken, requestURL, r.PostForm, provided) {
			return requestURL, true
		}
	}

	return "", false
}

func (s *Server) twilioWebhookURLs(r *http.Request) []string {
	path := r.URL.RequestURI()
	if path == "" {
		path = s.cfg.TwilioVoiceWebhookPath
	}

	candidates := make([]string, 0, 3)
	if s.cfg.PublicBaseURL != "" {
		candidates = append(candidates, strings.TrimRight(s.cfg.PublicBaseURL, "/")+path)
	}
	if base := forwardedBaseURL(r); base != "" {
		candidates = append(candidates, base+path)
	}
	if base := requestBaseURL(r); base != "" {
		candidates = append(candidates, base+path)
	}

	return uniqueStrings(candidates)
}

func (s *Server) streamURL(r *http.Request, webhookURL string) string {
	base := originFromURL(webhookURL)
	if base == "" {
		base = firstNonEmpty(forwardedBaseURL(r), requestBaseURL(r), s.cfg.PublicBaseURL)
	}
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

func forwardedBaseURL(r *http.Request) string {
	forwardedHost := firstForwardedValue(r.Header.Get("X-Forwarded-Host"))
	forwardedProto := strings.ToLower(firstForwardedValue(r.Header.Get("X-Forwarded-Proto")))
	if forwardedHost == "" && forwardedProto == "" {
		return ""
	}

	host := firstNonEmpty(forwardedHost, r.Host)
	if host == "" {
		return ""
	}

	scheme := forwardedProto
	if scheme != "http" && scheme != "https" {
		scheme = requestScheme(r)
	}

	return (&url.URL{Scheme: scheme, Host: host}).String()
}

func requestBaseURL(r *http.Request) string {
	if r.Host == "" {
		return ""
	}

	return (&url.URL{Scheme: requestScheme(r), Host: r.Host}).String()
}

func requestScheme(r *http.Request) string {
	if r.TLS != nil {
		return "https"
	}

	return "http"
}

func firstForwardedValue(value string) string {
	parts := strings.Split(value, ",")
	for _, part := range parts {
		if trimmed := strings.TrimSpace(part); trimmed != "" {
			return trimmed
		}
	}

	return ""
}

func originFromURL(rawURL string) string {
	parsed, err := url.Parse(rawURL)
	if err != nil || parsed.Scheme == "" || parsed.Host == "" {
		return ""
	}

	return (&url.URL{Scheme: parsed.Scheme, Host: parsed.Host}).String()
}

func uniqueStrings(values []string) []string {
	seen := make(map[string]struct{}, len(values))
	unique := make([]string, 0, len(values))
	for _, value := range values {
		if value == "" {
			continue
		}
		if _, ok := seen[value]; ok {
			continue
		}
		seen[value] = struct{}{}
		unique = append(unique, value)
	}

	return unique
}
