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
	cfg            config.Config
	logger         *log.Logger
	laravel        *laravel.Client
	promptBuilders map[string]*PromptBuilder
	twilioConn     *websocket.Conn

	twilioWriteMu sync.Mutex
	dgWriteMu     sync.Mutex

	streamSID                 string
	callSID                   string
	promptProfile             string
	voiceAICallID             string
	referralLeadID            string
	targetName                string
	targetOrganization        string
	targetRole                string
	targetEmail               string
	targetFax                 string
	targetLocation            string
	callerName                string
	callerPhone               string
	relationship              string
	careRecipient             string
	careNeeds                 string
	urgency                   string
	address                   string
	city                      string
	zip                       string
	callbackTime              string
	leadCreated               bool
	leadType                  string
	intent                    string
	outcome                   string
	knowledge                 laravel.Knowledge
	callStartedAt             time.Time
	callEndedAt               time.Time
	callbackRequested         bool
	signupLinkSent            bool
	transcriptMu              sync.Mutex
	transcript                []string
	recorder                  *LocalRecorder
	recordingErr              error
	providerOutcome           string
	providerSummary           string
	providerNotes             string
	providerContactName       string
	providerContactRole       string
	providerEmail             string
	providerFax               string
	providerResourceRequested bool
	providerFollowUpNeeded    bool
	providerBestFollowUp      string
	providerDoNotCall         bool
	providerVoicemailDetected bool
	providerIVRDetected       bool
	providerAIDetected        bool
	providerObjection         string
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

func NewSession(cfg config.Config, logger *log.Logger, laravelClient *laravel.Client, promptBuilders map[string]*PromptBuilder, twilioConn *websocket.Conn) *Session {
	return &Session{
		cfg:            cfg,
		logger:         logger,
		laravel:        laravelClient,
		promptBuilders: promptBuilders,
		twilioConn:     twilioConn,
	}
}

func (s *Session) Run(parent context.Context) error {
	var runErr error
	defer func() {
		if reportErr := s.reportCall(runErr); reportErr != nil {
			s.logger.Printf("voice call report failed: %v", reportErr)
		}
	}()

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
		runErr = err
		return err
	case <-ctx.Done():
		runErr = ctx.Err()
		return ctx.Err()
	}

	if start.CustomParameters["bridge_token"] != s.cfg.StreamAuthToken {
		return fmt.Errorf("invalid bridge token for call %s", start.CallSID)
	}

	s.streamSID = start.StreamSID
	s.callSID = start.CallSID
	s.promptProfile = supportedProfile(start.CustomParameters["prompt_profile"])
	s.voiceAICallID = start.CustomParameters["voice_ai_call_id"]
	s.referralLeadID = start.CustomParameters["referral_lead_id"]
	s.targetName = start.CustomParameters["target_name"]
	s.targetOrganization = start.CustomParameters["target_organization"]
	s.targetRole = start.CustomParameters["target_role"]
	s.targetEmail = start.CustomParameters["target_email"]
	s.targetFax = start.CustomParameters["target_fax"]
	s.targetLocation = start.CustomParameters["target_location"]
	s.callerPhone = firstNonEmpty(start.CustomParameters["customer_phone"], start.CustomParameters["from"], s.callerPhone)
	if s.isProviderOutreach() {
		s.leadType = "referral"
		s.intent = "provider_outreach"
	} else {
		s.leadType = "family"
		s.intent = "unknown"
	}
	s.callStartedAt = time.Now().UTC()
	s.startRecorder()

	knowledge, err := s.laravel.GetKnowledge(ctx)
	if err != nil {
		runErr = fmt.Errorf("fetch knowledge: %w", err)
		return runErr
	}
	s.knowledge = knowledge

	promptBuilder := s.promptBuilder()
	if promptBuilder == nil {
		runErr = fmt.Errorf("missing prompt builder for profile %q", s.promptProfile)
		return runErr
	}

	prompt, err := promptBuilder.Render(knowledge, s.callContext())
	if err != nil {
		runErr = fmt.Errorf("render prompt: %w", err)
		return runErr
	}

	dgConn, err := s.connectDeepgram(ctx, prompt)
	if err != nil {
		runErr = err
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
				runErr = err
				return err
			}
		case err := <-twilioErrCh:
			if finalizeErr := s.finalize(); finalizeErr != nil {
				s.logger.Printf("finalize error after twilio close: %v", finalizeErr)
			}
			runErr = err
			return err
		case err := <-dgErrCh:
			if finalizeErr := s.finalize(); finalizeErr != nil {
				s.logger.Printf("finalize error after deepgram close: %v", finalizeErr)
			}
			runErr = err
			return err
		case <-ctx.Done():
			runErr = s.finalize()
			return runErr
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
			if s.recorder != nil {
				if err := s.recorder.WriteMuLaw(audio); err != nil {
					s.recordingErr = err
					s.logger.Printf("record caller audio: %v", err)
				}
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
				"prompt":    prompt,
				"functions": s.deepgramFunctions(),
			},
			"speak": map[string]any{
				"provider": map[string]any{
					"type":  "deepgram",
					"model": s.cfg.DeepgramTTSModel,
				},
			},
			"greeting": s.greeting(),
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

