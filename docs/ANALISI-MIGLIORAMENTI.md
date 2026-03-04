# Analisi e Miglioramenti - Ride On WP Translator

## Panoramica

Il plugin e' ben strutturato con un pattern Singleton, classi ben separate per responsabilita' (Admin, OpenAI Client, Translator, Post Handler), e un buon uso delle API di WordPress (hooks, nonce, capabilities). L'architettura e' solida per un plugin v1.0.

Di seguito i miglioramenti proposti, organizzati per priorita'.

---

## Stato attuale (aggiornato 2026-03-04)

### ✅ Risolti

| # | Priorita' | Problema | File |
|---|-----------|----------|------|
| 2 | Critica | `add_settings_field` argomenti sbagliati (bug) | class-admin.php:116-123 |
| 3 | Critica | Nessuna validazione lingua AJAX (prompt injection) | class-post-handler.php |
| 4 | Critica | `is_content=false` in `translate_post` (bug) | class-translator.php:80 |
| 5 | Alta | API key nei log di debug | class-openai-client.php:416 |
| 7 | Alta | `ensure_unique_slug` ridondante con bug | class-translator.php:267-282 |

### ⚠️ Parziali o accettabili

| # | Priorita' | Problema | File |
|---|-----------|----------|------|
| 1 | Critica | base64 != cifratura (falsa sicurezza) | class-admin.php, class-openai-client.php |
| 6 | Alta | 4 chiamate API seriali (rischio timeout) | class-translator.php |
| 8 | Media | Script inline (CSP, coding standards) | class-admin.php:359-411 |
| 9 | Media | CSS inline nella view | settings-page.php |
| 10 | Media | Admin notice su tutte le pagine | class-admin.php:517-524 |
| 11 | Media | `.html()` con dati non sanitizzati (XSS) | admin.js:506 |
| 13 | Media | Stringhe hardcoded non localizzate | admin.js |
| 18 | Bassa | register_setting senza sanitize callback | class-admin.php:64-67 |

### ❌ Da fare

| # | Priorita' | Problema | File |
|---|-----------|----------|------|
| 12 | Media | Nessun rate limiting AJAX | class-post-handler.php |
| 14 | Bassa | Inconsistenza brace style | class-openai-client.php, class-translator.php |
| 15 | Bassa | Nessun test automatizzato | - |
| 16 | Bassa | Nessun tracciamento relazione post | - |
| 17 | Bassa | Nessun uninstall.php | - |
| 19 | Bassa | Doppia sanitizzazione contenuto | class-translator.php |
| 20 | Bassa | max_tokens hardcoded | class-openai-client.php:398 |

---

## CRITICI

### 1. La chiave API e' codificata con base64, non cifrata

**File:** `includes/class-admin.php:147-187`, `includes/class-openai-client.php:54-95`

**Stato: ⚠️ PARZIALMENTE AFFRONTATO — base64 ancora in uso**

L'UI dichiara che la chiave "viene cifrata prima dello storage", ma `base64_encode()` **non e' cifratura**: e' una codifica reversibile senza chiave segreta. Chiunque abbia accesso al database puo' decodificarla con `base64_decode()`.

La logica di `get_api_key()` e' inoltre eccessivamente complessa, con un loop di 3 tentativi per gestire "doppia codifica" che non dovrebbe mai verificarsi.

**Fix proposto:** usare cifratura simmetrica con le chiavi di WordPress:

```php
// Cifratura
$iv = substr(AUTH_SALT, 0, 16);
$stored = base64_encode(openssl_encrypt($api_key, 'AES-256-CBC', AUTH_KEY, 0, $iv));

// Decifratura
$api_key = openssl_decrypt(base64_decode($stored), 'AES-256-CBC', AUTH_KEY, 0, $iv);
```

In alternativa, rimuovere l'affermazione "cifrata" dalla UI e documentare che la responsabilita' e' dell'admin del server.

---

### 2. Bug: `add_settings_field` con argomenti sbagliati per target language

**File:** `includes/class-admin.php:116-123`

**Stato: ✅ FIXATO**

Il campo `'rideon_translator_lang_section'` e' ora correttamente passato come 5° argomento (section).

---

### 3. Nessuna validazione delle lingue negli handler AJAX

