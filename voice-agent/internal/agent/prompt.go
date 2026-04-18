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

func (p *PromptBuilder) Render(knowledge laravel.Knowledge) (string, error) {
	data := struct {
		Knowledge laravel.Knowledge
	}{
		Knowledge: knowledge,
	}

	var buffer bytes.Buffer
	if err := p.tmpl.Execute(&buffer, data); err != nil {
		return "", err
	}

	return buffer.String(), nil
}