func (s *Session) deepgramFunctions() []map[string]any {
	functions := []map[string]any{
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
					"lead_type":      map[string]any{"type": "string", "description": "family, caregiver, agency, or general"},
					"name":           map[string]any{"type": "string", "description": "Caller name"},
					"phone":          map[string]any{"type": "string", "description": "Best callback number"},
					"relationship":   map[string]any{"type": "string", "description": "Relationship to the person needing care"},
					"care_recipient": map[string]any{"type": "string", "description": "Who needs care"},
					"care_needs":     map[string]any{"type": "string", "description": "Type of help or support needed"},
					"urgency":        map[string]any{"type": "string", "description": "How urgent the situation is"},
					"address":        map[string]any{"type": "string", "description": "Street address if shared"},
					"city":           map[string]any{"type": "string", "description": "City if shared"},
					"zip":            map[string]any{"type": "string", "description": "Zip code if shared"},
					"callback_time":  map[string]any{"type": "string", "description": "Best callback time or window"},
					"reason":         map[string]any{"type": "string"},
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
					"name":             map[string]any{"type": "string", "description": "Caller name"},
					"phone":            map[string]any{"type": "string", "description": "SMS-capable phone number"},
					"relationship":     map[string]any{"type": "string", "description": "Relationship to the person needing care"},
					"care_recipient":   map[string]any{"type": "string", "description": "Who needs care"},
					"care_needs":       map[string]any{"type": "string", "description": "Type of help or support needed"},
					"urgency":          map[string]any{"type": "string", "description": "How urgent the situation is"},
					"address":          map[string]any{"type": "string", "description": "Street address if shared"},
					"city":             map[string]any{"type": "string", "description": "City if shared"},
					"zip":              map[string]any{"type": "string", "description": "Zip code if shared"},
					"consent_received": map[string]any{"type": "boolean"},
				},
				"required": []string{"lead_type", "phone", "consent_received"},
			},
		},
	}

	if !s.isProviderOutreach() {
		return functions
	}

	return append(functions, map[string]any{
		"name":        "record_provider_outreach_result",
		"description": "Record the outcome of Julie's provider-relations outreach call into the LoLo referral-source CRM.",
		"parameters": map[string]any{
			"type": "object",
			"properties": map[string]any{
				"outcome":            map[string]any{"type": "string", "description": "completed, resource_requested, follow_up_needed, voicemail, ivr, ai_system, not_interested, not_fit, do_not_call, wrong_number, or incomplete"},
				"summary":            map[string]any{"type": "string", "description": "Concise one or two sentence outcome summary"},
				"notes":              map[string]any{"type": "string", "description": "Additional operational notes for the CRM"},
				"contact_name":       map[string]any{"type": "string", "description": "Person reached or best contact identified"},
				"contact_role":       map[string]any{"type": "string", "description": "Role of the person reached"},
				"email":              map[string]any{"type": "string", "description": "Email to send the one-page resource if provided"},
				"fax":                map[string]any{"type": "string", "description": "Fax number to send the one-page resource if provided"},
				"resource_requested": map[string]any{"type": "boolean", "description": "Whether they agreed that a one-page resource would be useful"},
				"follow_up_needed":   map[string]any{"type": "boolean", "description": "Whether a human follow-up is useful"},
				"best_follow_up":     map[string]any{"type": "string", "description": "Best time or method for human follow-up"},
				"do_not_call":        map[string]any{"type": "boolean", "description": "True if they requested no future calls"},
				"voicemail_detected": map[string]any{"type": "boolean", "description": "True if voicemail was reached"},
				"ivr_detected":       map[string]any{"type": "boolean", "description": "True if an IVR/phone tree was reached"},
				"ai_detected":        map[string]any{"type": "boolean", "description": "True if an AI receptionist/agent was detected"},
				"objection":          map[string]any{"type": "string", "description": "Main objection or refusal, if any"},
			},
			"required": []string{"outcome", "summary"},
		},
	})
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
		s.absorbCallerDetails(args)
		phone := firstNonEmpty(stringValue(args["phone"]), s.callerPhone)
		payload := laravel.CallbackPayload{
			LeadType:          firstNonEmpty(stringValue(args["lead_type"]), "family"),
			Name:              firstNonEmpty(stringValue(args["name"]), s.callerName),
			Phone:             phone,
			CallbackTime:      firstNonEmpty(stringValue(args["callback_time"]), s.callbackTime),
			Reason:            stringValue(args["reason"]),
			CallSID:           s.callSID,
			TranscriptExcerpt: s.transcriptWithLimit(4000),
			Metadata: s.metadata(map[string]any{
				"relationship":   s.relationship,
				"care_recipient": s.careRecipient,
				"care_needs":     s.careNeeds,
				"urgency":        s.urgency,
				"address":        s.address,
				"city":           s.city,
				"zip":            s.zip,
			}),
		}
		if err := s.laravel.CreateCallback(ctx, payload); err != nil {
			return "", err
		}
		s.leadCreated = true
		s.leadType = payload.LeadType
		s.intent = "callback_request"
		s.outcome = "callback_request"
		s.callbackRequested = true
		return mustJSON(map[string]any{
			"status":  "ok",
			"message": "Callback request captured.",
			"phone":   phone,
		}), nil
	case "send_signup_link":
		s.absorbCallerDetails(args)
		phone := firstNonEmpty(stringValue(args["phone"]), s.callerPhone)
		consent := boolValue(args["consent_received"])
		if !consent {
			return "", fmt.Errorf("caller consent is required before sending an SMS signup link")
		}

		signup, err := s.laravel.CreateSignupLink(ctx, laravel.SignupPayload{
			LeadType:          firstNonEmpty(stringValue(args["lead_type"]), "family"),
			Name:              firstNonEmpty(stringValue(args["name"]), s.callerName),
			Phone:             phone,
			ConsentReceived:   consent,
			CallSID:           s.callSID,
			TranscriptExcerpt: s.transcriptWithLimit(4000),
			Metadata: s.metadata(map[string]any{
				"relationship":   s.relationship,
				"care_recipient": s.careRecipient,
				"care_needs":     s.careNeeds,
				"urgency":        s.urgency,
				"address":        s.address,
				"city":           s.city,
				"zip":            s.zip,
			}),
		})
		if err != nil {
			return "", err
		}

		if err := s.sendSMS(ctx, phone, signup.SMSMessage); err != nil {
			return "", err
		}

		s.leadCreated = true
		s.leadType = firstNonEmpty(stringValue(args["lead_type"]), "family")
		s.intent = "signup_link"
		s.outcome = "signup_link_sent"
		s.signupLinkSent = true
		return mustJSON(map[string]any{
			"status":      "ok",
			"phone":       phone,
			"signup_link": signup.SignupLink,
			"message":     "Signup link sent by SMS.",
		}), nil
	case "record_provider_outreach_result":
		if !s.isProviderOutreach() {
			return "", fmt.Errorf("provider outreach results are only available on provider outreach calls")
		}

		s.absorbProviderOutreachDetails(args)
		if s.providerOutcome == "" {
			s.providerOutcome = "completed"
		}
		if s.providerSummary == "" {
			s.providerSummary = "Provider outreach call completed."
		}

		payload := s.providerOutreachPayload()
		if err := s.laravel.CreateProviderOutreachResult(ctx, payload); err != nil {
			return "", err
		}

		s.leadCreated = true
		s.leadType = "referral"
		s.intent = "provider_outreach"
		s.outcome = firstNonEmpty(s.providerOutcome, "completed")

		return mustJSON(map[string]any{
			"status":             "ok",
			"message":            "Provider outreach result captured.",
			"outcome":            s.providerOutcome,
			"resource_requested": s.providerResourceRequested,
			"follow_up_needed":   s.providerFollowUpNeeded,
			"do_not_call":        s.providerDoNotCall,
			"voicemail_detected": s.providerVoicemailDetected,
			"ivr_detected":       s.providerIVRDetected,
			"ai_detected":        s.providerAIDetected,
		}), nil
	default:
		return "", fmt.Errorf("unsupported function: %s", fn.Name)
	}
}

