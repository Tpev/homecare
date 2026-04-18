package agent

import (
	"context"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"net/url"
	"slices"
	"strings"
	"sync"
	"time"

	"github.com/gorilla/websocket"

	"voice-agent/internal/config"
	"voice-agent/internal/laravel"
	"voice-agent/internal/twilio"
)

type Session struct {
	cfg           config.Config
	logger        *log.Logger
	laravel       *laravel.Client
	promptBuilder *PromptBuilder
	twilioConn    *websocket.Conn

	twilioWriteMu sync.Mutex
	dgWriteMu     sync.Mutex

	streamSID    string
	callSID      string
	callerPhone  string
	leadCreated  bool
	knowledge    laravel.Knowledge
	transcriptMu sync.Mutex
	transcript   []string
}

type dgEnvelope struct {
	Type string `json:"type"`
}

type dgFunctionCallRequest struct {
	Type      string           `json:"type"`
	Functions []dgFunctionCall `json:"functions"`
}

type dgFunctionCall struct {
	ID               string `json:"id"`
	Name             string `json:"name"`
	Arguments        string `json:"arguments"`
	ClientSide       bool   `json:"client_side"`
	ThoughtSignature string `json:"thought_signature,omitempty"`
}

type dgConversationText struct {
	Type    string `json:"type"`
	Role    string `json:"role"`
	Content string `json:"content"`
}

type dgErrorMessage struct {
	Type    string `json:"type"`
	Message string `json:"message"`
	Code    string `json:"code,omitempty"`
}

func NewSession(cfg config.Config, logger *log.Logger, laravelClient *laravel.Client, promptBuilder *PromptBuilder, twilioConn *websocket.Conn) *Session {
	return &Session{
		cfg:           cfg,
		logger:        logger,
		laravel:       laravelClient,
		promptBuilder: promptBuilder,
		twilioConn:    twilioConn,
	}
}

func (s *Session) Run(parent context.Context) error {
	ctx, cancel := context.WithCancel(parent)
	defer cancel()

	audioCh := make(chan []byte, 128)
	startCh := make(chan twilio.StartPayload, 1)
	twilioErrCh := make(chan error, 1)
	dgErrCh := make(chan error, 1)

	go s.readTwilio(ctx, cancel, startCh, audioCh, twilioErrCh)

	var start twilio.StartPayload
	select {
	case start = <-startCh:
	case err := <-twilioErrCh:
		return err
	case <-ctx.Done():
		return ctx.Err()
	}

	if start.CustomParameters["bridge_token"] != s.cfg.StreamAuthToken {
		return fmt.Errorf("invalid bridge token for call %s", start.CallSID)
	}

	s.streamSID = start.StreamSID
	s.callSID = start.CallSID
	s.callerPhone = firstNonEmpty(start.CustomParameters["from"], s.callerPhone)

	knowledge, err := s.laravel.GetKnowledge(ctx)
	if err != nil {
		return fmt.Errorf("fetch knowledge: %w", err)
	}
	s.knowledge = knowledge

	prompt, err := s.promptBuilder.Render(knowledge)
	if err != nil {
		return fmt.Errorf("render prompt: %w", err)
	}

	dgConn, err := s.connectDeepgram(ctx, prompt)
	if err != nil {
		return err
	}
	defer dgConn.Close()

	go s.readDeepgram(ctx, cancel, dgConn, dgErrCh)
	go s.keepAlive(ctx, dgConn)

	for {
		select {
		case audio := <-audioCh:
			if audio == nil {
				continue
			}
			if err := s.writeDeepgramBinary(dgConn, audio); err != nil {
				return err
			}
		case err := <-twilioErrCh:
			if finalizeErr := s.finalize(); finalizeErr != nil {
				s.logger.Printf("finalize error after twilio close: %v", finalizeErr)
			}
			return err
		case err := <-dgErrCh:
			if finalizeErr := s.finalize(); finalizeErr != nil {
				s.logger.Printf("finalize error after deepgram close: %v", finalizeErr)
			}
			return err
		case <-ctx.Done():
			return s.finalize()
		}
	}
}

