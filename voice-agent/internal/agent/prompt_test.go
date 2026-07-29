package agent

import (
	"context"
	"strings"
	"testing"

	"voice-agent/internal/laravel"
)

func TestFamilyPromptsEnforceLoLoCareCallbacksAndCaregiverApplications(t *testing.T) {
	tests := []struct {
		path    string
		profile string
	}{
		{path: "../../prompts/system.md", profile: ProfileInbound},
		{path: "../../prompts/callback-discovery.md", profile: ProfileCallbackDiscovery},
	}

	for _, test := range tests {
		t.Run(test.profile, func(t *testing.T) {
			builder, err := NewPromptBuilder(test.path)
			if err != nil {
				t.Fatalf("NewPromptBuilder returned error: %v", err)
			}

			prompt, err := builder.Render(laravel.Knowledge{
				BrandName: "Homecare",
				SignupLinks: map[string]string{
					"caregiver": "https://carelolo.com/caregiver/register",
				},
			}, CallContext{Profile: test.profile, AssistantName: "Julie"})
			if err != nil {
				t.Fatalf("Render returned error: %v", err)
			}

			for _, expected := range []string{
				`Always identify the company as "LoLo Care."`,
				"human",
				"Charles",
				"requested_contact",
				"as soon as possible",
				"provide_caregiver_application_info",
				"create a caregiver account",
				"family signup",
			} {
				if !strings.Contains(prompt, expected) {
					t.Fatalf("expected %s prompt to contain %q", test.profile, expected)
				}
			}
		})
	}
}

func TestVoiceToolsSeparateFamilyAndProviderActions(t *testing.T) {
	inbound := &Session{promptProfile: ProfileInbound}
	inboundNames := functionNames(inbound.deepgramFunctions())
	for _, expected := range []string{
		"lookup_service_info",
		"request_human_callback",
		"send_signup_link",
		"provide_caregiver_application_info",
	} {
		if !inboundNames[expected] {
			t.Fatalf("expected inbound tools to include %q", expected)
		}
	}
	if inboundNames["record_provider_outreach_result"] {
		t.Fatal("inbound tools unexpectedly expose provider outreach CRM action")
	}

	provider := &Session{promptProfile: ProfileProviderOutreach}
	providerNames := functionNames(provider.deepgramFunctions())
	if len(providerNames) != 2 || !providerNames["lookup_service_info"] || !providerNames["record_provider_outreach_result"] {
		t.Fatalf("unexpected provider outreach tools: %#v", providerNames)
	}
}

func TestCaregiverApplicationToolReturnsApprovedWebsiteAndSetsIntent(t *testing.T) {
	session := &Session{
		leadType: "family",
		intent:   "unknown",
		knowledge: laravel.Knowledge{SignupLinks: map[string]string{
			"caregiver": "https://carelolo.com/caregiver/register",
		}},
	}

	content, err := session.executeFunction(context.Background(), dgFunctionCall{
		Name:      "provide_caregiver_application_info",
		Arguments: `{"name":"Jamie"}`,
	})
	if err != nil {
		t.Fatalf("executeFunction returned error: %v", err)
	}

	if !strings.Contains(content, "https://carelolo.com/caregiver/register") || !strings.Contains(content, "create a caregiver account") {
		t.Fatalf("unexpected caregiver application response: %s", content)
	}
	if session.leadType != "caregiver" || session.intent != "caregiver_application" || session.outcome != "caregiver_signup_directed" {
		t.Fatalf("unexpected caregiver application state: lead_type=%q intent=%q outcome=%q", session.leadType, session.intent, session.outcome)
	}
	if session.callerName != "Jamie" {
		t.Fatalf("expected caller name to be preserved, got %q", session.callerName)
	}
}

func functionNames(functions []map[string]any) map[string]bool {
	names := make(map[string]bool, len(functions))
	for _, function := range functions {
		if name, ok := function["name"].(string); ok {
			names[name] = true
		}
	}

	return names
}

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
		`Always identify the company as "LoLo Care."`,
		"Never say internal CRM labels out loud",
		"Do not deliver the full opening as one long monologue",
		"Did I catch you at an okay time for a quick question?",
		"Do not continue into the explanation until the person responds",
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
		"If they ask for Charles",
		"LoLo Care team will follow up as soon as possible",
	} {
		if !strings.Contains(prompt, expected) {
			t.Fatalf("expected provider outreach prompt to contain %q", expected)
		}
	}

	if strings.Contains(prompt, "say you will mark that") {
		t.Fatal("provider outreach prompt still tells Julie to say an internal CRM action out loud")
	}

	if strings.Contains(prompt, "Then continue with opening turn 3") {
		t.Fatal("provider outreach prompt still uses a forced multi-step opening")
	}
}