func (s *Session) sendAudioToTwilio(raw []byte) error {
	if s.recorder != nil {
		if err := s.recorder.WriteMuLaw(raw); err != nil {
			s.recordingErr = err
			s.logger.Printf("record assistant audio: %v", err)
		}
	}

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
	if s.isProviderOutreach() {
		if s.outcome == "" {
			s.outcome = firstNonEmpty(s.providerOutcome, "completed")
		}
		if s.intent == "" || s.intent == "unknown" {
			s.intent = "provider_outreach"
		}
		return nil
	}

	if s.leadCreated || len(s.transcript) == 0 {
		if s.outcome == "" {
			s.outcome = "information_only"
		}
		if s.intent == "unknown" {
			s.intent = "information"
		}
		return nil
	}

	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	err := s.laravel.CreateLead(ctx, laravel.LeadPayload{
		LeadType:          "family",
		Intent:            "information",
		Phone:             s.callerPhone,
		CallSID:           s.callSID,
		TranscriptExcerpt: s.transcriptWithLimit(4000),
		Notes:             "Captured automatically from an informational voice-agent call.",
		Metadata:          s.metadata(nil),
	})

	if err == nil {
		s.leadCreated = true
		s.leadType = "family"
		s.intent = "information"
		s.outcome = "information_only"
	}

	return err
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
	if len(s.transcript) > 120 {
		s.transcript = s.transcript[len(s.transcript)-120:]
	}
}

