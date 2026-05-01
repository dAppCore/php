package php

// This file collects small package-local helpers that fill gaps in the
// pinned dappco.re/go release (currently v0.9.0). Each helper has a
// matching core/go primitive landing post-#1329; remove this file and
// inline the canonical core wrapper once the dappco.re/go bump lands.

// trimTrailingSlash strips trailing "/" runes from s. Equivalent of
// strings.TrimRight(s, "/") for single-rune trim without importing
// strings; replaced by core.TrimRight when available.
func trimTrailingSlash(s string) string {
	for len(s) > 0 && s[len(s)-1] == '/' {
		s = s[:len(s)-1]
	}
	return s
}
