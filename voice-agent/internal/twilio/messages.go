package twilio

type Message struct {
	Event          string        `json:"event"`
	SequenceNumber string        `json:"sequenceNumber,omitempty"`
	StreamSID      string        `json:"streamSid,omitempty"`
	Start          *StartPayload `json:"start,omitempty"`
	Media          *MediaPayload `json:"media,omitempty"`
}

type StartPayload struct {
	AccountSID       string            `json:"accountSid"`
	CallSID          string            `json:"callSid"`
	StreamSID        string            `json:"streamSid"`
	CustomParameters map[string]string `json:"customParameters"`
}

type MediaPayload struct {
	Track     string `json:"track,omitempty"`
	Payload   string `json:"payload"`
	Chunk     string `json:"chunk,omitempty"`
	Timestamp string `json:"timestamp,omitempty"`
}

type OutboundMediaMessage struct {
	Event     string               `json:"event"`
	StreamSID string               `json:"streamSid"`
	Media     OutboundMediaPayload `json:"media"`
}

type OutboundMediaPayload struct {
	Payload string `json:"payload"`
}

type ClearMessage struct {
	Event     string `json:"event"`
	StreamSID string `json:"streamSid"`
}