func (s *Session) transcriptWithLimit(limit int) string {
	s.transcriptMu.Lock()
	defer s.transcriptMu.Unlock()

	body := strings.Join(s.transcript, "\n")
	if limit > 0 && len(body) > limit {
		return body[len(body)-limit:]
	}

	return body
}

func (s *Session) reportCall(runErr error) error {
	if s.callSID == "" && s.callerPhone == "" && s.transcriptWithLimit(1) == "" {
		return nil
	}

	s.closeRecorder()
	s.callEndedAt = time.Now().UTC()
	if s.callStartedAt.IsZero() {
		s.callStartedAt = s.callEndedAt
	}

	callStatus := "completed"
	if runErr != nil && runErr != context.Canceled {
		callStatus = "error"
	}

	outcome := firstNonEmpty(s.outcome, "information_only")
	intent := firstNonEmpty(s.intent, "information")
	leadType := firstNonEmpty(s.leadType, "family")

	summary := "Informational family call."
	if s.isProviderOutreach() {
		leadType = "referral"
		intent = "provider_outreach"
		outcome = firstNonEmpty(s.providerOutcome, outcome, "completed")
		summary = firstNonEmpty(s.providerSummary, "Provider outreach call completed by Julie.")
	} else {
		switch outcome {
		case "callback_request":
			summary = "Family caller requested a human callback."
		case "signup_link_sent":
			summary = "Family caller received the signup link by SMS."
		case "information_only":
			summary = "Family caller received information without a callback or signup link."
		}
	}
	if runErr != nil && runErr != context.Canceled {
		summary = fmt.Sprintf("%s Session ended with error: %v", summary, runErr)
	}

	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	return s.laravel.CreateCallReport(ctx, laravel.ReportPayload{
		CallSID:           s.callSID,
		Name:              s.callerName,
		Phone:             s.callerPhone,
		Relationship:      s.relationship,
		CareRecipient:     s.careRecipient,
		CareNeeds:         s.careNeeds,
		Urgency:           s.urgency,
		Address:           s.address,
		City:              s.city,
		Zip:               s.zip,
		CallbackTime:      s.callbackTime,
		LeadType:          leadType,
		Intent:            intent,
		Outcome:           outcome,
		CallStatus:        callStatus,
		DurationSeconds:   nonNegativeDurationSeconds(s.callEndedAt.Sub(s.callStartedAt)),
		StartedAt:         s.callStartedAt.Format(time.RFC3339),
		EndedAt:           s.callEndedAt.Format(time.RFC3339),
		Summary:           summary,
		Transcript:        s.transcriptWithLimit(20000),
		CallbackRequested: s.callbackRequested,
		SignupLinkSent:    s.signupLinkSent,
		Metadata:          s.metadata(nil),
	})
}

