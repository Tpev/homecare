package agent

import (
	"bytes"
	"encoding/json"
	"os"
	"text/template"

	"voice-agent/internal/laravel"
)

type PromptBuilder struct {
	tmpl *template.Template
}

type CallContext struct {
	Profile            string `json:"profile"`
	AssistantName      string `json:"assistant_name"`
	VoiceAICallID      string `json:"voice_ai_call_id,omitempty"`
	CallSID            string `json:"call_sid,omitempty"`
	CustomerPhone      string `json:"customer_phone,omitempty"`
	ReferralLeadID     string `json:"referral_lead_id,omitempty"`
	TargetName         string `json:"target_name,omitempty"`
	TargetOrganization string `json:"target_organization,omitempty"`
	TargetRole         string `json:"target_role,omitempty"`
	TargetEmail        string `json:"target_email,omitempty"`
	TargetFax          string `json:"target_fax,omitempty"`
	TargetLocation     string `json:"target_location,omitempty"`
}

func NewPromptBuilder(path string) (*PromptBuilder, error) {
	body, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}

	tmpl, err := template.New("system").Funcs(template.FuncMap{
		"json": func(v any) string {
			body, err := json.MarshalIndent(v, "", "  ")
			if err != nil {
				return "{}"
			}

			return string(body)
		},
	}).Parse(string(body))
	if err != nil {
		return nil, err
	}

	return &PromptBuilder{tmpl: tmpl}, nil
}

func (p *PromptBuilder) Render(knowledge laravel.Knowledge, call CallContext) (string, error) {
	data := struct {
		Knowledge laravel.Knowledge
		Call      CallContext
	}{
		Knowledge: knowledge,
		Call:      call,
	}

	var buffer bytes.Buffer
	if err := p.tmpl.Execute(&buffer, data); err != nil {
		return "", err
	}

	return buffer.String(), nil
}
