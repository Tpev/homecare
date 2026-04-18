package laravel

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"

	"voice-agent/internal/config"
)

type Client struct {
	baseURL string
	token   string
	http    *http.Client
}

type Knowledge struct {
	BrandName      string            `json:"brand_name"`
	ServiceSummary string            `json:"service_summary"`
	ServiceDetails []string          `json:"service_details"`
	Capabilities   []string          `json:"capabilities"`
	Intents        []string          `json:"intents"`
	HumanHandoff   map[string]any    `json:"human_handoff"`
	SignupLinks    map[string]string `json:"signup_links"`
	FAQs           []FAQ             `json:"faqs"`
}

type FAQ struct {
	Question string   `json:"question"`
	Answer   string   `json:"answer"`
	Keywords []string `json:"keywords"`
}

type LeadPayload struct {
	LeadType          string         `json:"lead_type"`
	Intent            string         `json:"intent"`
	Name              string         `json:"name,omitempty"`
	Phone             string         `json:"phone,omitempty"`
	Email             string         `json:"email,omitempty"`
	Notes             string         `json:"notes,omitempty"`
	CallSID           string         `json:"call_sid,omitempty"`
	TranscriptExcerpt string         `json:"transcript_excerpt,omitempty"`
	Metadata          map[string]any `json:"metadata,omitempty"`
}

type CallbackPayload struct {
	LeadType          string         `json:"lead_type,omitempty"`
	Name              string         `json:"name,omitempty"`
	Phone             string         `json:"phone"`
	CallbackTime      string         `json:"callback_time,omitempty"`
	Reason            string         `json:"reason,omitempty"`
	CallSID           string         `json:"call_sid,omitempty"`
	TranscriptExcerpt string         `json:"transcript_excerpt,omitempty"`
	Metadata          map[string]any `json:"metadata,omitempty"`
}

type SignupPayload struct {
	LeadType          string         `json:"lead_type"`
	Name              string         `json:"name,omitempty"`
	Phone             string         `json:"phone,omitempty"`
	ConsentReceived   bool           `json:"consent_received"`
	CallSID           string         `json:"call_sid,omitempty"`
	TranscriptExcerpt string         `json:"transcript_excerpt,omitempty"`
	Metadata          map[string]any `json:"metadata,omitempty"`
}

type SignupResponse struct {
	LeadID     int64  `json:"lead_id"`
	Status     string `json:"status"`
	SignupLink string `json:"signup_link"`
	SMSMessage string `json:"sms_message"`
}

func NewClient(cfg config.Config) *Client {
	return &Client{
		baseURL: cfg.LaravelBaseURL,
		token:   cfg.LaravelInternalAPIToken,
		http: &http.Client{
			Timeout: 15 * time.Second,
		},
	}
}

func (c *Client) GetKnowledge(ctx context.Context) (Knowledge, error) {
	req, err := c.newRequest(ctx, http.MethodGet, "/api/internal/voice/knowledge", nil)
	if err != nil {
		return Knowledge{}, err
	}

	var payload Knowledge
	if err := c.do(req, &payload); err != nil {
		return Knowledge{}, err
	}

	return payload, nil
}

func (c *Client) CreateLead(ctx context.Context, payload LeadPayload) error {
	req, err := c.newRequest(ctx, http.MethodPost, "/api/internal/voice/leads", payload)
	if err != nil {
		return err
	}

	return c.do(req, nil)
}

func (c *Client) CreateCallback(ctx context.Context, payload CallbackPayload) error {
	req, err := c.newRequest(ctx, http.MethodPost, "/api/internal/voice/callbacks", payload)
	if err != nil {
		return err
	}

	return c.do(req, nil)
}

func (c *Client) CreateSignupLink(ctx context.Context, payload SignupPayload) (SignupResponse, error) {
	req, err := c.newRequest(ctx, http.MethodPost, "/api/internal/voice/signup-link", payload)
	if err != nil {
		return SignupResponse{}, err
	}

	var response SignupResponse
	if err := c.do(req, &response); err != nil {
		return SignupResponse{}, err
	}

	return response, nil
}

func (c *Client) newRequest(ctx context.Context, method, path string, body any) (*http.Request, error) {
	var reader io.Reader
	if body != nil {
		buffer := &bytes.Buffer{}
		if err := json.NewEncoder(buffer).Encode(body); err != nil {
			return nil, err
		}
		reader = buffer
	}

	req, err := http.NewRequestWithContext(ctx, method, c.baseURL+path, reader)
	if err != nil {
		return nil, err
	}

	req.Header.Set("Authorization", "Bearer "+c.token)
	req.Header.Set("Accept", "application/json")
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}

	return req, nil
}

func (c *Client) do(req *http.Request, out any) error {
	resp, err := c.http.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		body, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		return fmt.Errorf("laravel %s %s returned %d: %s", req.Method, req.URL.Path, resp.StatusCode, strings.TrimSpace(string(body)))
	}

	if out == nil {
		io.Copy(io.Discard, resp.Body)
		return nil
	}

	return json.NewDecoder(resp.Body).Decode(out)
}