func (s *Session) absorbCallerDetails(args map[string]any) {
	s.callerName = firstNonEmpty(stringValue(args["name"]), s.callerName)
	s.callerPhone = firstNonEmpty(stringValue(args["phone"]), s.callerPhone)
	s.relationship = firstNonEmpty(stringValue(args["relationship"]), s.relationship)
	s.careRecipient = firstNonEmpty(stringValue(args["care_recipient"]), s.careRecipient)
	s.careNeeds = firstNonEmpty(stringValue(args["care_needs"]), s.careNeeds)
	s.urgency = firstNonEmpty(stringValue(args["urgency"]), s.urgency)
	s.address = firstNonEmpty(stringValue(args["address"]), s.address)
	s.city = firstNonEmpty(stringValue(args["city"]), s.city)
	s.zip = firstNonEmpty(stringValue(args["zip"]), s.zip)
	s.callbackTime = firstNonEmpty(stringValue(args["callback_time"]), s.callbackTime)
}

func (s *Session) absorbProviderOutreachDetails(args map[string]any) {
	s.providerOutcome = firstNonEmpty(stringValue(args["outcome"]), s.providerOutcome)
	s.providerSummary = firstNonEmpty(stringValue(args["summary"]), s.providerSummary)
	s.providerNotes = firstNonEmpty(stringValue(args["notes"]), s.providerNotes)
	s.providerContactName = firstNonEmpty(stringValue(args["contact_name"]), s.providerContactName)
	s.providerContactRole = firstNonEmpty(stringValue(args["contact_role"]), s.providerContactRole)
	s.providerEmail = firstNonEmpty(stringValue(args["email"]), s.providerEmail)
	s.providerFax = firstNonEmpty(stringValue(args["fax"]), s.providerFax)
	s.providerBestFollowUp = firstNonEmpty(stringValue(args["best_follow_up"]), s.providerBestFollowUp)
	s.providerObjection = firstNonEmpty(stringValue(args["objection"]), s.providerObjection)

	s.providerResourceRequested = s.providerResourceRequested || boolValue(args["resource_requested"])
	s.providerFollowUpNeeded = s.providerFollowUpNeeded || boolValue(args["follow_up_needed"])
	s.providerDoNotCall = s.providerDoNotCall || boolValue(args["do_not_call"])
	s.providerVoicemailDetected = s.providerVoicemailDetected || boolValue(args["voicemail_detected"])
	s.providerIVRDetected = s.providerIVRDetected || boolValue(args["ivr_detected"])
	s.providerAIDetected = s.providerAIDetected || boolValue(args["ai_detected"])
}

func (s *Session) providerOutreachPayload() laravel.ProviderOutreachResultPayload {
	return laravel.ProviderOutreachResultPayload{
		CallSID:            s.callSID,
		VoiceAICallID:      s.voiceAICallID,
		ReferralLeadID:     s.referralLeadID,
		TargetName:         s.targetName,
		TargetOrganization: s.targetOrganization,
		TargetRole:         s.targetRole,
		TargetPhone:        s.callerPhone,
		TargetEmail:        s.targetEmail,
		TargetFax:          s.targetFax,
		TargetLocation:     s.targetLocation,
		Outcome:            firstNonEmpty(s.providerOutcome, "completed"),
		Summary:            s.providerSummary,
		Notes:              s.providerNotes,
		ContactName:        s.providerContactName,
		ContactRole:        s.providerContactRole,
		Email:              s.providerEmail,
		Fax:                s.providerFax,
		ResourceRequested:  s.providerResourceRequested,
		FollowUpNeeded:     s.providerFollowUpNeeded,
		BestFollowUp:       s.providerBestFollowUp,
		DoNotCall:          s.providerDoNotCall,
		VoicemailDetected:  s.providerVoicemailDetected,
		IVRDetected:        s.providerIVRDetected,
		AIDetected:         s.providerAIDetected,
		Objection:          s.providerObjection,
		TranscriptExcerpt:  s.transcriptWithLimit(4000),
		Metadata:           s.metadata(nil),
	}
}

func (s *Session) callContext() CallContext {
	return CallContext{
		Profile:            supportedProfile(s.promptProfile),
		AssistantName:      "Julie",
		VoiceAICallID:      s.voiceAICallID,
		CallSID:            s.callSID,
		CustomerPhone:      s.callerPhone,
		ReferralLeadID:     s.referralLeadID,
		TargetName:         s.targetName,
		TargetOrganization: s.targetOrganization,
		TargetRole:         s.targetRole,
		TargetEmail:        s.targetEmail,
		TargetFax:          s.targetFax,
		TargetLocation:     s.targetLocation,
	}
}