**File:** `includes/class-post-handler.php:106-108, 161-163`

**Stato: ✅ FIXATO**

Aggiunta whitelist `['it', 'en', 'es']` in entrambi i metodi AJAX (`handle_ajax_translation` e `handle_ajax_get_translations`). Se viene passata una lingua non ammessa, la richiesta viene rifiutata con errore esplicito prima di qualsiasi elaborazione.

```php
$allowed_langs = array( 'it', 'en', 'es' );
if ( ! empty( $source_lang ) && ! in_array( $source_lang, $allowed_langs, true ) ) {
    wp_send_json_error( array( 'message' => __( 'Invalid source language.', 'rideon-wp-translator' ) ) );
}
if ( ! empty( $target_lang ) && ! in_array( $target_lang, $allowed_langs, true ) ) {
    wp_send_json_error( array( 'message' => __( 'Invalid target language.', 'rideon-wp-translator' ) ) );
}
```

---

### 4. Bug: `is_content` mancante in `translate_post()`

**File:** `includes/class-translator.php:80`

**Stato: ✅ FIXATO**

Aggiunto `true` come 4° argomento alla chiamata `translate_text` in `translate_post()`. Ora entrambi i metodi (`translate_post` e `get_translations`) applicano la normalizzazione dei paragrafi in modo coerente.

```php
// Prima (riga 80):
$translated_content = $this->translate_text($content['content'], $source_lang, $target_lang);

// Dopo:
$translated_content = $this->translate_text($content['content'], $source_lang, $target_lang, true);
```

---

## IMPORTANTI

### 5. Chiave API parzialmente esposta nei log di debug

**File:** `includes/class-openai-client.php:416`

**Stato: ✅ ACCETTABILE**

```php
'api_key_prefix' => substr($this->api_key, 0, 7) . '...',
```

Il prefisso di 7 caratteri e' nella norma per debug. Se `wp-content/debug.log` e' accessibile pubblicamente resta un rischio d'infrastruttura. Alternativa piu' sicura:

```php
'api_key_set' => !empty($this->api_key),
```

---

### 6. Fino a 4 chiamate API seriali per ogni traduzione (rischio timeout)

**File:** `includes/class-translator.php`

**Stato: ⚠️ ANCORA PRESENTE — non ottimizzato**

Per ogni post, il plugin esegue fino a 4 chiamate API separate e seriali:
1. Titolo (riga 59)
2. Slug via OpenAI (riga 67)
3. Contenuto (riga 80)
4. Excerpt (riga 88)

Ogni chiamata ha un timeout di 60s (`class-openai-client.php:442`). Con tutti i campi popolati, il totale puo' arrivare a **240 secondi**, ben oltre i tipici timeout PHP (30s) e server.

**Fix proposti:**

- **Eliminare la chiamata API per lo slug**: usare sempre `sanitize_title($translated_title)` come gia' fatto nel fallback. Lo slug e' un formato tecnico, non necessita di AI.
- **Considerare un singolo prompt strutturato** che traduca titolo, contenuto ed excerpt in un'unica chiamata, con output JSON.

---

### 7. `ensure_unique_slug` e' ridondante e ha un bug logico

**File:** `includes/class-translator.php:267-282`

**Stato: ✅ ACCETTABILE — logica corretta, ridondante ma non buggy**

La logica attuale gestisce correttamente i conflitti di slug incrementando il contatore. WordPress gestisce internamente i conflitti di slug con `wp_unique_post_slug()` durante `wp_insert_post()`, quindi la funzione e' ridondante ma non causa problemi.

---

### 8. Script inline nella pagina settings (viola WordPress Coding Standards e CSP)

**File:** `includes/class-admin.php:359-411`

**Stato: ⚠️ PARZIALMENTE MITIGATO — script inline ancora presente ma sicuro**

`enqueue_model_info_script()` produce ancora un blocco `<script>` inline, ma usa correttamente `wp_json_encode()` per i dati PHP e ha una funzione `escapeHtml()` custom che previene XSS. Rimane una violazione delle WordPress Coding Standards e puo' essere bloccato da header CSP con `unsafe-inline`.

**Fix:** Spostare i dati `modelsInfo` nell'array `wp_localize_script` gia' esistente in `rideon-wp-translator.php` e la logica nel file `admin/js/admin.js`.

