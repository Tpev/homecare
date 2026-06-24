package config

import "testing"

func TestLoadUsesAppURLAsPublicBaseFallback(t *testing.T) {
	t.Setenv("APP_URL", "https://carelolo.com")
	t.Setenv("PUBLIC_BASE_URL", "")
	t.Setenv("TWILIO_ACCOUNT_SID", "AC123")
	t.Setenv("TWILIO_AUTH_TOKEN", "twilio-secret")
	t.Setenv("TWILIO_PHONE_NUMBER", "+15551234567")
	t.Setenv("DEEPGRAM_API_KEY", "deepgram-secret")
	t.Setenv("LARAVEL_INTERNAL_API_TOKEN", "voice-secret")
	t.Setenv("DEEPGRAM_LLM_MODEL", "gpt-4o-mini")

	cfg, err := Load()
	if err != nil {
		t.Fatalf("Load returned error: %v", err)
	}

	if cfg.PublicBaseURL != "https://carelolo.com" {
		t.Fatalf("expected APP_URL fallback for PublicBaseURL, got %q", cfg.PublicBaseURL)
	}

	if cfg.TwilioCallbackWebhookPath != "/twilio/callback-discovery" {
		t.Fatalf("expected callback webhook default, got %q", cfg.TwilioCallbackWebhookPath)
	}

	if cfg.CallbackPromptFile != "prompts/callback-discovery.md" {
		t.Fatalf("expected callback prompt default, got %q", cfg.CallbackPromptFile)
	}

	if cfg.ProviderOutreachPromptFile != "prompts/provider-outreach.md" {
		t.Fatalf("expected provider outreach prompt default, got %q", cfg.ProviderOutreachPromptFile)
	}

	if cfg.DeepgramProviderGreeting == "" {
		t.Fatal("expected provider outreach greeting default")
	}

	if !cfg.RecordingsEnabled {
		t.Fatal("expected local recordings to default on")
	}

	if cfg.RecordingsPublicBaseURL != "/storage/voice-agent-recordings" {
		t.Fatalf("expected recording public URL default, got %q", cfg.RecordingsPublicBaseURL)
	}
}