func (s *Session) startRecorder() {
	if !s.cfg.RecordingsEnabled {
		return
	}
	if supportedProfile(s.promptProfile) != ProfileCallbackDiscovery && s.voiceAICallID == "" {
		return
	}

	identifier := firstNonEmpty(s.voiceAICallID, s.callSID, "voice-call")
	recorder, err := NewLocalRecorder(s.cfg.RecordingsDir, s.cfg.RecordingsPublicBaseURL, identifier)
	if err != nil {
		s.recordingErr = err
		s.logger.Printf("start local recording: %v", err)
		return
	}

	s.recorder = recorder
}

func (s *Session) closeRecorder() {
	if s.recorder == nil {
		return
	}

	if err := s.recorder.Close(); err != nil {
		s.recordingErr = err
		s.logger.Printf("close local recording: %v", err)
	}
}

func (s *Session) promptBuilder() *PromptBuilder {
	profile := supportedProfile(s.promptProfile)
	if builder := s.promptBuilders[profile]; builder != nil {
		return builder
	}

	return s.promptBuilders[ProfileInbound]
}

func (s *Session) greeting() string {
	if s.isProviderOutreach() {
		return s.cfg.DeepgramProviderGreeting
	}
	if supportedProfile(s.promptProfile) == ProfileCallbackDiscovery {
		return s.cfg.DeepgramCallbackGreeting
	}

	return s.cfg.DeepgramGreeting
}

func (s *Session) metadata(extra map[string]any) map[string]any {
	metadata := map[string]any{
		"channel":             "voice_agent",
		"voice_agent_profile": supportedProfile(s.promptProfile),
	}

	if s.isProviderOutreach() {
		metadata["assistant_name"] = "Julie"
		metadata["referral_lead_id"] = s.referralLeadID
		metadata["target_name"] = s.targetName
		metadata["target_organization"] = s.targetOrganization
		metadata["target_role"] = s.targetRole
		metadata["target_email"] = s.targetEmail
		metadata["target_fax"] = s.targetFax
		metadata["target_location"] = s.targetLocation
		metadata["target_phone"] = s.callerPhone
		metadata["provider_outreach"] = map[string]any{
			"outcome":            s.providerOutcome,
			"summary":            s.providerSummary,
			"notes":              s.providerNotes,
			"contact_name":       s.providerContactName,
			"contact_role":       s.providerContactRole,
			"email":              s.providerEmail,
			"fax":                s.providerFax,
			"resource_requested": s.providerResourceRequested,
			"follow_up_needed":   s.providerFollowUpNeeded,
			"best_follow_up":     s.providerBestFollowUp,
			"do_not_call":        s.providerDoNotCall,
			"voicemail_detected": s.providerVoicemailDetected,
			"ivr_detected":       s.providerIVRDetected,
			"ai_detected":        s.providerAIDetected,
			"objection":          s.providerObjection,
		}
	}

	if s.voiceAICallID != "" {
		metadata["voice_ai_call_id"] = s.voiceAICallID
	}
	if s.recorder != nil {
		if path := s.recorder.Path(); path != "" {
			metadata["recording_path"] = path
		}
		if publicURL := s.recorder.PublicURL(); publicURL != "" {
			metadata["recording_url"] = publicURL
		}
		metadata["recording_mime_type"] = "audio/wav"
		metadata["recording_source"] = "local_voice_agent"
	}
	if s.recordingErr != nil {
		metadata["recording_error"] = s.recordingErr.Error()
	}

	for key, value := range extra {
		metadata[key] = value
	}

	return metadata
}

func supportedProfile(profile string) string {
	switch strings.TrimSpace(profile) {
	case ProfileCallbackDiscovery:
		return ProfileCallbackDiscovery
	case ProfileProviderOutreach:
		return ProfileProviderOutreach
	default:
		return ProfileInbound
	}
}

func (s *Session) isProviderOutreach() bool {
	return supportedProfile(s.promptProfile) == ProfileProviderOutreach
}

func nonNegativeDurationSeconds(duration time.Duration) int {
	if duration < 0 {
		return 0
	}

	return int(duration.Seconds())
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
