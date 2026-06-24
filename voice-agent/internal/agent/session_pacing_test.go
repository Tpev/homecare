package agent

import (
	"context"
	"testing"
	"time"
)

func TestProviderOutreachAssistantAudioWaitsBeforeFirstChunk(t *testing.T) {
	originalDelay := providerAssistantTurnDelay
	providerAssistantTurnDelay = 8 * time.Millisecond
	defer func() {
		providerAssistantTurnDelay = originalDelay
	}()

	session := &Session{promptProfile: ProfileProviderOutreach}

	startedAt := time.Now()
	shouldSend, err := session.beforeAssistantAudio(context.Background())
	if err != nil {
		t.Fatalf("beforeAssistantAudio returned error: %v", err)
	}
	if !shouldSend {
		t.Fatal("expected first assistant chunk to send")
	}
	if elapsed := time.Since(startedAt); elapsed < providerAssistantTurnDelay {
		t.Fatalf("expected first provider assistant chunk to wait at least %s, waited %s", providerAssistantTurnDelay, elapsed)
	}

	startedAt = time.Now()
	shouldSend, err = session.beforeAssistantAudio(context.Background())
	if err != nil {
		t.Fatalf("second beforeAssistantAudio returned error: %v", err)
	}
	if !shouldSend {
		t.Fatal("expected later chunks in same assistant turn to send")
	}
	if elapsed := time.Since(startedAt); elapsed > providerAssistantTurnDelay {
		t.Fatalf("expected later chunks in same turn not to wait again, waited %s", elapsed)
	}
}

func TestProviderOutreachAssistantAudioSuppressesIfCallerSpeaksDuringInitialBeat(t *testing.T) {
	originalDelay := providerAssistantTurnDelay
	providerAssistantTurnDelay = 20 * time.Millisecond
	defer func() {
		providerAssistantTurnDelay = originalDelay
	}()

	session := &Session{promptProfile: ProfileProviderOutreach}
	result := make(chan bool, 1)
	errs := make(chan error, 1)

	go func() {
		shouldSend, err := session.beforeAssistantAudio(context.Background())
		result <- shouldSend
		errs <- err
	}()

	time.Sleep(5 * time.Millisecond)
	session.noteCallerAudio()

	select {
	case err := <-errs:
		if err != nil {
			t.Fatalf("beforeAssistantAudio returned error: %v", err)
		}
	case <-time.After(100 * time.Millisecond):
		t.Fatal("timed out waiting for pacing result")
	}

	select {
	case shouldSend := <-result:
		if shouldSend {
			t.Fatal("expected assistant turn to be suppressed when caller speaks during initial beat")
		}
	case <-time.After(100 * time.Millisecond):
		t.Fatal("timed out waiting for send decision")
	}

	if shouldSend, err := session.beforeAssistantAudio(context.Background()); err != nil {
		t.Fatalf("suppressed turn returned error: %v", err)
	} else if shouldSend {
		t.Fatal("expected remaining chunks in suppressed assistant turn to be dropped")
	}

	session.finishAssistantTurn()

	if shouldSend, err := session.beforeAssistantAudio(context.Background()); err != nil {
		t.Fatalf("new assistant turn returned error: %v", err)
	} else if !shouldSend {
		t.Fatal("expected new assistant turn to send after AgentAudioDone reset")
	}
}

func TestInboundAssistantAudioDoesNotUseProviderPacing(t *testing.T) {
	originalDelay := providerAssistantTurnDelay
	providerAssistantTurnDelay = 50 * time.Millisecond
	defer func() {
		providerAssistantTurnDelay = originalDelay
	}()

	session := &Session{promptProfile: ProfileInbound}
	startedAt := time.Now()

	shouldSend, err := session.beforeAssistantAudio(context.Background())
	if err != nil {
		t.Fatalf("beforeAssistantAudio returned error: %v", err)
	}
	if !shouldSend {
		t.Fatal("expected inbound assistant audio to send")
	}
	if elapsed := time.Since(startedAt); elapsed >= providerAssistantTurnDelay {
		t.Fatalf("expected inbound assistant audio not to wait for provider pacing, waited %s", elapsed)
	}
}