func (s *Session) readTwilio(ctx context.Context, cancel context.CancelFunc, startCh chan<- twilio.StartPayload, audioCh chan<- []byte, errCh chan<- error) {
	defer close(audioCh)

	for {
		select {
		case <-ctx.Done():
			return
		default:
		}

		_, payload, err := s.twilioConn.ReadMessage()
		if err != nil {
			errCh <- err
			cancel()
			return
		}

		var message twilio.Message
		if err := json.Unmarshal(payload, &message); err != nil {
			s.logger.Printf("invalid twilio message: %v", err)
			continue
		}

		switch message.Event {
		case "connected":
			continue
		case "start":
			if message.Start != nil {
				select {
				case startCh <- *message.Start:
				default:
				}
			}
		case "media":
			if message.Media == nil || message.Media.Payload == "" {
				continue
			}

			audio, err := base64.StdEncoding.DecodeString(message.Media.Payload)
			if err != nil {
				s.logger.Printf("decode twilio audio: %v", err)
				continue
			}

			select {
			case audioCh <- audio:
			case <-ctx.Done():
				return
			}
		case "stop":
			cancel()
			errCh <- context.Canceled
			return
		}
	}
}

func (s *Session) connectDeepgram(ctx context.Context, prompt string) (*websocket.Conn, error) {
	dialer := websocket.Dialer{
		Subprotocols: []string{"token", s.cfg.DeepgramAPIKey},
	}

	conn, response, err := dialer.DialContext(ctx, s.cfg.DeepgramWSURL, nil)
	if err != nil {
		if response != nil {
			return nil, fmt.Errorf("dial deepgram: %w (status %s)", err, response.Status)
		}
		return nil, fmt.Errorf("dial deepgram: %w", err)
	}

	if err := s.waitForDeepgramMessage(conn, "Welcome"); err != nil {
		conn.Close()
		return nil, err
	}

	listenProvider := map[string]any{
		"type":  "deepgram",
		"model": s.cfg.DeepgramSTTModel,
	}

	if strings.HasPrefix(s.cfg.DeepgramSTTModel, "flux-") {
		listenProvider["version"] = "v2"
	} else {
		listenProvider["smart_format"] = true
	}

	settings := map[string]any{
		"type": "Settings",
		"audio": map[string]any{
			"input": map[string]any{
				"encoding":    "mulaw",
				"sample_rate": 8000,
			},
			"output": map[string]any{
				"encoding":    "mulaw",
				"sample_rate": 8000,
				"container":   "none",
			},
		},
		"agent": map[string]any{
			"language": s.cfg.DeepgramLanguage,
			"listen": map[string]any{
				"provider": listenProvider,
			},
			"think": map[string]any{
				"provider": map[string]any{
					"type":        "open_ai",
					"model":       s.cfg.DeepgramLLMModel,
					"temperature": 0.2,
				},
				"prompt": prompt,
				"functions": []map[string]any{
					{
						"name":        "lookup_service_info",
						"description": "Retrieve approved information about the service, signup paths, and common FAQs.",
						"parameters": map[string]any{
							"type": "object",
							"properties": map[string]any{
								"topic": map[string]any{
									"type":        "string",
									"description": "The topic or question the caller wants clarified.",
								},
							},
							"required": []string{"topic"},
						},
					},
					{
						"name":        "request_human_callback",
						"description": "Create a callback request for a human team member.",
						"parameters": map[string]any{
							"type": "object",
							"properties": map[string]any{
								"lead_type":     map[string]any{"type": "string", "description": "family, caregiver, agency, or general"},
								"name":          map[string]any{"type": "string"},
								"phone":         map[string]any{"type": "string"},
								"callback_time": map[string]any{"type": "string"},
								"reason":        map[string]any{"type": "string"},
							},
							"required": []string{"phone"},
						},
					},
					{
						"name":        "send_signup_link",
						"description": "Create a signup-link request and send the link by SMS after the caller has explicitly agreed to receive it.",
						"parameters": map[string]any{
							"type": "object",
							"properties": map[string]any{
								"lead_type":        map[string]any{"type": "string", "description": "family, caregiver, agency, or general"},
								"name":             map[string]any{"type": "string"},
								"phone":            map[string]any{"type": "string"},
								"consent_received": map[string]any{"type": "boolean"},
							},
							"required": []string{"lead_type", "phone", "consent_received"},
						},
					},
				},
			},
			"speak": map[string]any{
				"provider": map[string]any{
					"type":  "deepgram",
					"model": s.cfg.DeepgramTTSModel,
				},
			},
			"greeting": s.cfg.DeepgramGreeting,
		},
	}

	if err := s.writeDeepgramJSON(conn, settings); err != nil {
		conn.Close()
		return nil, err
	}

	if err := s.waitForDeepgramMessage(conn, "SettingsApplied"); err != nil {
		conn.Close()
		return nil, err
	}

	return conn, nil
}

