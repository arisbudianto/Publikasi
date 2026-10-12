# M04 Semantic AI and article evidence

Provider selected by owner: Dinoiki. Official base https://ai.dinoiki.com/v1; embeddings text-embedding-3-small. App endpoint fixed HTTPS with redirects disabled. No LLM-generated citations or full-text scraping.

Owner configuration in production .env only:
SEMANTIC_AI_ENABLED=true
DINOIKI_API_KEY="owner key"
Then php artisan config:cache. Never commit or share the key. Default disabled; mode returns 503 until enabled/key present. Basic M03 remains independent.

Explicit checkbox consent required server-side before title/abstract/context transfer to Dinoiki. Limits 300/5000/500 characters, POST 5/minute per client; shared global counter 100 requests/day (abuse bound, not monetary guarantee under concurrent requests). Maximum 50 published journals, otherwise refuse rather than silently truncate. Up to two embedding calls per request, no retries; connect5/total20 seconds. Scope batches cached 24h by content/model; manuscript/query vectors never cached or persisted by app. Hosting/provider retention not audited; do not claim zero third-party retention.

Normalized vector cosine similarity ranks up to 3 journals above initial 0.30 threshold. Threshold is uncalibrated, scores are not acceptance probability. Title/abstract/context combine in one embedding; truncated combined input capped6000 characters (field limits normally total5800). Journal scope capped5500 characters.

Crossref retrieves only public ISSN and up to3 recent article records per shortlisted journal, cached6h; no manuscript sent to Crossref. Metadata requires matching ISSN and DOI format. Article titles ranked via embeddings where available. Evidence is a small latest-record sample, not comprehensive topic search or proof of suitability; article abstracts/full text not analyzed. Provider/source failures visible; no fabricated data. Crossref timeout5 seconds and no retries. Missing/invalid metadata skipped. DOI resolver availability not asserted.

Sources: https://dinoiki.com/ai ; https://developers.openai.com/api/reference/resources/embeddings/methods/create ; https://www.crossref.org/documentation/retrieve-metadata/rest-api/ .
TESTED local PHP8.3: 29 tests /178 assertions PASS, HTTP fakes prevent paid/real calls. Consent, disabled AI, malformed/zero vectors, redaction, ordering/provenance, private journal exclusion, catalog/daily limits, article source failures, ISSN/DOI validation and M01-M03 regressions.
NOT VERIFIED live Dinoiki/key, full real-API matching, ranking quality on a manuscript corpus, provider retention, infra logging, physical mobile. Do not call live AI production-ready until key and live smoke complete.
DATA IMPACT: read only; cache public metadata/vectors + rate counters, no DB schema/seed/credential change.
ROLLBACK restore routes/web.php, public/catalog.css, resources/views/layout.blade.php to df296e5; rebuild autoload and route/view cache. New files then unused. No DB rollback required.
