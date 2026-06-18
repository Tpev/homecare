# Voice Agent

This Go service answers Twilio phone calls, bridges live audio to the Deepgram Voice Agent API, and calls back into Laravel for approved knowledge, lead intake, callback requests, and signup-link generation.

## What It Does

- `POST /twilio/voice` validates the Twilio webhook and returns TwiML with `<Connect><Stream>`.
- `POST /twilio/callback-discovery` does the same for outbound callback/discovery calls started from Laravel.
- `GET /ws/twilio` accepts the Twilio Media Streams WebSocket.
- Opens a Deepgram Voice Agent WebSocket at `wss://agent.deepgram.com/v1/agent/converse`.
- Uses Deepgram STT plus TTS and OpenAI `gpt-4o-mini` through Deepgram's `think` provider.
- Executes client-side tools for approved service lookup, human callback requests, and signup link creation.

## Setup

1. Copy `.env.example` to `.env` or export the variables in your shell.
2. Set `PUBLIC_BASE_URL` to the public HTTPS base URL for this service.
3. Set `LARAVEL_BASE_URL` to the Laravel app and `LARAVEL_INTERNAL_API_TOKEN` to the same value as Laravel's `VOICE_AGENT_INTERNAL_API_TOKEN`.
4. Point your Twilio number's voice webhook to `https://<your-voice-domain>/twilio/voice`.
5. In Laravel, set `TWILIO_VOICE_AGENT_CALLBACK_URL=https://<your-voice-domain>/twilio/callback-discovery` so admin callback tests use this Deepgram voice bridge.
6. Run:

```bash
go mod tidy
go run ./cmd/server
```

## Notes

- Twilio webhook validation uses `X-Twilio-Signature`.
- The WebSocket bridge is protected by a custom stream token embedded in the TwiML `<Parameter>` block.
- If no callback or signup action is taken, the service records the call as an informational lead at the end of the session.
- Inbound call guidance lives in `prompts/system.md`; outbound callback/discovery guidance lives in `prompts/callback-discovery.md`.
