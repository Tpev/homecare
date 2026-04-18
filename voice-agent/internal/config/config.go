package config

import (
	"errors"
	"fmt"
	"os"
	"strings"
)

type Config struct {
	Port                    string
	PublicBaseURL           string
	TwilioAccountSID        string
	TwilioAuthToken         string
	TwilioPhoneNumber       string
	TwilioSMSFrom           string
	TwilioVoiceWebhookPath  string
	TwilioStreamPath        string
	StreamAuthToken         string
	DeepgramAPIKey          string
	DeepgramWSURL           string
	DeepgramLanguage        string
	DeepgramSTTModel        string
	DeepgramLLMModel        string
	DeepgramTTSModel        string
	DeepgramGreeting        string
	LaravelBaseURL          string
	LaravelInternalAPIToken string
	PromptFile              string
}

func Load() (Config, error) {
	cfg := Config{
		Port:                    env("PORT", "8088"),
		PublicBaseURL:           strings.TrimRight(env("PUBLIC_BASE_URL", ""), "/"),
		TwilioAccountSID:        env("TWILIO_ACCOUNT_SID", ""),
		TwilioAuthToken:         env("TWILIO_AUTH_TOKEN", ""),
		TwilioPhoneNumber:       env("TWILIO_PHONE_NUMBER", ""),
		TwilioSMSFrom:           env("TWILIO_SMS_FROM", env("TWILIO_PHONE_NUMBER", "")),
		TwilioVoiceWebhookPath:  env("TWILIO_VOICE_WEBHOOK_PATH", "/twilio/voice"),
		TwilioStreamPath:        env("TWILIO_STREAM_PATH", "/ws/twilio"),
		StreamAuthToken:         env("TWILIO_STREAM_AUTH_TOKEN", ""),
		DeepgramAPIKey:          env("DEEPGRAM_API_KEY", ""),
		DeepgramWSURL:           env("DEEPGRAM_WS_URL", "wss://agent.deepgram.com/v1/agent/converse"),
		DeepgramLanguage:        env("DEEPGRAM_LANGUAGE", "en"),
		DeepgramSTTModel:        env("DEEPGRAM_STT_MODEL", "flux-general-en"),
		DeepgramLLMModel:        env("DEEPGRAM_LLM_MODEL", "gpt-4o-mini"),
		DeepgramTTSModel:        env("DEEPGRAM_TTS_MODEL", "aura-2-thalia-en"),
		DeepgramGreeting:        env("DEEPGRAM_GREETING", "Thanks for calling Homecare. How can I help you today?"),
		LaravelBaseURL:          strings.TrimRight(env("LARAVEL_BASE_URL", "http://localhost"), "/"),
		LaravelInternalAPIToken: env("LARAVEL_INTERNAL_API_TOKEN", ""),
		PromptFile:              env("PROMPT_FILE", "prompts/system.md"),
	}

	if cfg.StreamAuthToken == "" {
		cfg.StreamAuthToken = "replace-me-before-production"
	}

	var missing []string
	for name, value := range map[string]string{
		"TWILIO_ACCOUNT_SID":         cfg.TwilioAccountSID,
		"TWILIO_AUTH_TOKEN":          cfg.TwilioAuthToken,
		"TWILIO_PHONE_NUMBER":        cfg.TwilioPhoneNumber,
		"DEEPGRAM_API_KEY":           cfg.DeepgramAPIKey,
		"LARAVEL_INTERNAL_API_TOKEN": cfg.LaravelInternalAPIToken,
	} {
		if strings.TrimSpace(value) == "" {
			missing = append(missing, name)
		}
	}

	if len(missing) > 0 {
		return Config{}, errors.New("missing required env vars: " + strings.Join(missing, ", "))
	}

	if !strings.HasPrefix(cfg.DeepgramLLMModel, "gpt-") {
		return Config{}, fmt.Errorf("DEEPGRAM_LLM_MODEL must be an OpenAI model name such as gpt-4o-mini, got %q", cfg.DeepgramLLMModel)
	}

	return cfg, nil
}

func env(key, fallback string) string {
	if value := strings.TrimSpace(os.Getenv(key)); value != "" {
		return value
	}

	return fallback
}
