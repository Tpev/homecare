package agent

import (
	"strings"
	"testing"

	"voice-agent/internal/laravel"
)

func TestProviderOutreachPromptIncludesProductionConversationGuardrails(t *testing.T) {
	builder, err := NewPromptBuilder("../../prompts/provider-outreach.md")
	if err != nil {
		t.Fatalf("NewPromptBuilder returned error: %v", err)
	}

	prompt, err := builder.Render(laravel.Knowledge{}, CallContext{
		Profile:            ProfileProviderOutreach,
		AssistantName:      "Julie",
		TargetOrganization: "Triangle Primary Care",
		TargetName:         "Steve",
		TargetRole:         "office_manager",
		CustomerPhone:      "+19195551234",
	})
	if err != nil {
		t.Fatalf("Render returned error: %v", err)
	}

	for _, expected := range []string{
		"Never say internal CRM labels out loud",
		"Do not deliver the full opening as one long monologue",
		"new home care service based in Raleigh",
		"older adults and families arrange non-medical support",
		"rides, meal prep, respite, light household help, and check-ins",
		"patients, residents, or families",
		"Who handles local resource options",
		"simple family resource sheet",
		"Would it be okay if I sent",
		"treat that as permission to send the family resource sheet",
		"Never rename the contact from the email prefix",
		"If Steve gives `jess@example.com`, keep calling him Steve",
		"Traditional agencies usually require families to call around and coordinate manually",
		"No follow-up needed unless you ask for one",
		"`resource_requested=true`, `follow_up_needed=false`",
		"Do this silently; do not narrate the internal status",
	} {
		if !strings.Contains(prompt, expected) {
			t.Fatalf("expected provider outreach prompt to contain %q", expected)
		}
	}

	if strings.Contains(prompt, "say you will mark that") {
		t.Fatal("provider outreach prompt still tells Julie to say an internal CRM action out loud")
	}
}