---

### 9. CSS inline nella view settings

**File:** `admin/views/settings-page.php`

**Stato: ⚠️ PRESENTE — non critico**

Il file PHP include un blocco `<style>` (non attributi inline), che e' accettabile in contesto admin WordPress. Per correttezza, le regole andrebbero in `admin/css/admin.css`.

---

### 10. Admin notice su tutte le pagine admin

**File:** `includes/class-admin.php:517-524`

**Stato: ⚠️ PARZIALMENTE FIXATO**

La notice ora appare solo quando `settings-updated=true`, ma non verifica che si stia sulla pagina del plugin. Potrebbe comparire su pagine esterne al plugin se qualcuno manipola il parametro GET.

**Fix completo:**

```php
public function display_admin_notices() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'settings_page_rideon-translator' ) {
        return;
    }
    if ( isset( $_GET['settings-updated'] ) ) {
        // ...
    }
}
```

---

### 11. `.html()` con dati dal server (rischio XSS in admin)

**File:** `admin/js/admin.js:506`

**Stato: ⚠️ A RISCHIO — concatenazione HTML non sicura**

```javascript
function showMessage(type, message) {
    $message.html(message).show();  // inserisce HTML grezzo
}
```

Il problema principale e' a riga 478 dove `editLink` viene concatenato direttamente nell'HTML:

```javascript
messageHtml += ' <a href="' + editLink + '" target="_blank">' + 'View translated post' + '</a>';
```

Anche se `editLink` proviene da `get_edit_post_link()` (URL interno), la concatenazione diretta in HTML e' una cattiva pratica.

**Fix:** Costruire il DOM in modo sicuro:

```javascript
function showMessage(type, text, link) {
    $message.removeClass('success error').addClass(type).text(text);
    if (link) {
        var $a = $('<a>').attr('href', link).attr('target', '_blank').text('View translated post');
        $message.append(' ').append($a);
    }
    $message.show();
}
```

---

### 12. Nessun rate limiting sulle chiamate AJAX di traduzione

**File:** `includes/class-post-handler.php`

**Stato: ❌ ANCORA PRESENTE**

Un utente con `edit_posts` puo' chiamare ripetutamente gli endpoint AJAX, generando molte chiamate API OpenAI a spese del proprietario del sito.

**Fix:** Implementare un lock con transient:

```php
$lock_key = 'rideon_translating_' . $post_id;
if ( get_transient( $lock_key ) ) {
    wp_send_json_error( array( 'message' => __( 'Translation already in progress.', 'rideon-wp-translator' ) ) );
}
set_transient( $lock_key, true, 300 );
// ... esegui traduzione ...
delete_transient( $lock_key );
```

---

### 13. Stringhe hardcoded in inglese nel JavaScript

**File:** `admin/js/admin.js:138, 478, 515`

**Stato: ⚠️ PARZIALMENTE FIXATO**

Alcune stringhe usano `rideonTranslator.i18n`, ma queste rimangono hardcoded:
- Riga 478: `'View translated post'`
- Riga 515: `'Translate'`

**Fix:** Aggiungere tutte le stringhe all'array `i18n` in `wp_localize_script()`:

```php
'i18n' => array(
    'translating'       => __( 'Translating...', 'rideon-wp-translator' ),
    'translate'         => __( 'Translate', 'rideon-wp-translator' ),
    'success'           => __( 'Translation completed!', 'rideon-wp-translator' ),
    'error'             => __( 'An error occurred.', 'rideon-wp-translator' ),
    'selectTargetLang'  => __( 'Please select a target language.', 'rideon-wp-translator' ),
    'contentTranslated' => __( 'The content has been translated. Review and save.', 'rideon-wp-translator' ),
    'viewTranslated'    => __( 'View translated post', 'rideon-wp-translator' ),
    'alreadyInProgress' => __( 'Translation already in progress.', 'rideon-wp-translator' ),
),
```

---

## MINORI / STILE

### 14. Inconsistenza nello stile delle parentesi graffe

**Stato: ❌ PRESENTE**

