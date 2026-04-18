package twilio

import (
	"crypto/hmac"
	"crypto/sha1"
	"crypto/subtle"
	"encoding/base64"
	"net/url"
	"sort"
	"strings"
)

func ValidateSignature(authToken, requestURL string, form url.Values, provided string) bool {
	if strings.TrimSpace(authToken) == "" || strings.TrimSpace(provided) == "" {
		return false
	}

	base := requestURL
	keys := make([]string, 0, len(form))
	for key := range form {
		keys = append(keys, key)
	}
	sort.Strings(keys)

	for _, key := range keys {
		values := append([]string(nil), form[key]...)
		sort.Strings(values)
		for _, value := range values {
			base += key + value
		}
	}

	mac := hmac.New(sha1.New, []byte(authToken))
	mac.Write([]byte(base))
	expected := base64.StdEncoding.EncodeToString(mac.Sum(nil))

	return subtle.ConstantTimeCompare([]byte(expected), []byte(strings.TrimSpace(provided))) == 1
}
