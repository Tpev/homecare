package config

import (
	"errors"
	"fmt"
	"os"
	"strings"
)

type Config struct {
	Port                       string
	PublicBaseURL              string
	TwilioAccountSID           string
	TwilioAuthToken            string
	TwilioPhoneNumber          string
	TwilioSMSFrom              string
	TwilioVoiceWebhookPath     string
	TwilioCallbackWebhookPath  string
	TwilioStreamPath           string
	StreamAuthToken            string
	DeepgramAPIKey             string
	DeepgramWSURL              string
	DeepgramLanguage           string
	DeepgramSTTModel           string
	DeepgramLLMModel           string
	DeepgramTTSModel           string
	DeepgramGreeting           string
	DeepgramCallbackGreeting   string
	DeepgramProviderGreeting   string
	LaravelBaseURL             string
	LaravelInternalAPIToken    string
	PromptFile                 string
	CallbackPromptFile         string
	ProviderOutreachPromptFile string
	RecordingsEnabled          bool
	RecordingsDir              string
	RecordingsPublicBaseURL    string
}

func Load() (Config, error) {
	cfg := Config{
		Port:                       env("PORT", "8088"),
		PublicBaseURL:              strings.TrimRight(env("PUBLIC_BASE_URL", env("APP_URL", "")), "/"),
		TwilioAccountSID:           env("TWILIO_ACCOUNT_SID", ""),
		TwilioAuthToken:            env("TWILIO_AUTH_TOKEN", ""),
		TwilioPhoneNumber:          env("TWILIO_PHONE_NUMBER", ""),
		TwilioSMSFrom:              env("TWILIO_SMS_FROM", env("TWILIO_PHONE_NUMBER", "")),
		TwilioVoiceWebhookPath:     env("TWILIO_VOICE_WEBHOOK_PATH", "/twilio/voice"),
		TwilioCallbackWebhookPath:  env("TWILIO_CALLBACK_WEBHOOK_PATH", "/twilio/callback-discovery"),
		TwilioStreamPath:           env("TWILIO_STREAM_PATH", "/ws/twilio"),
		StreamAuthToken:            env("TWILIO_STREAM_AUTH_TOKEN", ""),
		DeepgramAPIKey:             env("DEEPGRAM_API_KEY", ""),
		DeepgramWSURL:              env("DEEPGRAM_WS_URL", "wss://agent.deepgram.com/v1/agent/converse"),
		DeepgramLanguage:           env("DEEPGRAM_LANGUAGE", "en"),
		DeepgramSTTModel:           env("DEEPGRAM_STT_MODEL", "flux-general-en"),
		DeepgramLLMModel:           env("DEEPGRAM_LLM_MODEL", "gpt-4o-mini"),
		DeepgramTTSModel:           env("DEEPGRAM_TTS_MODEL", "aura-2-thalia-en"),
		DeepgramGreeting:           brandedEnv("DEEPGRAM_GREETING", "Thanks for calling LoLo Care. This is Julie. How can I help you today?"),
		DeepgramCallbackGreeting:   brandedEnv("DEEPGRAM_CALLBACK_GREETING", "Hi, this is Julie from LoLo Care calling about your care request. Did I catch you at an okay time?"),
		DeepgramProviderGreeting:   brandedEnv("DEEPGRAM_PROVIDER_OUTREACH_GREETING", "Hi, this is Julie calling from LoLo Care. Did I catch you at an okay time for a quick question?"),
		LaravelBaseURL:             strings.TrimRight(env("LARAVEL_BASE_URL", "http://localhost"), "/"),
		LaravelInternalAPIToken:    env("LARAVEL_INTERNAL_API_TOKEN", ""),
		PromptFile:                 env("PROMPT_FILE", "prompts/system.md"),
		CallbackPromptFile:         env("VOICE_AGENT_CALLBACK_PROMPT_FILE", "prompts/callback-discovery.md"),
		ProviderOutreachPromptFile: env("VOICE_AGENT_PROVIDER_OUTREACH_PROMPT_FILE", "prompts/provider-outreach.md"),
		RecordingsEnabled:          boolEnv("VOICE_AGENT_RECORDINGS_ENABLED", true),
		RecordingsDir:              env("VOICE_AGENT_RECORDINGS_DIR", "../storage/app/public/voice-agent-recordings"),
		RecordingsPublicBaseURL:    env("VOICE_AGENT_RECORDINGS_PUBLIC_BASE_URL", "/storage/voice-agent-recordings"),
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

func brandedEnv(key, fallback string) string {
	value := env(key, fallback)
	normalized := strings.ToLower(value)
	if !strings.Contains(normalized, "lolo care") || strings.Contains(normalized, "homecare") || strings.Contains(normalized, "laravel") {
		return fallback
	}

	return value
}

func boolEnv(key string, fallback bool) bool {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}

	return strings.EqualFold(value, "true") || value == "1" || strings.EqualFold(value, "yes")
}