func (s *Session) waitForDeepgramMessage(conn *websocket.Conn, wantType string) error {
	for {
		messageType, payload, err := conn.ReadMessage()
		if err != nil {
			return err
		}

		if messageType != websocket.TextMessage {
			continue
		}

		var envelope dgEnvelope
		if err := json.Unmarshal(payload, &envelope); err != nil {
			continue
		}

		if envelope.Type == "Error" {
			var dgErr dgErrorMessage
			if err := json.Unmarshal(payload, &dgErr); err == nil {
				return fmt.Errorf("deepgram error while waiting for %s: %s (%s)", wantType, dgErr.Message, dgErr.Code)
			}

			return fmt.Errorf("deepgram error while waiting for %s: %s", wantType, strings.TrimSpace(string(payload)))
		}

		if envelope.Type == "Warning" {
			s.logger.Printf("deepgram warning while waiting for %s: %s", wantType, strings.TrimSpace(string(payload)))
			continue
		}

		if envelope.Type == wantType {
			return nil
		}
	}
}

func (s *Session) readDeepgram(ctx context.Context, cancel context.CancelFunc, conn *websocket.Conn, errCh chan<- error) {
	for {
		select {
		case <-ctx.Done():
			return
		default:
		}

		messageType, payload, err := conn.ReadMessage()
		if err != nil {
			errCh <- err
			cancel()
			return
		}

		switch messageType {
		case websocket.BinaryMessage:
			if err := s.sendAudioToTwilio(payload); err != nil {
				errCh <- err
				cancel()
				return
			}
		case websocket.TextMessage:
			if err := s.handleDeepgramText(ctx, conn, payload); err != nil {
				errCh <- err
				cancel()
				return
			}
		}
	}
}

func (s *Session) keepAlive(ctx context.Context, conn *websocket.Conn) {
	ticker := time.NewTicker(8 * time.Second)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			if err := s.writeDeepgramJSON(conn, map[string]string{"type": "KeepAlive"}); err != nil {
				s.logger.Printf("deepgram keepalive failed: %v", err)
				return
			}
		}
	}
}

func (s *Session) handleDeepgramText(ctx context.Context, conn *websocket.Conn, payload []byte) error {
	var envelope dgEnvelope
	if err := json.Unmarshal(payload, &envelope); err != nil {
		return nil
	}

	switch envelope.Type {
	case "ConversationText":
		var message dgConversationText
		if err := json.Unmarshal(payload, &message); err == nil && strings.TrimSpace(message.Content) != "" {
			s.addTranscript(message.Role + ": " + message.Content)
		}
	case "UserStartedSpeaking":
		return s.writeTwilioJSON(twilio.ClearMessage{
			Event:     "clear",
			StreamSID: s.streamSID,
		})
	case "FunctionCallRequest":
		var request dgFunctionCallRequest
		if err := json.Unmarshal(payload, &request); err != nil {
			return err
		}

		for _, fn := range request.Functions {
			if !fn.ClientSide {
				continue
			}

			content, err := s.executeFunction(ctx, fn)
			if err != nil {
				content = fmt.Sprintf(`{"error":%q}`, err.Error())
			}

			response := map[string]any{
				"type":    "FunctionCallResponse",
				"id":      fn.ID,
				"name":    fn.Name,
				"content": content,
			}

			if err := s.writeDeepgramJSON(conn, response); err != nil {
				return err
			}
		}
	case "Error":
		var dgErr dgErrorMessage
		if err := json.Unmarshal(payload, &dgErr); err == nil {
			return fmt.Errorf("deepgram error: %s (%s)", dgErr.Message, dgErr.Code)
		}

		return fmt.Errorf("deepgram error: %s", strings.TrimSpace(string(payload)))
	case "Warning", "AgentThinking", "AgentAudioDone", "Welcome", "SettingsApplied":
		s.logger.Printf("deepgram %s: %s", envelope.Type, strings.TrimSpace(string(payload)))
	}

	return nil
}