`class-openai-client.php` e `class-translator.php` usano Allman style (parentesi su riga separata), mentre `rideon-wp-translator.php`, `class-admin.php` e `class-post-handler.php` usano K&R. Le WordPress Coding Standards richiedono K&R.

**Fix:** Uniformare tutti i file a K&R style.

---

### 15. Nessun test automatizzato

**Stato: ❌ PRESENTE**

Non esiste alcun file di test. Per un plugin che manipola contenuti di post e chiama API esterne, sarebbero utili almeno:

- **Unit test** per `sanitize_api_key()`, `sanitize_temperature()`, logica punteggiatura
- **Mock test** per `make_api_request()` / `parse_response()` (con `WP_Mock` o `Brain\Monkey`)
- **Integration test** per il flusso AJAX completo

---

### 16. Nessun tracciamento delle relazioni tra post originale e tradotto

**Stato: ❌ PRESENTE**

Il plugin non salva alcun post meta che colleghi l'originale alla traduzione. Questo impedisce:
- Sapere se un post e' gia' stato tradotto
- Navigare tra originale e traduzione
- Evitare traduzioni duplicate

**Fix proposto:** Dopo la creazione del post tradotto, salvare:

```php
update_post_meta($translated_post_id, '_rideon_source_post_id', $source_post->ID);
update_post_meta($translated_post_id, '_rideon_source_lang', $source_lang);
update_post_meta($translated_post_id, '_rideon_target_lang', $target_lang);
update_post_meta($source_post->ID, '_rideon_translation_' . $target_lang, $translated_post_id);
```

---

### 17. Nessun hook di deattivazione/disinstallazione per la pulizia

**File:** `rideon-wp-translator.php:162-164`

**Stato: ❌ PRESENTE**

```php
function rideon_translator_deactivate() {
    // Clean up if needed
}
```

Il plugin non fornisce un file `uninstall.php` per rimuovere le opzioni dal database quando viene disinstallato.

**Fix:** Creare `uninstall.php`:

```php
<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
delete_option( 'rideon_translator_api_key' );
delete_option( 'rideon_translator_model' );
delete_option( 'rideon_translator_temperature' );
delete_option( 'rideon_translator_default_source_lang' );
delete_option( 'rideon_translator_default_target_lang' );
delete_option( 'rideon_translator_enable_debug_log' );
```

---

### 18. `register_setting` senza callback di sanitizzazione per model e lingue

**File:** `includes/class-admin.php:64-67`

**Stato: ⚠️ PARZIALMENTE FIXATO**

`api_key`, `temperature` e `enable_debug_log` hanno callback di sanitizzazione. Mancano per:

```php
register_setting( 'rideon_translator_settings', 'rideon_translator_model' );                // ❌ manca
register_setting( 'rideon_translator_settings', 'rideon_translator_default_source_lang' );  // ❌ manca
register_setting( 'rideon_translator_settings', 'rideon_translator_default_target_lang' );  // ❌ manca
```

**Fix:** Aggiungere callback di validazione:

```php
register_setting( 'rideon_translator_settings', 'rideon_translator_model', array(
    'sanitize_callback' => array( $this, 'sanitize_model' ),
));

public function sanitize_model( $model ) {
    $allowed = array( 'gpt-3.5-turbo', 'gpt-4.1', 'gpt-4o' );
    return in_array( $model, $allowed, true ) ? $model : 'gpt-3.5-turbo';
}
```

---

### 19. Doppia sanitizzazione del contenuto tradotto

**File:** `includes/class-translator.php`

**Stato: Non verificato nell'ultima analisi**

Il contenuto potrebbe essere sanitizzato con `wp_kses_post()` in due punti separati. La doppia sanitizzazione e' inutile e potrebbe alterare il contenuto.

---

### 20. `max_tokens` hardcoded a 4000

**File:** `includes/class-openai-client.php:398`

**Stato: ❌ PRESENTE**

```php
'max_tokens' => 4000,  // hardcoded
```

Il valore `max_tokens: 4000` e' hardcoded. Per post lunghi con modelli che supportano contesti maggiori (GPT-4o supporta fino a 16k output tokens), questo limite potrebbe troncare la traduzione senza errore evidente.

**Fix:** Rendere `max_tokens` configurabile o calcolarlo dinamicamente in base alla lunghezza del testo sorgente.