func (s *Session) executeFunction(ctx context.Context, fn dgFunctionCall) (string, error) {
	var args map[string]any
	if strings.TrimSpace(fn.Arguments) != "" {
		if err := json.Unmarshal([]byte(fn.Arguments), &args); err != nil {
			return "", fmt.Errorf("parse %s arguments: %w", fn.Name, err)
		}
	}

	switch fn.Name {
	case "lookup_service_info":
		topic := strings.TrimSpace(stringValue(args["topic"]))
		matches := make([]laravel.FAQ, 0, len(s.knowledge.FAQs))
		for _, faq := range s.knowledge.FAQs {
			if topic == "" || containsTopic(topic, faq) {
				matches = append(matches, faq)
			}
		}
		if len(matches) == 0 {
			matches = slices.Clone(s.knowledge.FAQs)
		}

		payload := map[string]any{
			"topic":           topic,
			"brand_name":      s.knowledge.BrandName,
			"service_summary": s.knowledge.ServiceSummary,
			"service_details": s.knowledge.ServiceDetails,
			"capabilities":    s.knowledge.Capabilities,
			"signup_links":    s.knowledge.SignupLinks,
			"matches":         matches,
		}
		return mustJSON(payload), nil
	case "request_human_callback":
		phone := firstNonEmpty(stringValue(args["phone"]), s.callerPhone)
		payload := laravel.CallbackPayload{
			LeadType:          firstNonEmpty(stringValue(args["lead_type"]), "general"),
			Name:              stringValue(args["name"]),
			Phone:             phone,
			CallbackTime:      stringValue(args["callback_time"]),
			Reason:            stringValue(args["reason"]),
			CallSID:           s.callSID,
			TranscriptExcerpt: s.transcriptExcerpt(),
			Metadata: map[string]any{
				"channel": "voice_agent",
			},
		}
		if err := s.laravel.CreateCallback(ctx, payload); err != nil {
			return "", err
		}
		s.leadCreated = true
		return mustJSON(map[string]any{
			"status":  "ok",
			"message": "Callback request captured.",
			"phone":   phone,
		}), nil
	case "send_signup_link":
		phone := firstNonEmpty(stringValue(args["phone"]), s.callerPhone)
		consent := boolValue(args["consent_received"])
		if !consent {
			return "", fmt.Errorf("caller consent is required before sending an SMS signup link")
		}

		signup, err := s.laravel.CreateSignupLink(ctx, laravel.SignupPayload{
			LeadType:          firstNonEmpty(stringValue(args["lead_type"]), "general"),
			Name:              stringValue(args["name"]),
			Phone:             phone,
			ConsentReceived:   consent,
			CallSID:           s.callSID,
			TranscriptExcerpt: s.transcriptExcerpt(),
			Metadata: map[string]any{
				"channel": "voice_agent",
			},
		})
		if err != nil {
			return "", err
		}

		if err := s.sendSMS(ctx, phone, signup.SMSMessage); err != nil {
			return "", err
		}

		s.leadCreated = true
		return mustJSON(map[string]any{
			"status":      "ok",
			"phone":       phone,
			"signup_link": signup.SignupLink,
			"message":     "Signup link sent by SMS.",
		}), nil
	default:
		return "", fmt.Errorf("unsupported function: %s", fn.Name)
	}
}

func (s *Session) sendAudioToTwilio(raw []byte) error {
	message := twilio.OutboundMediaMessage{
		Event:     "media",
		StreamSID: s.streamSID,
		Media: twilio.OutboundMediaPayload{
			Payload: base64.StdEncoding.EncodeToString(raw),
		},
	}

	return s.writeTwilioJSON(message)
}

func (s *Session) writeTwilioJSON(payload any) error {
	s.twilioWriteMu.Lock()
	defer s.twilioWriteMu.Unlock()

	return s.twilioConn.WriteJSON(payload)
}

func (s *Session) writeDeepgramJSON(conn *websocket.Conn, payload any) error {
	s.dgWriteMu.Lock()
	defer s.dgWriteMu.Unlock()

	return conn.WriteJSON(payload)
}

func (s *Session) writeDeepgramBinary(conn *websocket.Conn, payload []byte) error {
	s.dgWriteMu.Lock()
	defer s.dgWriteMu.Unlock()

	return conn.WriteMessage(websocket.BinaryMessage, payload)
}

func (s *Session) finalize() error {
	if s.leadCreated || len(s.transcript) == 0 {
		return nil
	}

	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	return s.laravel.CreateLead(ctx, laravel.LeadPayload{
		LeadType:          "general",
		Intent:            "information",
		Phone:             s.callerPhone,
		CallSID:           s.callSID,
		TranscriptExcerpt: s.transcriptExcerpt(),
		Notes:             "Captured automatically from an informational voice-agent call.",
		Metadata: map[string]any{
			"channel": "voice_agent",
		},
	})
}

func (s *Session) sendSMS(ctx context.Context, to, body string) error {
	if strings.TrimSpace(to) == "" {
		return fmt.Errorf("missing destination phone number")
	}

	values := url.Values{}
	values.Set("To", to)
	values.Set("From", s.cfg.TwilioSMSFrom)
	values.Set("Body", body)

	endpoint := fmt.Sprintf("https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json", s.cfg.TwilioAccountSID)
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, endpoint, strings.NewReader(values.Encode()))
	if err != nil {
		return err
	}

	req.SetBasicAuth(s.cfg.TwilioAccountSID, s.cfg.TwilioAuthToken)
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")

	client := &http.Client{Timeout: 15 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return fmt.Errorf("twilio sms returned %s", resp.Status)
	}

	return nil
}

func (s *Session) addTranscript(line string) {
	s.transcriptMu.Lock()
	defer s.transcriptMu.Unlock()

	s.transcript = append(s.transcript, line)
	if len(s.transcript) > 24 {
		s.transcript = s.transcript[len(s.transcript)-24:]
	}
}

func (s *Session) transcriptExcerpt() string {
	s.transcriptMu.Lock()
	defer s.transcriptMu.Unlock()

	return strings.Join(s.transcript, "\n")
}

func containsTopic(topic string, faq laravel.FAQ) bool {
	needle := strings.ToLower(strings.TrimSpace(topic))
	if needle == "" {
		return true
	}

	if strings.Contains(strings.ToLower(faq.Question), needle) || strings.Contains(strings.ToLower(faq.Answer), needle) {
		return true
	}

	for _, keyword := range faq.Keywords {
		if strings.Contains(strings.ToLower(keyword), needle) || strings.Contains(needle, strings.ToLower(keyword)) {
			return true
		}
	}

	return false
}

func stringValue(value any) string {
	switch typed := value.(type) {
	case string:
		return strings.TrimSpace(typed)
	default:
		return ""
	}
}

func boolValue(value any) bool {
	switch typed := value.(type) {
	case bool:
		return typed
	case string:
		return strings.EqualFold(strings.TrimSpace(typed), "true")
	default:
		return false
	}
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if trimmed := strings.TrimSpace(value); trimmed != "" {
			return trimmed
		}
	}

	return ""
}

func mustJSON(value any) string {
	body, err := json.Marshal(value)
	if err != nil {
		return `{"status":"error","message":"failed to serialize response"}`
	}

	return string(body)
}
